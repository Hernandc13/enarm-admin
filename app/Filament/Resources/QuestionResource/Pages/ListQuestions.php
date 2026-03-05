<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Services\GiftImporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListQuestions extends ListRecords
{
    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva pregunta'),

            // ✅ NUEVO: Descargar formato GIFT
            Actions\Action::make('downloadGiftTemplate')
                ->label('Descargar formato GIFT')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('questions.gift.template.download'))
                ->openUrlInNewTab(),

            Actions\Action::make('importGift')
                ->label('Importar GIFT')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Archivo .gift')
                        ->required()
                        ->rules([
                            'required',
                            'file',
                            'extensions:gift,txt',
                            'max:10240',
                        ])
                        ->disk('public')
                        ->directory('imports/gift')
                        ->preserveFilenames(),

                    Forms\Components\Select::make('on_duplicate')
                        ->label('Si se encuentran preguntas idénticas, ¿qué quieres hacer?')
                        ->options([
                            'update' => 'Actualizar (sobrescribir)',
                            'skip'   => 'Omitir (no importar)',
                        ])
                        ->default('update')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $path = $data['file'];
                    $raw = Storage::disk('public')->get($path);

                    /** @var GiftImporter $importer */
                    $importer = app(GiftImporter::class);

                    $result = $importer->import(
                        raw: $raw,
                        createSpecialties: true,
                        onDuplicate: (string) $data['on_duplicate'],
                    );

                    if (! $result['ok']) {
                        $msg = collect($result['errors'])
                            ->take(10)
                            ->map(fn ($e) => "Bloque {$e['block']}: {$e['message']}")
                            ->implode("\n");

                        Notification::make()
                            ->title('Importación con errores')
                            ->body($msg . (count($result['errors']) > 10 ? "\n... (más errores)" : ''))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Importación exitosa')
                        ->body("Nuevas: {$result['imported']} | Actualizadas: {$result['updated']} | Especialidades creadas: {$result['created_specialties']}")
                        ->success()
                        ->send();

                    // refrescar tabla
                    $this->resetTable();
                }),
        ];
    }
}
