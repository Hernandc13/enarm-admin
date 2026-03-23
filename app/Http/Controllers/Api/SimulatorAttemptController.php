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

        if (!(bool) $user->has_app_access || !empty($user->revoked_at)) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'ok' => false,
                'reason' => 'NO_APP_ACCESS',
                'message' => 'Tu acceso a la app está desactivado.',
            ], 403);
        }

        $simulator->load('category:id,name,description,is_active,sort_order');

        if (
            !(bool) $simulator->is_published ||
            !$simulator->isAvailableNow() ||
            !$simulator->category ||
            !(bool) $simulator->category->is_active
        ) {
            return response()->json([
                'ok'      => false,
                'message' => 'Simulador no disponible.',
            ], 404);
        }

        if (!is_null($simulator->max_attempts)) {
            $count = SimulatorAttempt::where('user_id', $user->id)
                ->where('simulator_id', $simulator->id)
                ->whereIn('status', ['in_progress', 'finished', 'expired'])
                ->count();

            if ($count >= (int) $simulator->max_attempts) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Has alcanzado el máximo de intentos para este simulador.',
                ], 403);
            }
        }

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
                    'attempt_id'  => $attempt->id,
                    'question_id' => $qid,
                    'position'    => $idx + 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            SimulatorAttemptQuestion::insert($rows);

            return $attempt;
        });

        return response()->json([
            'ok'   => true,
            'data' => [
                'attempt_id'      => (int) $attempt->id,
                'simulator_id'    => (int) $simulator->id,
                'category_id'     => (int) $simulator->category_id,
                'category'        => $simulator->category ? [
                    'id' => (int) $simulator->category->id,
                    'name' => (string) $simulator->category->name,
                    'description' => $simulator->category->description,
                    'sort_order' => (int) $simulator->category->sort_order,
                ] : null,
                'mode'            => $simulator->mode ?? Simulator::MODE_EXAM,
                'started_at'      => optional($attempt->started_at)->toIso8601String(),
                'expires_at'      => optional($attempt->expires_at)->toIso8601String(),
                'total_questions' => (int) $attempt->total_questions,
            ],
        ]);
    }

    public function questions(Request $request, SimulatorAttempt $attempt)
    {
        $user = $request->user();

        if (!(bool) $user->has_app_access || !empty($user->revoked_at)) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'ok' => false,
                'reason' => 'NO_APP_ACCESS',
                'message' => 'Tu acceso a la app está desactivado.',
            ], 403);
        }

        if ((int) $attempt->user_id !== (int) $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado.',
            ], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'ok' => false,
                'message' => 'El intento no está activo.',
            ], 422);
        }

        if (!is_null($attempt->expires_at) && now()->greaterThan($attempt->expires_at)) {
            $attempt->update([
                'status' => 'expired',
                'finished_at' => now(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Tiempo agotado.',
            ], 410);
        }

        $limit = (int) ($request->query('limit', 10));
        $limit = max(1, min(25, $limit));

        $attempt->load('simulator.category');

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
                'simulator_mode' => $attempt->simulator?->mode ?? Simulator::MODE_EXAM,
                'category_id'    => (int) ($attempt->simulator?->category_id ?? 0),
                'category_name'  => (string) ($attempt->simulator?->category?->name ?? ''),
            ],
        ]);
    }

    public function answer(Request $request, SimulatorAttempt $attempt)
    {
        $user = $request->user();

        if (!(bool) $user->has_app_access || !empty($user->revoked_at)) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'ok' => false,
                'reason' => 'NO_APP_ACCESS',
                'message' => 'Tu acceso a la app está desactivado.',
            ], 403);
        }

        if ((int) $attempt->user_id !== (int) $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado.',
            ], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'ok' => false,
                'message' => 'El intento no está activo.',
            ], 422);
        }

        if (!is_null($attempt->expires_at) && now()->greaterThan($attempt->expires_at)) {
            $attempt->update([
                'status' => 'expired',
                'finished_at' => now(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Tiempo agotado.',
            ], 410);
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
            return response()->json([
                'ok' => false,
                'message' => 'Pregunta inválida para este intento.',
            ], 422);
        }

        $isCorrect = false;
        $question = Question::findOrFail($data['question_id']);

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

    public function abandon(Request $request, SimulatorAttempt $attempt)
    {
        $user = $request->user();

        if (!(bool) $user->has_app_access || !empty($user->revoked_at)) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'ok' => false,
                'reason' => 'NO_APP_ACCESS',
                'message' => 'Tu acceso a la app está desactivado.',
            ], 403);
        }

        if ((int) $attempt->user_id !== (int) $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado.',
            ], 403);
        }

        if (in_array($attempt->status, ['finished', 'abandoned'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'El intento ya fue cerrado.',
            ], 422);
        }

        $attempt->update([
            'status' => 'abandoned',
            'finished_at' => now(),
        ]);

        return response()->json([
            'ok'   => true,
            'data' => [
                'attempt_id' => (int) $attempt->id,
                'status'     => 'abandoned',
                'finished_at'=> optional($attempt->finished_at)->toIso8601String(),
            ],
            'message' => 'Intento abandonado correctamente.',
        ]);
    }

    public function finish(Request $request, SimulatorAttempt $attempt)
    {
        $user = $request->user();

        if (!(bool) $user->has_app_access || !empty($user->revoked_at)) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'ok' => false,
                'reason' => 'NO_APP_ACCESS',
                'message' => 'Tu acceso a la app está desactivado.',
            ], 403);
        }

        if ((int) $attempt->user_id !== (int) $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado.',
            ], 403);
        }

        if (!in_array($attempt->status, ['in_progress', 'expired'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'El intento ya fue cerrado.',
            ], 422);
        }

        $attempt->load('simulator.category');

        $timedOut = (!is_null($attempt->expires_at) && now()->greaterThan($attempt->expires_at));

        if ($timedOut && $attempt->status === 'in_progress') {
            $attempt->status = 'expired';
        }

        $answeredIds = $attempt->answers()->pluck('question_id')->all();

        $missing = $attempt->attemptQuestions()
            ->whereNotIn('question_id', $answeredIds)
            ->pluck('question_id')
            ->all();

        if (!empty($missing)) {
            $rows = [];
            foreach ($missing as $qid) {
                $rows[] = [
                    'attempt_id'         => $attempt->id,
                    'question_id'        => (int) $qid,
                    'selected_option_id' => null,
                    'selected_text'      => null,
                    'is_correct'         => false,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            }
            SimulatorAttemptAnswer::insert($rows);
        }

        $total   = (int) $attempt->total_questions;
        $correct = (int) $attempt->answers()->where('is_correct', true)->count();
        $score   = $total > 0 ? (int) round(($correct / $total) * 100) : 0;

        $attempt->update([
            'correct_count' => $correct,
            'score'         => $score,
            'finished_at'   => now(),
            'status'        => 'finished',
        ]);

        (new SimulatorStatsService())->recordFinishedAttempt($attempt->fresh());

        $min    = $attempt->simulator?->min_passing_score;
        $passed = is_null($min) ? null : ($score >= (float) $min);

        return response()->json([
            'ok'   => true,
            'data' => [
                'attempt_id'        => (int) $attempt->id,
                'simulator_id'      => (int) $attempt->simulator_id,
                'category_id'       => (int) ($attempt->simulator?->category_id ?? 0),
                'category_name'     => (string) ($attempt->simulator?->category?->name ?? ''),
                'simulator_mode'    => $attempt->simulator?->mode ?? Simulator::MODE_EXAM,
                'total_questions'   => $total,
                'correct_count'     => $correct,
                'score'             => $score,
                'min_passing_score' => $min,
                'passed'            => $passed,
                'finished_at'       => optional($attempt->finished_at)->toIso8601String(),
                'timed_out'         => $timedOut,
            ],
        ]);
    }
}