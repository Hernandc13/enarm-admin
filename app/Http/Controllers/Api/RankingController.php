<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(5, min(200, $limit));

        $validSimulators = Simulator::query()
            ->where('mode', 'exam')
            ->has('questions', '=', 280)
            ->select('id');

        /**
         * Ranking general por promedio.
         */
        $rows = DB::table('user_simulator_stats as uss')
            ->joinSub($validSimulators, 'vs', function ($join) {
                $join->on('vs.id', '=', 'uss.simulator_id');
            })
            ->join('users as u', 'u.id', '=', 'uss.user_id')
            ->groupBy('uss.user_id', 'u.name', 'u.last_name')
            ->havingRaw('SUM(uss.attempts_count) > 0')
            ->selectRaw("
                uss.user_id,
                TRIM(CONCAT(COALESCE(u.name,''), ' ', COALESCE(u.last_name,''))) as user_name,
                SUM(uss.sum_scores) as sum_scores,
                SUM(uss.attempts_count) as attempts_count,
                ROUND(SUM(uss.sum_scores) / NULLIF(SUM(uss.attempts_count), 0), 2) as avg_score
            ")
            ->orderByDesc('avg_score')
            ->orderByDesc('attempts_count')
            ->orderBy('uss.user_id')
            ->limit($limit)
            ->get();

        /**
         * Mi ranking dentro del mismo universo.
         */
        $user = $request->user();
        $myRank = null;

        if ($user) {
            $me = DB::table('user_simulator_stats as uss')
                ->joinSub($validSimulators, 'vs', function ($join) {
                    $join->on('vs.id', '=', 'uss.simulator_id');
                })
                ->where('uss.user_id', $user->id)
                ->groupBy('uss.user_id')
                ->selectRaw("
                    uss.user_id,
                    SUM(uss.attempts_count) as attempts_count,
                    ROUND(SUM(uss.sum_scores) / NULLIF(SUM(uss.attempts_count), 0), 2) as avg_score
                ")
                ->first();

            if ($me && (int) $me->attempts_count > 0 && $me->avg_score !== null) {
                $allRanked = DB::table('user_simulator_stats as uss')
                    ->joinSub($validSimulators, 'vs', function ($join) {
                        $join->on('vs.id', '=', 'uss.simulator_id');
                    })
                    ->groupBy('uss.user_id')
                    ->havingRaw('SUM(uss.attempts_count) > 0')
                    ->selectRaw("
                        uss.user_id,
                        SUM(uss.attempts_count) as attempts_count,
                        ROUND(SUM(uss.sum_scores) / NULLIF(SUM(uss.attempts_count), 0), 2) as avg_score
                    ");

                $better = DB::query()
                    ->fromSub($allRanked, 't')
                    ->where(function ($q) use ($me, $user) {
                        $q->where('t.avg_score', '>', (float) $me->avg_score)
                            ->orWhere(function ($q2) use ($me) {
                                $q2->where('t.avg_score', '=', (float) $me->avg_score)
                                   ->where('t.attempts_count', '>', (int) $me->attempts_count);
                            })
                            ->orWhere(function ($q3) use ($me, $user) {
                                $q3->where('t.avg_score', '=', (float) $me->avg_score)
                                   ->where('t.attempts_count', '=', (int) $me->attempts_count)
                                   ->where('t.user_id', '<', (int) $user->id);
                            });
                    })
                    ->count();

                $myRank = $better + 1;
            }
        }

        return response()->json([
            'ok' => true,
            'data' => $rows,
            'meta' => [
                'my_rank' => $myRank,
                'ranking_type' => 'average_score',
                'ranking_label' => 'Promedio',
                'score_scale' => '0-100',
                'filters' => [
                    'mode' => 'exam',
                    'exact_questions' =>280,
                ],
            ],
        ]);
    }
}