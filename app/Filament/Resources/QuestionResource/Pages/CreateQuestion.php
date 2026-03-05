<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Specialty;
use App\Services\QuestionIdGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateQuestion extends CreateRecord
{
    protected static string $resource = QuestionResource::class;

    // ✅ Al guardar, volver al listado
    protected function getRedirectUrl(): string
    {
        return QuestionResource::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Question
    {
        return DB::transaction(function () use ($data) {

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

            /** @var Specialty $specialty */
            $specialty = Specialty::findOrFail($data['specialty_id']);

            /** @var QuestionIdGenerator $idGen */
            $idGen = app(QuestionIdGenerator::class);

            // ✅ Generar ID estilo Moodle
            $data['gift_id'] = $idGen->generateForSpecialty($specialty);

            $data['content_hash'] = hash(
                'sha256',
                $data['specialty_id'] . '|' . $data['stem'] . '|' . $optionA . '|' . $optionB . '|' . $optionC . '|' . $optionD . '|' . $correct . '|' . $data['reference']
            );

            /** @var Question $question */
            $question = Question::create($data);

            $this->saveOptions($question->id, $optionA, $optionB, $optionC, $optionD, $correct);

            return $question;
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
