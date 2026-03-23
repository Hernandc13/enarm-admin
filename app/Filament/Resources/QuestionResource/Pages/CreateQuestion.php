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

    protected function getRedirectUrl(): string
    {
        return QuestionResource::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Question
    {
        return DB::transaction(function () use ($data) {
            $optionA = trim((string) $data['option_a']);
            $optionB = trim((string) $data['option_b']);
            $optionC = trim((string) $data['option_c']);
            $optionD = trim((string) $data['option_d']);
            $correct = (string) $data['correct_option'];

            unset(
                $data['option_a'],
                $data['option_b'],
                $data['option_c'],
                $data['option_d'],
                $data['correct_option']
            );

            /** @var Specialty $specialty */
            $specialty = Specialty::findOrFail((int) $data['specialty_id']);

            // Si no mandan ID manual, autogenerar con prefijo de especialidad
            if (blank(trim((string) ($data['gift_id'] ?? '')))) {
                /** @var QuestionIdGenerator $idGen */
                $idGen = app(QuestionIdGenerator::class);
                $data['gift_id'] = $idGen->generate($specialty);
            } else {
                $data['gift_id'] = trim((string) $data['gift_id']);
            }

            $data['stem'] = trim((string) $data['stem']);
            $data['general_feedback'] = trim((string) ($data['general_feedback'] ?? ''));

            $data['content_hash'] = hash(
                'sha256',
                $data['specialty_id'] . '|' .
                $data['gift_id'] . '|' .
                $data['stem'] . '|' .
                $optionA . '|' .
                $optionB . '|' .
                $optionC . '|' .
                $optionD . '|' .
                $correct . '|' .
                $data['general_feedback']
            );

            /** @var Question $question */
            $question = Question::create($data);

            $this->saveOptions(
                questionId: $question->id,
                a: $optionA,
                b: $optionB,
                c: $optionC,
                d: $optionD,
                correct: $correct
            );

            return $question;
        });
    }

    private function saveOptions(
        int $questionId,
        string $a,
        string $b,
        string $c,
        string $d,
        string $correct
    ): void {
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