<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Specialty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GiftImporter
{
    /**
     * Parse + valida archivo GIFT ENARM:
     * - $CATEGORY: Especialidad (obligatorio antes de preguntas)
     * - ::ID:: opcional (si no viene, se autogenera)
     * - [REF] obligatorio
     * - { } con 4 opciones exactas (A–D), 1 correcta (=), 3 incorrectas (~)
     */
    private function parseWithCategoryTracking(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $segments = preg_split('/^\$CATEGORY:\s*/m', $raw);
        $items = [];
        $errors = [];

        // No permitir preguntas antes de la primera categoría
        if (trim($segments[0]) !== '') {
            if (Str::contains($segments[0], '{')) {
                $errors[] = [
                    'block' => 0,
                    'message' => 'Hay preguntas antes del primer $CATEGORY:. Agrega $CATEGORY: Especialidad al inicio.',
                ];
            }
        }

        $blockIndex = 0;

        // Cada segmento posterior empieza con: "NombreCategoria\n..."
        for ($i = 1; $i < count($segments); $i++) {
            $seg = $segments[$i];

            $firstNewline = strpos($seg, "\n");
            $categoryName = $firstNewline === false ? trim($seg) : trim(substr($seg, 0, $firstNewline));
            $body         = $firstNewline === false ? '' : substr($seg, $firstNewline + 1);

            $categoryName = $this->normalizeCategoryDisplayName($categoryName);

            if ($categoryName === '') {
                $errors[] = ['block' => $blockIndex, 'message' => '$CATEGORY: vacío'];
                continue;
            }

            // Split por cierre de bloque "}" (cada pregunta termina en })
            $parts = preg_split('/\}\s*\n/m', $body);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') continue;
                if (!Str::contains($p, '{')) continue;

                $blockIndex++;
                $items[] = [
                    'block' => $blockIndex,
                    'category' => $categoryName,
                    'raw' => $p . "\n}",
                ];
            }
        }

        // Parsear cada pregunta
        $parsed = [];
        foreach ($items as $it) {
            $res = $this->parseQuestionBlock($it['raw']);

            if (!empty($res['errors'])) {
                foreach ($res['errors'] as $msg) {
                    $errors[] = ['block' => $it['block'], 'message' => $msg];
                }
                continue;
            }

            $parsed[] = [
                'block'     => $it['block'],
                'category'  => $it['category'],
                'gift_id'   => $res['gift_id'],      // puede venir null
                'stem'      => $res['stem'],
                'reference' => $res['reference'],
                'options'   => $res['options'],      // 4 items
            ];
        }

        return ['items' => $parsed, 'errors' => $errors];
    }

    private function parseQuestionBlock(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $errors = [];

        // ID opcional ::ID::
        $giftId = null;
        if (preg_match('/^\s*::(.+?)::\s*$/m', $raw, $m)) {
            $giftId = trim($m[1]);
            $raw = preg_replace('/^\s*::(.+?)::\s*$/m', '', $raw, 1);
        }

        $openPos = strpos($raw, '{');
        $closePos = strrpos($raw, '}');

        if ($openPos === false || $closePos === false || $closePos <= $openPos) {
            return ['errors' => ['No se encontró bloque de opciones { ... }.']];
        }

        $before = trim(substr($raw, 0, $openPos));
        $inside = trim(substr($raw, $openPos + 1, $closePos - $openPos - 1));

        $stem = $this->extractStem($before);
        $reference = $this->extractTag($before, 'REF');

        if (blank($stem)) $errors[] = 'Enunciado vacío.';
        if (blank($reference)) $errors[] = 'Falta [REF] o está vacío.';

        $optLines = preg_split('/\n+/', $inside);
        $opts = [];
        $correctCount = 0;

        foreach ($optLines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $prefix = $line[0] ?? '';
            if (!in_array($prefix, ['=', '~'], true)) {
                continue;
            }

            $isCorrect = $prefix === '=';
            if ($isCorrect) $correctCount++;

            $text = trim(substr($line, 1));

            // Si alguien puso "#", lo cortamos
            if (str_contains($text, '#')) {
                [$t] = explode('#', $text, 2);
                $text = trim($t);
            }

            if ($text === '') continue;

            $opts[] = [
                'text' => $text,
                'is_correct' => $isCorrect,
            ];
        }

        if (count($opts) !== 4) $errors[] = 'Se requieren exactamente 4 opciones (A–D).';
        if ($correctCount !== 1) $errors[] = 'Debe existir exactamente 1 opción correcta (=).';

        return [
            'gift_id'   => $giftId,
            'stem'      => $stem,
            'reference' => $reference,
            'options'   => $opts,
            'errors'    => $errors,
        ];
    }

    private function extractTag(string $text, string $tag): ?string
    {
        if (preg_match('/^\s*\[' . preg_quote($tag, '/') . '\]\s*(.+)\s*$/m', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractStem(string $text): string
    {
        $lines = preg_split('/\n+/', trim($text));
        $out = [];

        foreach ($lines as $l) {
            $t = trim($l);
            if ($t === '') continue;
            if (Str::startsWith($t, '[REF]')) continue;
            $out[] = $t;
        }

        return trim(implode("\n", $out));
    }

    /**
     * Normaliza el nombre "visible" (name) para evitar basura:
     * - trim
     * - colapsa espacios
     */
    private function normalizeCategoryDisplayName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name) ?: $name;
        return trim($name);
    }

    /**
     * Encuentra o crea especialidad usando SLUG (evita duplicados por acentos/mayúsculas).
     */
    private function findOrCreateSpecialty(string $categoryName, bool $createSpecialties): Specialty
    {
        $displayName = $this->normalizeCategoryDisplayName($categoryName);
        $slug = Str::slug($displayName);

        if ($slug === '') {
            throw new \RuntimeException('Especialidad inválida: slug vacío');
        }

        $specialty = Specialty::query()->where('slug', $slug)->first();
        if ($specialty) {
            return $specialty;
        }

        if (!$createSpecialties) {
            throw new \RuntimeException("Especialidad no existe: {$displayName}");
        }

        return Specialty::create([
            'name' => $displayName,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    public function import(string $raw, bool $createSpecialties = true, string $onDuplicate = 'update'): array
    {
        $parsed = $this->parseWithCategoryTracking($raw);

        if (!empty($parsed['errors'])) {
            return [
                'ok' => false,
                'imported' => 0,
                'updated' => 0,
                'created_specialties' => 0,
                'errors' => $parsed['errors'],
            ];
        }

        $imported = 0;
        $updated = 0;
        $createdSpecialties = 0;

        DB::transaction(function () use (
            &$parsed, &$imported, &$updated, &$createdSpecialties, $createSpecialties, $onDuplicate
        ) {
            foreach ($parsed['items'] as $it) {

                // ✅ Buscar/crear especialidad por slug
                $specialtyBefore = Specialty::query()->where('slug', Str::slug($it['category']))->first();
                $specialty = $this->findOrCreateSpecialty($it['category'], $createSpecialties);

                if (!$specialtyBefore) {
                    $createdSpecialties++;
                }

                // ✅ Hash de contenido (cuando no hay ::ID::)
                $hash = hash(
                    'sha256',
                    $specialty->id . '|' . $it['stem'] . '|' . json_encode($it['options'], JSON_UNESCAPED_UNICODE) . '|' . $it['reference']
                );

                // ✅ Buscar existente por ID si viene, si no por hash
                $q = Question::query()->where('specialty_id', $specialty->id);

                if (!blank($it['gift_id'])) {
                    $q->where('gift_id', $it['gift_id']);
                } else {
                    $q->where('content_hash', $hash);
                }

                $existing = $q->first();

                // -----------------------------
                // UPDATE / SKIP
                // -----------------------------
                if ($existing) {
                    if ($onDuplicate === 'skip') {
                        continue;
                    }

                    // ✅ Si el match fue por hash (sin ID), NO tocar el gift_id existente
                    $existing->update([
                        'stem' => $it['stem'],
                        'reference' => $it['reference'],
                        'content_hash' => $hash,
                        'is_active' => true,
                    ]);

                    $existing->options()->delete();
                    foreach ($it['options'] as $idx => $opt) {
                        QuestionOption::create([
                            'question_id' => $existing->id,
                            'order' => $idx + 1,
                            'text' => $opt['text'],
                            'is_correct' => $opt['is_correct'],
                        ]);
                    }

                    $updated++;
                    continue;
                }

                // -----------------------------
                // CREATE
                // -----------------------------
                // ✅ Si no viene ::ID::, generar ID estilo Moodle
                $giftId = $it['gift_id'] ?? null;

                if (blank($giftId)) {
                    /** @var QuestionIdGenerator $idGen */
                    $idGen = app(QuestionIdGenerator::class);
                    $giftId = $idGen->generateForSpecialty($specialty);
                }

                $question = Question::create([
                    'specialty_id' => $specialty->id,
                    'gift_id' => $giftId,
                    'stem' => $it['stem'],
                    'reference' => $it['reference'],
                    'content_hash' => $hash,
                    'is_active' => true,
                ]);

                foreach ($it['options'] as $idx => $opt) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'order' => $idx + 1,
                        'text' => $opt['text'],
                        'is_correct' => $opt['is_correct'],
                    ]);
                }

                $imported++;
            }
        });

        return [
            'ok' => true,
            'imported' => $imported,
            'updated' => $updated,
            'created_specialties' => $createdSpecialties,
            'errors' => [],
        ];
    }
}
