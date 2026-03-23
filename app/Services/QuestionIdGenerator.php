<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Specialty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuestionIdGenerator
{
    /**
     * Genera IDs por especialidad tipo:
     * CARDIO_0001, MI_0002, GINECO_0003, etc.
     *
     * Este será el método principal para preguntas creadas manualmente
     * o importadas sin ID.
     */
    public function generate(Specialty $specialty): string
    {
        return $this->generateForSpecialty($specialty);
    }

    /**
     * Compatibilidad con flujos anteriores.
     */
    public function generateForSpecialty(Specialty $specialty): string
    {
        $prefix = $this->makePrefix($specialty->name);

        return DB::transaction(function () use ($specialty, $prefix) {
            $lastGiftId = Question::query()
                ->where('specialty_id', $specialty->id)
                ->where('gift_id', 'like', $prefix . '\_%')
                ->orderByDesc('id')
                ->value('gift_id');

            $nextNumber = 1;

            if ($lastGiftId && preg_match('/^' . preg_quote($prefix, '/') . '_(\d+)$/', $lastGiftId, $matches)) {
                $lastNumber = (int) $matches[1];

                if ($lastNumber > 0) {
                    $nextNumber = $lastNumber + 1;
                }
            }

            $padLength = max(4, strlen((string) $nextNumber));

            return $prefix . '_' . str_pad((string) $nextNumber, $padLength, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Genera prefijo según la especialidad.
     *
     * Reglas:
     * - Si tiene 2 o más palabras: usar iniciales. Ej: Medicina Interna => MI
     * - Si tiene 1 palabra: usar slug limpio y hasta 6 caracteres. Ej: Cardiología => CARDIO
     */
    private function makePrefix(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        $words = preg_split('/\s+/', $name) ?: [];

        if (count($words) >= 2) {
            $initials = '';

            foreach ($words as $word) {
                $word = trim($word);

                if ($word === '') {
                    continue;
                }

                $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            }

            return mb_substr($initials, 0, 6) ?: 'Q';
        }

        $slug = Str::slug($name);
        $slug = strtoupper(str_replace('-', '', $slug));

        return substr($slug, 0, 6) ?: 'Q';
    }
}