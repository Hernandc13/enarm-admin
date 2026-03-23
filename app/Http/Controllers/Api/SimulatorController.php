<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SimulatorController extends Controller
{
    public function index(Request $request)
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

        $now = Carbon::now();

        $query = Simulator::query()
            ->with([
                'category:id,name,description,is_active,sort_order',
            ])
            ->withCount('questions')
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
            });

        // Filtro opcional: ?mode=study|exam
        $mode = strtolower(trim((string) $request->query('mode', '')));
        if (in_array($mode, [Simulator::MODE_STUDY, Simulator::MODE_EXAM], true)) {
            $query->where('mode', $mode);
        }

        // Filtro opcional por category_id
        $categoryId = (int) $request->query('category_id', 0);
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        // Filtro opcional por nombre de categoría: ?category=cardiologia
        $categoryName = trim((string) $request->query('category', ''));
        if ($categoryName !== '') {
            $query->whereHas('category', function ($q) use ($categoryName) {
                $q->where('name', 'like', '%' . $categoryName . '%');
            });
        }

        $simulators = $query
            ->orderByRaw('CASE WHEN category_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('category_id')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Simulator $s) {
                return [
                    'id' => (int) $s->id,
                    'category_id' => (int) $s->category_id,
                    'category' => $s->category ? [
                        'id' => (int) $s->category->id,
                        'name' => (string) $s->category->name,
                        'description' => $s->category->description,
                        'sort_order' => (int) $s->category->sort_order,
                    ] : null,
                    'name' => $s->name,
                    'description' => $s->description,
                    'num_questions' => (int) ($s->questions_count ?? 0),
                    'available_from' => optional($s->available_from)->toIso8601String(),
                    'available_until' => optional($s->available_until)->toIso8601String(),
                    'shuffle_questions' => (bool) $s->shuffle_questions,
                    'shuffle_options' => (bool) $s->shuffle_options,
                    'max_attempts' => $s->max_attempts,
                    'time_limit_seconds' => $s->time_limit_seconds,
                    'min_passing_score' => $s->min_passing_score,
                    'mode' => $s->mode ?: Simulator::MODE_EXAM,
                ];
            })
            ->values();

        // Opcional: regresar agrupado por categorías con ?group_by_category=1
        $groupByCategory = filter_var($request->query('group_by_category', false), FILTER_VALIDATE_BOOL);

        if ($groupByCategory) {
            $grouped = $simulators
                ->groupBy('category_id')
                ->map(function ($items) {
                    $first = $items->first();

                    return [
                        'category' => $first['category'],
                        'simulators' => $items->values(),
                    ];
                })
                ->values();

            return response()->json([
                'ok' => true,
                'data' => $grouped,
            ]);
        }

        return response()->json([
            'ok' => true,
            'data' => $simulators,
        ]);
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

        $now = Carbon::now();

        $simulator->load([
            'category:id,name,description,is_active,sort_order',
        ])->loadCount('questions');

        if (
            !(bool) $simulator->is_published ||
            !$simulator->category ||
            !(bool) $simulator->category->is_active ||
            (!is_null($simulator->available_from) && $simulator->available_from->gt($now)) ||
            (!is_null($simulator->available_until) && $simulator->available_until->lt($now))
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Simulador no disponible.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => (int) $simulator->id,
                'category_id' => (int) $simulator->category_id,
                'category' => $simulator->category ? [
                    'id' => (int) $simulator->category->id,
                    'name' => (string) $simulator->category->name,
                    'description' => $simulator->category->description,
                    'sort_order' => (int) $simulator->category->sort_order,
                ] : null,
                'name' => $simulator->name,
                'description' => $simulator->description,
                'num_questions' => (int) $simulator->questions_count,
                'shuffle_questions' => (bool) $simulator->shuffle_questions,
                'shuffle_options' => (bool) $simulator->shuffle_options,
                'max_attempts' => $simulator->max_attempts,
                'time_limit_seconds' => $simulator->time_limit_seconds,
                'min_passing_score' => $simulator->min_passing_score,
                'mode' => $simulator->mode ?: Simulator::MODE_EXAM,
                'available_from' => optional($simulator->available_from)->toIso8601String(),
                'available_until' => optional($simulator->available_until)->toIso8601String(),
            ],
        ]);
    }
}