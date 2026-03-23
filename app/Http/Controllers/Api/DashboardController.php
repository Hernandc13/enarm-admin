<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulator;
use App\Models\UserActivityStat;
use App\Models\UserSimulatorStat;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function normalizeScore($value, int $decimals = 2): float
    {
        $score = (float) ($value ?? 0);

        // Si por alguna razón viene como fracción (ej. 0.8), lo convertimos a 80
        if ($score > 0 && $score <= 1) {
            $score = $score * 100;
        }

        // Blindaje por rango
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

    public function me(Request $request)
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

        $activity = UserActivityStat::firstOrNew([
            'user_id' => $user->id,
        ]);

        // Promedio general real en escala 0 a 100
        $overall = UserSimulatorStat::query()
            ->where('user_id', $user->id)
            ->selectRaw('SUM(sum_scores) as sum_scores, SUM(attempts_count) as attempts_count')
            ->first();

        $overallAvg = 0;
        if ($overall && (int) $overall->attempts_count > 0) {
            $overallAvg = $this->normalizeScore(
                ((float) $overall->sum_scores) / ((int) $overall->attempts_count)
            );
        }

        // Promedio EXAM (para ranking) en escala 0 a 100
        $overallExam = DB::table('user_simulator_stats as uss')
            ->join('simulators as s', 's.id', '=', 'uss.simulator_id')
            ->where('uss.user_id', $user->id)
            ->where('s.mode', Simulator::MODE_EXAM)
            ->selectRaw('SUM(uss.sum_scores) as sum_scores, SUM(uss.attempts_count) as attempts_count')
            ->first();

        $examAvg = 0;
        if ($overallExam && (int) $overallExam->attempts_count > 0) {
            $examAvg = $this->normalizeScore(
                ((float) $overallExam->sum_scores) / ((int) $overallExam->attempts_count)
            );
        }

        // Lugar en ranking
        $myRank = null;
        if ($overallExam && (int) $overallExam->attempts_count > 0) {
            $better = DB::table(DB::raw("(
                SELECT uss.user_id,
                       (SUM(uss.sum_scores) / NULLIF(SUM(uss.attempts_count),0)) as avg_score
                FROM user_simulator_stats uss
                JOIN simulators s ON s.id = uss.simulator_id
                WHERE s.mode = '" . Simulator::MODE_EXAM . "'
                GROUP BY uss.user_id
            ) t"))
                ->where('t.avg_score', '>', (float) $examAvg)
                ->count();

            $myRank = (int) $better + 1;
        }

        $now = Carbon::now();

        // Total de simuladores disponibles/publicados con categoría activa
        $totalSimulators = Simulator::query()
            ->where('is_published', true)
            ->whereNotNull('category_id')
            ->whereHas('category', function ($q) {
                $q->where('is_active', true);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('available_from')
                    ->orWhere('available_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('available_until')
                    ->orWhere('available_until', '>=', $now);
            })
            ->count();

        // Cuántos de esos simuladores ya fueron intentados por el usuario
        $attemptedSimulators = UserSimulatorStat::query()
            ->where('user_id', $user->id)
            ->where('attempts_count', '>', 0)
            ->whereHas('simulator', function ($q) use ($now) {
                $q->where('is_published', true)
                    ->whereNotNull('category_id')
                    ->whereHas('category', function ($qc) {
                        $qc->where('is_active', true);
                    })
                    ->where(function ($qq) use ($now) {
                        $qq->whereNull('available_from')
                            ->orWhere('available_from', '<=', $now);
                    })
                    ->where(function ($qq) use ($now) {
                        $qq->whereNull('available_until')
                            ->orWhere('available_until', '>=', $now);
                    });
            })
            ->count();

        // Stats por simulador con categoría
        $perSim = UserSimulatorStat::query()
            ->with([
                'simulator:id,category_id,name,mode,is_published,available_from,available_until',
                'simulator.category:id,name,description,is_active,sort_order',
            ])
            ->where('user_id', $user->id)
            ->whereHas('simulator', function ($q) use ($now) {
                $q->where('is_published', true)
                    ->whereNotNull('category_id')
                    ->whereHas('category', function ($qc) {
                        $qc->where('is_active', true);
                    })
                    ->where(function ($qq) use ($now) {
                        $qq->whereNull('available_from')
                            ->orWhere('available_from', '<=', $now);
                    })
                    ->where(function ($qq) use ($now) {
                        $qq->whereNull('available_until')
                            ->orWhere('available_until', '>=', $now);
                    });
            })
            ->orderByDesc('last_attempt_at')
            ->get()
            ->map(function ($s) {
                $sim = $s->simulator;
                $cat = $sim?->category;

                return [
                    'simulator_id'    => (int) $s->simulator_id,
                    'simulator_name'  => $sim?->name,
                    'mode'            => $sim?->mode,
                    'attempts_count'  => (int) $s->attempts_count,
                    'avg_score'       => $this->normalizeScore($s->avg_score),
                    'best_score'      => $this->normalizeScoreInt($s->best_score),
                    'last_score'      => $this->normalizeScoreInt($s->last_score),
                    'last_attempt_at' => optional($s->last_attempt_at)->toIso8601String(),

                    'category_id'     => $sim?->category_id,
                    'category_name'   => $cat?->name,
                    'category'        => $cat ? [
                        'id'          => (int) $cat->id,
                        'name'        => (string) $cat->name,
                        'description' => $cat->description,
                        'sort_order'  => (int) $cat->sort_order,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'first_login'          => optional($activity->first_login_at)->toIso8601String(),
                'last_login'           => optional($activity->last_login_at)->toIso8601String(),
                'overall_avg'          => $this->normalizeScore($overallAvg),
                'exam_avg'             => $this->normalizeScore($examAvg),
                'my_rank'              => $myRank,
                'attempted_simulators' => (int) $attemptedSimulators,
                'total_simulators'     => (int) $totalSimulators,
                'simulators'           => $perSim,
            ],
        ]);
    }
}