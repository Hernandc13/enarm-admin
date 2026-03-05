<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login para usuarios APP-only (is_from_moodle = 0).
     * - Si el usuario es Moodle, responde 409 MOODLE_USER para que Flutter use Moodle token.php.
     * - Si no tiene acceso a app, responde 403 NO_APP_ACCESS.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        // ✅ Si es usuario Moodle, NO se autentica aquí
        if ((bool) $user->is_from_moodle) {
            return response()->json([
                'ok' => false,
                'reason' => 'MOODLE_USER',
                'message' => 'Este usuario debe iniciar sesión con Moodle.',
            ], 409);
        }

        // ✅ Validar acceso a app
        if (!(bool) $user->has_app_access) {
            return response()->json([
                'ok' => false,
                'reason' => 'NO_APP_ACCESS',
                'message' => 'Tu acceso a la app está desactivado.',
            ], 403);
        }

        // ✅ Si tienes revoked_at, bloquea
        if (!empty($user->revoked_at)) {
            return response()->json([
                'ok' => false,
                'reason' => 'REVOKED',
                'message' => 'Tu acceso a la app fue revocado.',
            ], 403);
        }

        // ✅ Password check
        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        $device = $data['device_name'] ?: 'flutter';

        // (Opcional) eliminar tokens previos del mismo device
        // $user->tokens()->where('name', $device)->delete();

        $token = $user->createToken($device)->plainTextToken;

        // ✅ REGISTRAR primer/último ingreso
        $this->touchLoginStats($user->id);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->fullname,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'is_from_moodle' => (bool) $user->is_from_moodle,
                'has_app_access' => (bool) $user->has_app_access,
            ],
        ]);
    }

    /**
     * Validación de acceso a la app por email (sirve para usuarios Moodle).
     * IMPORTANTE: emite token Sanctum para consumir /api/*
     */
    public function checkAccess(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'reason' => 'NOT_FOUND',
                'message' => 'Usuario no registrado en el panel.',
            ], 404);
        }

        if (!(bool) $user->has_app_access) {
            return response()->json([
                'ok' => false,
                'reason' => 'NO_APP_ACCESS',
                'message' => 'Tu acceso a la app está desactivado.',
            ], 403);
        }

        if (!empty($user->revoked_at)) {
            return response()->json([
                'ok' => false,
                'reason' => 'REVOKED',
                'message' => 'Tu acceso a la app fue revocado.',
            ], 403);
        }

        $device = $data['device_name'] ?: 'flutter_moodle';
        $token = $user->createToken($device)->plainTextToken;

        // ✅ REGISTRAR primer/último ingreso (para usuarios Moodle también)
        $this->touchLoginStats($user->id);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->fullname,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'is_from_moodle' => (bool) $user->is_from_moodle,
                'has_app_access' => (bool) $user->has_app_access,
            ],
        ]);
    }

    /**
     * Retorna el usuario autenticado por token Sanctum.
     * (También refresca last_login_at para “último ingreso”)
     */
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

        // ✅ refrescar “último ingreso”
        $this->touchLoginStats($user->id);

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->fullname,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'is_from_moodle' => (bool) $user->is_from_moodle,
                'has_app_access' => (bool) $user->has_app_access,
            ],
        ]);
    }

    /**
     * Cierra sesión (borra el token actual).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    // ----------------------------------------------------
    // Helper: primer/último ingreso
    // ----------------------------------------------------
    private function touchLoginStats(int $userId): void
    {
        $stat = UserActivityStat::firstOrNew(['user_id' => $userId]);

        if (!$stat->first_login_at) {
            $stat->first_login_at = now();
        }

        $stat->last_login_at = now();
        $stat->save();
    }
}