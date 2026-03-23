<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'origin_university' => ['required', 'string', 'max:255'],
                'origin_municipality' => ['required', 'string', 'max:255'],
                'desired_specialty' => ['required', 'string', 'max:255'],
                'whatsapp_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-\(\)]{7,30}$/'],
            ],
            [
                'name.required' => 'El nombre es obligatorio.',
                'name.string' => 'El nombre debe ser texto válido.',
                'name.max' => 'El nombre no debe exceder 255 caracteres.',

                'last_name.required' => 'Los apellidos son obligatorios.',
                'last_name.string' => 'Los apellidos deben ser texto válido.',
                'last_name.max' => 'Los apellidos no deben exceder 255 caracteres.',

                'email.required' => 'El correo es obligatorio.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'email.max' => 'El correo no debe exceder 255 caracteres.',
                'email.unique' => 'Este correo ya está registrado.',

                'origin_university.required' => 'La universidad de origen es obligatoria.',
                'origin_university.string' => 'La universidad de origen debe ser texto válido.',
                'origin_university.max' => 'La universidad de origen no debe exceder 255 caracteres.',

                'origin_municipality.required' => 'El municipio de procedencia es obligatorio.',
                'origin_municipality.string' => 'El municipio de procedencia debe ser texto válido.',
                'origin_municipality.max' => 'El municipio de procedencia no debe exceder 255 caracteres.',

                'desired_specialty.required' => 'La especialidad deseada es obligatoria.',
                'desired_specialty.string' => 'La especialidad deseada debe ser texto válido.',
                'desired_specialty.max' => 'La especialidad deseada no debe exceder 255 caracteres.',

                'whatsapp_number.required' => 'El número de WhatsApp es obligatorio.',
                'whatsapp_number.string' => 'El número de WhatsApp debe ser texto válido.',
                'whatsapp_number.max' => 'El número de WhatsApp no debe exceder 30 caracteres.',
                'whatsapp_number.regex' => 'Ingresa un número de WhatsApp válido.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Hay errores en la información enviada.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => trim((string) $request->name),
            'last_name' => trim((string) $request->last_name),
            'email' => mb_strtolower(trim((string) $request->email)),

            'origin_university' => trim((string) $request->origin_university),
            'origin_municipality' => trim((string) $request->origin_municipality),
            'desired_specialty' => trim((string) $request->desired_specialty),
            'whatsapp_number' => trim((string) $request->whatsapp_number),

            // manual/app user
            'is_admin' => false,
            'is_from_moodle' => false,
            'moodle_user_id' => null,

            // sin acceso inicial
            'has_app_access' => false,
            'granted_at' => null,
            'revoked_at' => now(),
            'synced_at' => null,

            // sin contraseña todavía
            // luego el admin otorgará acceso y definirá/enviará credenciales
            'password' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Tu solicitud de registro fue enviada correctamente. Tu acceso a la app será habilitado por un administrador.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'has_app_access' => (bool) $user->has_app_access,
            ],
        ], 201);
    }
}