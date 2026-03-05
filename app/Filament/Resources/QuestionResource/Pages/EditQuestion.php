<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Models\Question;
use App\Models\QuestionOption;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditQuestion extends EditRecord
{
    protected static string $resource = QuestionResource::class;

    // ✅ Al guardar, volver al listado
    protected function getRedirectUrl(): string
    {
        return QuestionResource::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Question $record */
        $record = $this->record;

        $opts = $record->options()->orderBy('order')->get()->keyBy('order');

        $data['option_a'] = $opts[1]->text ?? '';
        $data['option_b'] = $opts[2]->text ?? '';
        $data['option_c'] = $opts[3]->text ?? '';
        $data['option_d'] = $opts[4]->text ?? '';

        $correctOrder = $opts->firstWhere('is_correct', true)?->order;
        $data['correct_option'] = match ($correctOrder) {
            1 => 'A',
            2 => 'B',
            3 => 'C',
            4 => 'D',
            default => 'A',
        };

        return $data;
    }

    protected function handleRecordUpdate($record, array $data): Question
    {
        /** @var Question $record */
        return DB::transaction(function () use ($record, $data) {

            // ✅ Blindaje: aunque alguien manipule el request, NO se actualiza gift_id en edición
            unset($data['gift_id']);

            $optionA = $data['option_a'];
            $optionB = $data['option_b'];
            $optionC = $data['option_c'];
            $optionD = $data['option_d'];
            $correct = $data['correct_option'];

            unset(
                $data['option_a'],
                $data['option_b'],
                $data['option_c'],
                $data['option_d'],
                $data['correct_option']
            );

            $data['content_hash'] = hash(
                'sha256',
                $data['specialty_id'] . '|' . $data['stem'] . '|' . $optionA . '|' . $optionB . '|' . $optionC . '|' . $optionD . '|' . $correct . '|' . $data['reference']
            );

            $record->update($data);

            // Reemplazar opciones (A–D) siempre
            $record->options()->delete();
            $this->saveOptions($record->id, $optionA, $optionB, $optionC, $optionD, $correct);

            return $record;
        });
    }

    private function saveOptions(int $questionId, string $a, string $b, string $c, string $d, string $correct): void
    {
        $map = [
            1 => ['key' => 'A', 'text' => $a],
            2 => ['key' => 'B', 'text' => $b],
            3 => ['key' => 'C', 'text' => $c],
            4 => ['key' => 'D', 'text' => $d],
        ];

        foreach ($map as $order => $row) {
            QuestionOption::create([
                'question_id' => $questionId,
                'order' => $order,
                'text' => $row['text'],
                'is_correct' => ($row['key'] === $correct),
            ]);
        }
    }
}
