<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Te enviamos un enlace para restablecer tu contraseña.',
            ], 200);
        }

        return response()->json([
            'message' => match ($status) {
                Password::INVALID_USER => 'No encontramos un usuario con ese correo.',
                Password::RESET_THROTTLED => 'Ya se solicitó recientemente. Intenta más tarde.',
                default => 'No se pudo enviar el enlace de recuperación.',
            },
        ], 400);
    }
}