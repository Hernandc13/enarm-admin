<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

public function reset(Request $request)
{
    $validator = Validator::make(
        $request->all(),
        [
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ],
        [
            'token.required' => 'El token de restablecimiento es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.string' => 'La nueva contraseña debe ser texto válido.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]
    );

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->setRememberToken(Str::random(60));

            $user->save();

            event(new PasswordReset($user));
        }
    );

    if ($status === Password::PASSWORD_RESET) {
        return redirect()->route('password.reset.success');
    }

    return back()->withErrors([
        'email' => match ($status) {
            Password::INVALID_TOKEN => 'El enlace ya no es válido o expiró.',
            Password::INVALID_USER => 'No encontramos un usuario con ese correo.',
            default => 'No se pudo restablecer la contraseña.',
        }
    ])->withInput();
}

    public function success()
    {
        return view('auth.reset-password-success');
    }
}