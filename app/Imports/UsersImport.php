<?php

namespace App\Imports;

use App\Mail\WelcomeAccessMail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;
    public int $skippedDuplicates = 0;
    public int $failed = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(private bool $sendWelcome = true) {}

    public function collection(Collection $rows): void
    {
        if ($rows->count() > 100) {
            throw new \InvalidArgumentException('Máximo 100 usuarios por archivo.');
        }

        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2; // encabezado en fila 1

            try {
                $name  = trim((string) ($row['nombre'] ?? ''));
                $last  = trim((string) ($row['apellidos'] ?? ''));
                $email = strtolower(trim((string) ($row['correo'] ?? '')));

                $passRaw = $row['contrasena'] ?? $row['contraseña'] ?? null;
                $pass = trim((string) ($passRaw ?? ''));

                // ✅ Nuevos campos (acepta headings alternos por si tu plantilla cambia)
                $originUniversity = trim((string) (
                    $row['universidad_origen']
                    ?? $row['universidad_de_origen']
                    ?? $row['universidad de origen']
                    ?? $row['universidad']
                    ?? ''
                ));

                $originMunicipality = trim((string) (
                    $row['municipio_procedencia']
                    ?? $row['municipio_de_procedencia']
                    ?? $row['municipio de procedencia']
                    ?? $row['municipio']
                    ?? ''
                ));

                $desiredSpecialty = trim((string) (
                    $row['especialidad_deseada']
                    ?? $row['especialidad_deceada']   // por si viene con typo en algún excel viejo
                    ?? $row['especialidad deseada']
                    ?? $row['especialidad']
                    ?? ''
                ));

                $whatsapp = trim((string) (
                    $row['whatsapp']
                    ?? $row['numero_whatsapp']
                    ?? $row['número_de_whatsapp']
                    ?? $row['numero de whatsapp']
                    ?? $row['número de whatsapp']
                    ?? ''
                ));

                if ($name === '' || $last === '' || $email === '') {
                    throw new \InvalidArgumentException("Fila {$rowNumber}: nombre, apellidos y correo son obligatorios.");
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException("Fila {$rowNumber}: correo inválido ({$email}).");
                }

                // ✅ Validación ligera WhatsApp (si viene)
                if ($whatsapp !== '' && ! preg_match('/^\+?[0-9\s\-\(\)]{7,30}$/', $whatsapp)) {
                    throw new \InvalidArgumentException("Fila {$rowNumber}: número de WhatsApp inválido ({$whatsapp}).");
                }

                // DUPLICADO: se omite y continúa
                if (User::query()->where('email', $email)->exists()) {
                    $this->skippedDuplicates++;
                    $this->errors[] = "Fila {$rowNumber}: duplicado, se omitió ({$email}).";
                    continue;
                }

                // Si no viene contraseña -> generar automáticamente
                $plain = $pass !== '' ? $pass : Str::password(12);

                if (mb_strlen($plain) < 8) {
                    throw new \InvalidArgumentException("Fila {$rowNumber}: la contraseña debe tener al menos 8 caracteres.");
                }

                $user = User::create([
                    'name'            => $name,
                    'last_name'       => $last,
                    'email'           => $email,
                    'password'        => Hash::make($plain),

                    // ✅ Nuevos campos (nullable)
                    'origin_university'   => $originUniversity !== '' ? $originUniversity : null,
                    'origin_municipality' => $originMunicipality !== '' ? $originMunicipality : null,
                    'desired_specialty'   => $desiredSpecialty !== '' ? $desiredSpecialty : null,
                    'whatsapp_number'     => $whatsapp !== '' ? $whatsapp : null,

                    // no admin
                    'is_admin'        => false,

                    // manual/excel => no Moodle
                    'moodle_user_id'  => null,
                    'is_from_moodle'  => false,
                    'synced_at'       => null,

                    // acceso automático
                    'has_app_access'  => true,
                    'granted_at'      => now(),
                    'revoked_at'      => null,
                ]);

                $this->imported++;

                if ($this->sendWelcome) {
                    Mail::to($user->email)->send(new WelcomeAccessMail([
                        'name'      => $user->name,
                        'last_name' => $user->last_name ?? '',
                        'email'     => $user->email,
                        'password'  => $plain,
                        'app_url'   => config('app.url'),
                    ]));
                }
            } catch (\Throwable $e) {
                $this->failed++;
                $this->errors[] = $e->getMessage();
                continue;
            }
        }
    }
}
