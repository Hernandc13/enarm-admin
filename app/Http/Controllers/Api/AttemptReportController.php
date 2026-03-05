<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimulatorAttempt;
use Illuminate\Http\Request;

class AttemptReportController extends Controller
{
    public function show(Request $request, SimulatorAttempt $attempt)
    {
        $user = $request->user();

        if ((int)$attempt->user_id !== (int)$user->id) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        $attempt->load(['simulator', 'attemptQuestions.question', 'answers']);

        // Solo tiene sentido cuando ya terminó (o si lo quieres en progreso también)
        $answersByQid = $attempt->answers->keyBy('question_id');

        $items = $attempt->attemptQuestions
            ->sortBy('position')
            ->values()
            ->map(function ($row) use ($answersByQid) {
                $q = $row->question;
                $ans = $answersByQid->get($row->question_id);

                return [
                    'position' => (int)$row->position,
                    'question_id' => (int)$row->question_id,
                    'text' => (string)($q->stem ?? ''),
                    'answered' => $ans ? true : false,
                    'is_correct' => $ans ? (bool)$ans->is_correct : null,
                ];
            });

        $total = (int)($attempt->total_questions ?? $items->count());
        $correct = (int)($attempt->correct_count ?? $attempt->answers->where('is_correct', true)->count());
        $score = (int)($attempt->score ?? ($total > 0 ? round(($correct / $total) * 100) : 0));

        return response()->json([
            'ok' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'simulator_id' => $attempt->simulator_id,
                'simulator_name' => $attempt->simulator?->name,
                'mode' => $attempt->simulator?->mode,
                'status' => $attempt->status,
                'total_questions' => $total,
                'correct_count' => $correct,
                'incorrect_count' => max(0, $total - $correct),
                'score' => $score,
                'items' => $items,
            ],
        ]);
    }
}