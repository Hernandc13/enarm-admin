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

        if (!(bool)$user->has_app_access || !empty($user->revoked_at)) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'ok' => false,
                'reason' => 'NO_APP_ACCESS',
                'message' => 'Tu acceso a la app está desactivado.',
            ], 403);
        }

        $now = Carbon::now();

        $query = Simulator::query()
            ->withCount('questions')
            ->where('is_published', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('available_from')->orWhere('available_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('available_until')->orWhere('available_until', '>=', $now);
            });

        // Filtro opcional: ?mode=study|exam
        $mode = strtolower(trim((string)$request->query('mode', '')));
        if (in_array($mode, ['study', 'exam'], true)) {
            $query->where('mode', $mode);
        }

        $simulators = $query
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Simulator $s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'description' => $s->description,
                    'num_questions' => (int)($s->questions_count ?? 0),
                    'available_from' => optional($s->available_from)->toIso8601String(),
                    'available_until' => optional($s->available_until)->toIso8601String(),
                    'shuffle_questions' => (bool)$s->shuffle_questions,
                    'shuffle_options' => (bool)$s->shuffle_options,
                    'max_attempts' => $s->max_attempts,
                    'time_limit_seconds' => $s->time_limit_seconds,
                    'min_passing_score' => $s->min_passing_score,
                    'mode' => $s->mode ?? (defined(Simulator::class . '::MODE_EXAM') ? Simulator::MODE_EXAM : 'exam'),
                ];
            });

        return response()->json([
            'ok' => true,
            'data' => $simulators,
        ]);
    }

    public function show(Request $request, Simulator $simulator)
    {
        $now = Carbon::now();

        if (
            !(bool)$simulator->is_published ||
            (!is_null($simulator->available_from) && $simulator->available_from->gt($now)) ||
            (!is_null($simulator->available_until) && $simulator->available_until->lt($now))
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Simulador no disponible.',
            ], 404);
        }

        $simulator->loadCount('questions');

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $simulator->id,
                'name' => $simulator->name,
                'description' => $simulator->description,
                'num_questions' => (int)$simulator->questions_count,
                'shuffle_questions' => (bool)$simulator->shuffle_questions,
                'shuffle_options' => (bool)$simulator->shuffle_options,
                'max_attempts' => $simulator->max_attempts,
                'time_limit_seconds' => $simulator->time_limit_seconds,
                'min_passing_score' => $simulator->min_passing_score,
                'mode' => $simulator->mode ?? (defined(Simulator::class . '::MODE_EXAM') ? Simulator::MODE_EXAM : 'exam'),
            ],
        ]);
    }
}