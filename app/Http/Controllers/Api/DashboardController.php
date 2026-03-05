<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulator;
use App\Models\UserActivityStat;
use App\Models\UserSimulatorStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        $activity = UserActivityStat::firstOrNew(['user_id' => $user->id]);

        // Promedio general
        $overall = UserSimulatorStat::query()
            ->where('user_id', $user->id)
            ->selectRaw('SUM(sum_scores) as sum_scores, SUM(attempts_count) as attempts_count')
            ->first();

        $overallAvg = 0;
        if ($overall && (int)$overall->attempts_count > 0) {
            $overallAvg = round(((int)$overall->sum_scores) / ((int)$overall->attempts_count), 2);
        }

        // Promedio EXAM (para ranking)
        $overallExam = DB::table('user_simulator_stats as uss')
            ->join('simulators as s', 's.id', '=', 'uss.simulator_id')
            ->where('uss.user_id', $user->id)
            ->where('s.mode', Simulator::MODE_EXAM)
            ->selectRaw('SUM(uss.sum_scores) as sum_scores, SUM(uss.attempts_count) as attempts_count')
            ->first();

        $examAvg = 0;
        if ($overallExam && (int)$overallExam->attempts_count > 0) {
            $examAvg = round(((int)$overallExam->sum_scores) / ((int)$overallExam->attempts_count), 2);
        }

        // Lugar en ranking
        $myRank = null;
        if ($overallExam && (int)$overallExam->attempts_count > 0) {
            $better = DB::table(DB::raw("(
                SELECT uss.user_id,
                       (SUM(uss.sum_scores) / NULLIF(SUM(uss.attempts_count),0)) as avg_score
                FROM user_simulator_stats uss
                JOIN simulators s ON s.id = uss.simulator_id
                WHERE s.mode = '".Simulator::MODE_EXAM."'
                GROUP BY uss.user_id
            ) t"))
            ->where('t.avg_score', '>', (float)$examAvg)
            ->count();

            $myRank = (int)$better + 1;
        }

        // Stats por simulador
        $perSim = UserSimulatorStat::query()
            ->with(['simulator:id,name,mode'])
            ->where('user_id', $user->id)
            ->orderByDesc('last_attempt_at')
            ->get()
            ->map(fn($s) => [
                'simulator_id' => (int)$s->simulator_id,
                'simulator_name' => $s->simulator?->name,
                'mode' => $s->simulator?->mode,
                'attempts_count' => (int)$s->attempts_count,
                'avg_score' => (float)$s->avg_score,
                'best_score' => (int)$s->best_score,
                'last_score' => (int)$s->last_score,
                'last_attempt_at' => optional($s->last_attempt_at)->toIso8601String(),
            ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'first_login' => optional($activity->first_login_at)->toIso8601String(),
                'last_login' => optional($activity->last_login_at)->toIso8601String(),
                'overall_avg' => (float)$overallAvg,
                'exam_avg' => (float)$examAvg,
                'my_rank' => $myRank,
                'simulators' => $perSim,
            ],
        ]);
    }
}