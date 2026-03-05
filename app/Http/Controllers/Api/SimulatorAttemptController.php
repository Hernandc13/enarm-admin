<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Simulator;
use App\Models\SimulatorAttempt;
use App\Models\SimulatorAttemptAnswer;
use App\Models\SimulatorAttemptQuestion;
use App\Services\SimulatorStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SimulatorAttemptController extends Controller
{
    public function start(Request $request, Simulator $simulator)
    {
        $user = $request->user();
        $now  = Carbon::now();

        if (!(bool) $simulator->is_published || ! $simulator->isAvailableNow()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Simulador no disponible.',
            ], 404);
        }

        // Límite de intentos
        if (!is_null($simulator->max_attempts)) {
            $count = SimulatorAttempt::where('user_id', $user->id)
                ->where('simulator_id', $simulator->id)
                ->whereIn('status', ['in_progress', 'finished'])
                ->count();

            if ($count >= (int) $simulator->max_attempts) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Has alcanzado el máximo de intentos para este simulador.',
                ], 403);
            }
        }

        // ✅ Obtener preguntas respetando pivot order (si NO se baraja)
        $qQuery = $simulator->questions()->select('questions.id');

        if (!(bool) $simulator->shuffle_questions) {
            $qQuery->orderBy('simulator_questions.order');
        }

        $questionIds = $qQuery->pluck('questions.id')->all();

        if (empty($questionIds)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Este simulador no tiene preguntas configuradas.',
            ], 422);
        }

        if ((bool) $simulator->shuffle_questions) {
            shuffle($questionIds);
        }

        $expiresAt = null;
        if (!is_null($simulator->time_limit_seconds) && (int) $simulator->time_limit_seconds > 0) {
            $expiresAt = $now->copy()->addSeconds((int) $simulator->time_limit_seconds);
        }

        $attempt = DB::transaction(function () use ($user, $simulator, $now, $expiresAt, $questionIds) {
            $attempt = SimulatorAttempt::create([
                'simulator_id'     => $simulator->id,
                'user_id'          => $user->id,
                'started_at'       => $now,
                'expires_at'       => $expiresAt,
                'status'           => 'in_progress',
                'total_questions'  => count($questionIds),
            ]);

            $rows = [];
            foreach ($questionIds as $idx => $qid) {
                $rows[] = [
                    'attempt_id' => $attempt->id,
                    'question_id'=> $qid,
                    'position'   => $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            SimulatorAttemptQuestion::insert($rows);

            return $attempt;
        });

        return response()->json([
            'ok'   => true,
            'data' => [
                'attempt_id'     => $attempt->id,
                'simulator_id'   => $simulator->id,

                // ✅ NUEVO: modo (study|exam)
                'mode'           => $simulator->mode ?? Simulator::MODE_EXAM,

                'started_at'     => optional($attempt->started_at)->toIso8601String(),
                'expires_at'     => optional($attempt->expires_at)->toIso8601String(),
                'total_questions'=> (int) $attempt->total_questions,
            ],
        ]);
    }

    public function questions(Request $request, SimulatorAttempt $attempt)
    {
        $user = $request->user();

        if ((int) $attempt->user_id !== (int) $user->id) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json(['ok' => false, 'message' => 'El intento no está activo.'], 422);
        }

        if (!is_null($attempt->expires_at) && now()->greaterThan($attempt->expires_at)) {
            $attempt->update(['status' => 'expired', 'finished_at' => now()]);
            return response()->json(['ok' => false, 'message' => 'Tiempo agotado.'], 410);
        }

        $limit = (int) ($request->query('limit', 10));
        $limit = max(1, min(25, $limit));

        $attempt->load('simulator');

        $paginator = $attempt->attemptQuestions()
            ->with(['question'])
            ->orderBy('position')
            ->paginate($limit);

        $shuffleOptions = (bool) ($attempt->simulator?->shuffle_options ?? false);

        $resolveQuestionText = function ($q): string {
            $candidates = [
                'stem',
                'question_text',
                'text',
                'name',
                'question',
                'statement',
                'prompt',
                'title',
                'enunciado',
                'contenido',
                'content',
                'description',
                'reference',
            ];

            foreach ($candidates as $field) {
                if (isset($q->{$field}) && is_string($q->{$field})) {
                    $val = trim($q->{$field});
                    if ($val !== '') {
                        return strip_tags($val);
                    }
                }
            }
            return '';
        };

        $data = $paginator->getCollection()->map(function ($row) use ($attempt, $shuffleOptions, $resolveQuestionText) {
            $q = $row->question;

            $questionText = trim((string) ($q->stem ?? ''));
            if ($questionText === '') {
                $questionText = $resolveQuestionText($q);
            }

            // Opciones
            $options = [];

            if (method_exists($q, 'options')) {
                $opts = $q->options()
                    ->orderBy('order')
                    ->get(['id', 'text', 'is_correct', 'order']);

                $options = $opts->map(fn ($o) => [
                    'id'   => (int) $o->id,
                    'text' => (string) ($o->text ?? ''),
                ])->values()->all();
            }

            if ($shuffleOptions && count($options) > 1) {
                shuffle($options);
            }

            $ans = $attempt->answers()->where('question_id', $q->id)->first();

            return [
                'position'           => (int) $row->position,
                'question_id'        => (int) $q->id,
                'text'               => (string) $questionText,
                'options'            => $options,
                'answered'           => $ans ? true : false,
                'selected_option_id' => $ans?->selected_option_id,
                'selected_text'      => $ans?->selected_text,
            ];
        });

        return response()->json([
            'ok'   => true,
            'data' => $data,
            'meta' => [
                'current_page'   => $paginator->currentPage(),
                'last_page'      => $paginator->lastPage(),
                'per_page'       => $paginator->perPage(),
                'total'          => $paginator->total(),
                'expires_at'     => optional($attempt->expires_at)->toIso8601String(),

                // ✅ NUEVO: modo del simulador en meta
                'simulator_mode' => $attempt->simulator?->mode ?? Simulator::MODE_EXAM,
            ],
        ]);
    }

    public function answer(Request $request, SimulatorAttempt $attempt)
    {
        $user = $request->user();

        if ((int) $attempt->user_id !== (int) $user->id) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }
        if ($attempt->status !== 'in_progress') {
            return response()->json(['ok' => false, 'message' => 'El intento no está activo.'], 422);
        }
        if (!is_null($attempt->expires_at) && now()->greaterThan($attempt->expires_at)) {
            $attempt->update(['status' => 'expired', 'finished_at' => now()]);
            return response()->json(['ok' => false, 'message' => 'Tiempo agotado.'], 410);
        }

        $data = $request->validate([
            'question_id'        => ['required', 'integer'],
            'selected_option_id' => ['nullable', 'integer'],
            'selected_text'      => ['nullable', 'string'],
        ]);

        $exists = $attempt->attemptQuestions()
            ->where('question_id', $data['question_id'])
            ->exists();

        if (!$exists) {
            return response()->json(['ok' => false, 'message' => 'Pregunta inválida para este intento.'], 422);
        }

        $isCorrect = false;

        $question = Question::findOrFail($data['question_id']);

        // ✅ Determinar si es correcta
        if (!is_null($data['selected_option_id']) && method_exists($question, 'options')) {
            $opt = $question->options()->where('id', $data['selected_option_id'])->first();
            if ($opt && isset($opt->is_correct)) {
                $isCorrect = (bool) $opt->is_correct;
            }
        }

        if (
            !$isCorrect &&
            !is_null($data['selected_option_id']) &&
            isset($question->correct_option_id)
        ) {
            $isCorrect = ((int) $question->correct_option_id === (int) $data['selected_option_id']);
        }

        SimulatorAttemptAnswer::updateOrCreate(
            [
                'attempt_id'  => $attempt->id,
                'question_id' => (int) $data['question_id'],
            ],
            [
                'selected_option_id' => $data['selected_option_id'] ?? null,
                'selected_text'      => $data['selected_text'] ?? null,
                'is_correct'         => $isCorrect,
            ]
        );

        return response()->json([
            'ok'   => true,
            'data' => [
                'question_id' => (int) $data['question_id'],
                'saved'       => true,
            ],
        ]);
    }

    public function finish(Request $request, SimulatorAttempt $attempt)
    {
        $user = $request->user();

        if ((int) $attempt->user_id !== (int) $user->id) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json(['ok' => false, 'message' => 'El intento ya fue cerrado.'], 422);
        }

        if (!is_null($attempt->expires_at) && now()->greaterThan($attempt->expires_at)) {
            $attempt->update(['status' => 'expired', 'finished_at' => now()]);
            return response()->json(['ok' => false, 'message' => 'Tiempo agotado.'], 410);
        }

        $attempt->load('simulator');

        $total   = (int) $attempt->total_questions;
        $correct = (int) $attempt->answers()->where('is_correct', true)->count();
        $score   = $total > 0 ? (int) round(($correct / $total) * 100) : 0;

        $attempt->update([
            'correct_count' => $correct,
            'score'         => $score,
            'finished_at'   => now(),
            'status'        => 'finished',
        ]);

        // ✅ IMPORTANTE: actualiza tus stats (record, promedios, counts, etc.)
        (new SimulatorStatsService())->recordFinishedAttempt($attempt->fresh());

        $min    = $attempt->simulator?->min_passing_score;
        $passed = is_null($min) ? null : ($score >= (float) $min);

        return response()->json([
            'ok'   => true,
            'data' => [
                'attempt_id' => $attempt->id,

                // ✅ NUEVO: modo del simulador en resultado
                'simulator_mode' => $attempt->simulator?->mode ?? Simulator::MODE_EXAM,

                'total_questions' => $total,
                'correct_count'   => $correct,
                'score'           => $score,
                'min_passing_score' => $min,
                'passed'          => $passed,
                'finished_at'     => optional($attempt->finished_at)->toIso8601String(),
            ],
        ]);
    }
}