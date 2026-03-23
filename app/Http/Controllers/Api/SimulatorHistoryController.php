<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulator;
use App\Models\SimulatorAttempt;
use App\Models\UserSimulatorStat;
use Illuminate\Http\Request;

class SimulatorHistoryController extends Controller
{
    private function normalizeScore($value, int $decimals = 2): float
    {
        $score = (float) ($value ?? 0);

        // Si por alguna razón viene como fracción (ej. 0.8), lo convertimos a 80
        if ($score > 0 && $score <= 1) {
            $score = $score * 100;
        }

        if ($score < 0) {
            $score = 0;
        }

        if ($score > 100) {
            $score = 100;
        }

        return round($score, $decimals);
    }

    private function normalizeScoreInt($value): int
    {
        return (int) round($this->normalizeScore($value, 0));
    }

    public function show(Request $request, Simulator $simulator)
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

        $simulator->load([
            'category:id,name,description,is_active,sort_order',
        ]);

        if (
            !(bool) $simulator->is_published ||
            !$simulator->category ||
            !(bool) $simulator->category->is_active
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Simulador no disponible.',
            ], 404);
        }

        $attempts = SimulatorAttempt::query()
            ->where('user_id', $user->id)
            ->where('simulator_id', $simulator->id)
            ->whereIn('status', ['finished', 'expired'])
            ->orderByDesc('finished_at')
            ->limit(50)
            ->get()
            ->map(function ($a) {
                return [
                    'attempt_id' => (int) $a->id,
                    'status' => $a->status,
                    'score' => $this->normalizeScoreInt($a->score ?? 0),
                    'correct_count' => (int) ($a->correct_count ?? 0),
                    'total_questions' => (int) ($a->total_questions ?? 0),
                    'started_at' => optional($a->started_at)->toIso8601String(),
                    'finished_at' => optional($a->finished_at)->toIso8601String(),
                ];
            })
            ->values();

        $stat = UserSimulatorStat::query()
            ->where('user_id', $user->id)
            ->where('simulator_id', $simulator->id)
            ->first();

        return response()->json([
            'ok' => true,
            'data' => [
                'simulator' => [
                    'id' => (int) $simulator->id,
                    'category_id' => (int) $simulator->category_id,
                    'category' => $simulator->category ? [
                        'id' => (int) $simulator->category->id,
                        'name' => (string) $simulator->category->name,
                        'description' => $simulator->category->description,
                        'sort_order' => (int) $simulator->category->sort_order,
                    ] : null,
                    'name' => $simulator->name,
                    'mode' => $simulator->mode ?: Simulator::MODE_EXAM,
                ],
                'record' => [
                    'attempts_count' => (int) ($stat->attempts_count ?? 0),
                    'avg_score' => $this->normalizeScore($stat->avg_score ?? 0),
                    'best_score' => $this->normalizeScoreInt($stat->best_score ?? 0),
                    'last_score' => $this->normalizeScoreInt($stat->last_score ?? 0),
                    'last_attempt_at' => optional($stat?->last_attempt_at)->toIso8601String(),
                ],
                'attempts' => $attempts,
            ],
        ]);
    }
}