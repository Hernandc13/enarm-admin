<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(5, min(200, $limit));

        // ✅ IMPORTANTE: usa 'exam' literal para evitar mismatch con constantes.
        $rows = DB::table('user_simulator_stats as uss')
            ->join('simulators as s', 's.id', '=', 'uss.simulator_id')
            ->join('users as u', 'u.id', '=', 'uss.user_id')
            ->where('s.mode', 'exam')
            ->groupBy('uss.user_id', 'u.name', 'u.last_name')
            ->havingRaw('SUM(uss.attempts_count) > 0')
            ->selectRaw("
                uss.user_id,
                TRIM(CONCAT(COALESCE(u.name,''),' ',COALESCE(u.last_name,''))) as user_name,
                SUM(uss.sum_scores) as sum_scores,
                SUM(uss.attempts_count) as attempts_count,
                CAST((SUM(uss.sum_scores) / NULLIF(SUM(uss.attempts_count), 0)) AS DECIMAL(10,2)) as avg_score
            ")
            ->orderByDesc('avg_score')
            ->orderByDesc('attempts_count')
            ->limit($limit)
            ->get();

        // ✅ Tu lugar (mismo universo: solo usuarios con intentos EXAM)
        $user = $request->user();
        $myRank = null;

        if ($user) {
            $me = DB::table('user_simulator_stats as uss')
                ->join('simulators as s', 's.id', '=', 'uss.simulator_id')
                ->where('s.mode', 'exam')
                ->where('uss.user_id', $user->id)
                ->selectRaw("
                    SUM(uss.attempts_count) as attempts_count,
                    (SUM(uss.sum_scores) / NULLIF(SUM(uss.attempts_count), 0)) as avg_score
                ")
                ->first();

            if ($me && (int)$me->attempts_count > 0 && $me->avg_score !== null) {
                $better = DB::table(DB::raw("(
                    SELECT
                        uss.user_id,
                        SUM(uss.attempts_count) as attempts_count,
                        (SUM(uss.sum_scores) / NULLIF(SUM(uss.attempts_count),0)) as avg_score
                    FROM user_simulator_stats uss
                    JOIN simulators s ON s.id = uss.simulator_id
                    WHERE s.mode = 'exam'
                    GROUP BY uss.user_id
                    HAVING SUM(uss.attempts_count) > 0
                ) t"))
                ->where('t.avg_score', '>', (float)$me->avg_score)
                ->count();

                $myRank = (int)$better + 1;
            }
        }

        return response()->json([
            'ok' => true,
            'data' => $rows,
            'meta' => [
                'my_rank' => $myRank,
            ],
        ]);
    }
}