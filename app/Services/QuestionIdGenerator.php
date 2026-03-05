<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Specialty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuestionIdGenerator
{
    /**
     * Genera IDs tipo Moodle:
     * PREFIX_0001, PREFIX_0002...
     *
     * PREFIX:
     * - Si la especialidad tiene 2+ palabras: iniciales (ej. "Medicina Interna" => MI)
     * - Si es 1 palabra: primeras 6 letras del slug (ej. "Cardiología" => CARDIO* aprox)
     */
    public function generateForSpecialty(Specialty $specialty): string
    {
        $prefix = $this->makePrefix($specialty->name);

        // Para evitar condiciones de carrera, lo hacemos dentro de transacción y lock.
        return DB::transaction(function () use ($specialty, $prefix) {

            // Tomamos los últimos IDs de esa especialidad con ese prefijo
            $last = Question::query()
                ->where('specialty_id', $specialty->id)
                ->where('gift_id', 'like', $prefix . '\_%')
                ->orderByDesc('id') // suficiente porque siempre generamos secuencialmente
                ->value('gift_id');

            $nextNumber = 1;

            if ($last) {
                // Espera formato PREFIX_#### (o más dígitos)
                $parts = explode('_', $last);
                $n = intval(end($parts));
                if ($n > 0) $nextNumber = $n + 1;
            }

            $pad = $nextNumber <= 9999 ? 4 : strlen((string) $nextNumber);

            return $prefix . '_' . str_pad((string) $nextNumber, $pad, '0', STR_PAD_LEFT);
        });
    }

    private function makePrefix(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        $words = preg_split('/\s+/', $name);

        if (count($words) >= 2) {
            // Iniciales
            $initials = '';
            foreach ($words as $w) {
                $w = trim($w);
                if ($w === '') continue;
                $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            }

            // Máximo 6 caracteres
            return mb_substr($initials, 0, 6);
        }

        // Una palabra: usar slug sin guiones, primeras 6 letras
        $slug = Str::slug($name);
        $slug = strtoupper(str_replace('-', '', $slug));

        return substr($slug, 0, 6) ?: 'Q';
    }
}
