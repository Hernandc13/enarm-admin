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

        if ((int) $attempt->user_id !== (int) $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado.',
            ], 403);
        }

        $attempt->load([
            'simulator.category:id,name,description,is_active,sort_order',
            'attemptQuestions.question.options',
            'answers',
        ]);

        $answersByQid = $attempt->answers->keyBy('question_id');

        $items = $attempt->attemptQuestions
            ->sortBy('position')
            ->values()
            ->map(function ($row) use ($answersByQid) {
                $q = $row->question;
                $ans = $answersByQid->get($row->question_id);

                $options = collect($q?->options ?? [])->sortBy('order')->values();

                $selectedOption = null;
                $correctOption = null;

                if ($ans && !is_null($ans->selected_option_id)) {
                    $selectedOption = $options->firstWhere('id', (int) $ans->selected_option_id);
                }

                $correctOption = $options->first(function ($opt) {
                    return (bool) ($opt->is_correct ?? false) === true;
                });

                return [
                    'position' => (int) $row->position,
                    'question_id' => (int) $row->question_id,

                    'question' => [
                        'text' => (string) ($q->stem ?? ''),
                        'general_feedback' => (string) ($q->general_feedback ?? ''),
                    ],

                    'answered' => $ans ? true : false,
                    'is_correct' => $ans ? (bool) $ans->is_correct : null,

                    'user_answer' => [
                        'selected_option_id' => $ans?->selected_option_id ? (int) $ans->selected_option_id : null,
                        'text' => $selectedOption
                            ? (string) ($selectedOption->text ?? '')
                            : (string) ($ans->selected_text ?? ''),
                    ],

                    'correct_answer' => [
                        'option_id' => $correctOption?->id ? (int) $correctOption->id : null,
                        'text' => (string) ($correctOption->text ?? ''),
                    ],
                ];
            });

        $total = (int) ($attempt->total_questions ?? $items->count());
        $correct = (int) ($attempt->correct_count ?? $attempt->answers->where('is_correct', true)->count());
        $score = (int) ($attempt->score ?? ($total > 0 ? round(($correct / $total) * 100) : 0));

        return response()->json([
            'ok' => true,
            'data' => [
                'attempt_id' => (int) $attempt->id,
                'simulator_id' => (int) $attempt->simulator_id,
                'simulator_name' => $attempt->simulator?->name,
                'mode' => $attempt->simulator?->mode,
                'status' => $attempt->status,
                'total_questions' => $total,
                'correct_count' => $correct,
                'incorrect_count' => max(0, $total - $correct),
                'score' => $score,

                'category_id' => (int) ($attempt->simulator?->category_id ?? 0),
                'category_name' => (string) ($attempt->simulator?->category?->name ?? ''),
                'category' => $attempt->simulator?->category ? [
                    'id' => (int) $attempt->simulator->category->id,
                    'name' => (string) $attempt->simulator->category->name,
                    'description' => $attempt->simulator->category->description,
                    'sort_order' => (int) $attempt->simulator->category->sort_order,
                ] : null,

                'items' => $items,
            ],
        ]);
    }
}