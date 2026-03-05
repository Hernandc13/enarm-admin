<?php

namespace App\Services;

use App\Models\SimulatorAttempt;
use App\Models\UserSimulatorStat;

class SimulatorStatsService
{
    public function recordFinishedAttempt(SimulatorAttempt $attempt): void
    {
        // Solo intentos finalizados con score
        if ($attempt->status !== 'finished') {
            return;
        }

        $attempt->loadMissing('simulator');

        $userId = (int) $attempt->user_id;
        $simId  = (int) $attempt->simulator_id;
        $score  = (int) ($attempt->score ?? 0);

        $stat = UserSimulatorStat::firstOrNew([
            'user_id' => $userId,
            'simulator_id' => $simId,
        ]);

        // Defaults si es nuevo
        $stat->attempts_count = (int) ($stat->attempts_count ?? 0);
        $stat->sum_scores     = (int) ($stat->sum_scores ?? 0);
        $stat->best_score     = (int) ($stat->best_score ?? 0);

        // Update
        $stat->attempts_count += 1;
        $stat->sum_scores     += $score;
        $stat->last_score      = $score;
        $stat->best_score      = max($stat->best_score, $score);
        $stat->avg_score       = $stat->attempts_count > 0
            ? round($stat->sum_scores / $stat->attempts_count, 2)
            : 0;

        $stat->last_attempt_at = $attempt->finished_at ?? now();
        $stat->save();
    }
}