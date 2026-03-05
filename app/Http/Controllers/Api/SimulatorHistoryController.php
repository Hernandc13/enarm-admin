<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulator;
use App\Models\SimulatorAttempt;
use App\Models\UserSimulatorStat;
use Illuminate\Http\Request;

class SimulatorHistoryController extends Controller
{
    public function show(Request $request, Simulator $simulator)
    {
        $user = $request->user();

        $attempts = SimulatorAttempt::query()
            ->where('user_id', $user->id)
            ->where('simulator_id', $simulator->id)
            ->whereIn('status', ['finished', 'expired'])
            ->orderByDesc('finished_at')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'attempt_id' => $a->id,
                'status' => $a->status,
                'score' => (int)($a->score ?? 0),
                'correct_count' => (int)($a->correct_count ?? 0),
                'total_questions' => (int)($a->total_questions ?? 0),
                'started_at' => optional($a->started_at)->toIso8601String(),
                'finished_at' => optional($a->finished_at)->toIso8601String(),
            ]);

        $stat = UserSimulatorStat::query()
            ->where('user_id', $user->id)
            ->where('simulator_id', $simulator->id)
            ->first();

        return response()->json([
            'ok' => true,
            'data' => [
                'simulator' => [
                    'id' => $simulator->id,
                    'name' => $simulator->name,
                    'mode' => $simulator->mode,
                ],
                'record' => [
                    'attempts_count' => (int)($stat->attempts_count ?? 0),
                    'avg_score' => (float)($stat->avg_score ?? 0),
                    'best_score' => (int)($stat->best_score ?? 0),
                    'last_score' => (int)($stat->last_score ?? 0),
                    'last_attempt_at' => optional($stat?->last_attempt_at)->toIso8601String(),
                ],
                'attempts' => $attempts,
            ],
        ]);
    }
}