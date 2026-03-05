<?php

namespace App\Filament\Resources\SimulatorResource\Pages;

use App\Filament\Resources\SimulatorResource;
use App\Models\Question;
use App\Models\Specialty;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\DB;

class ManageSimulatorQuestions extends Page implements HasTable
{
    use InteractsWithTable;
    use InteractsWithRecord;

    protected static string $resource = SimulatorResource::class;
    protected static ?string $title = 'Administrar preguntas';
    protected static string $view = 'filament.resources.simulator-resource.pages.manage-simulator-questions';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    /**
     * Keys seleccionadas del Table (pueden ser question_id o pivot.id).
     */
    private function getSelectedKeys(): array
    {
        $raw = $this->selectedTableRecords ?? [];

        return collect($raw)
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Compacta el order en la tabla pivot.
     */
    private function compactOrder(): void
    {
        $simId = (int) $this->record->id;

        $rows = DB::table('simulator_questions')
            ->where('simulator_id', $simId)
            ->orderByRaw('`order` IS NULL, `order` ASC, id ASC')
            ->get(['id']);

        $i = 1;
        foreach ($rows as $row) {
            DB::table('simulator_questions')
                ->where('simulator_id', $simId)
                ->where('id', (int) $row->id)
                ->update(['order' => $i]);
            $i++;
        }
    }

    /**
     * Refresco duro del Table + limpia selección.
     */
    private function hardRefreshTable(): void
    {
        $this->selectedTableRecords = [];

        if (method_exists($this->record, 'unsetRelation')) {
            $this->record->unsetRelation('questions');
        }

        $this->record->refresh();

        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
    }

    /**
     * Devuelve conteo de preguntas disponibles (activas, por especialidad) excluyendo ya agregadas al simulador.
     */
    private function availableCountForSpecialty(int $simId, int $specialtyId): int
    {
        $attachedIds = DB::table('simulator_questions')
            ->where('simulator_id', $simId)
            ->pluck('question_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $q = Question::query()
            ->where('is_active', true)
            ->where('specialty_id', $specialtyId);

        if (! empty($attachedIds)) {
            $q->whereNotIn('id', $attachedIds);
        }

        return (int) $q->count();
    }

    public function table(Table $table): Table
    {
        $simId = (int) $this->record->id;

        return $table
            ->query(
                $this->record->questions()
                    ->select('questions.*')
                    ->with('specialty')
                    ->withPivot(['order'])
                    ->getQuery()
            )
            ->defaultSort('simulator_questions.order')
            ->columns([
                Tables\Columns\TextColumn::make('row_index')->label('#')->rowIndex(),

                Tables\Columns\TextColumn::make('specialty.name')
                    ->label('Especialidad')
                    ->sortable(),

                Tables\Columns\TextColumn::make('gift_id')
                    ->label('ID')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('stem')
                    ->label('Enunciado')
                    ->limit(90)
                    ->wrap()
                    ->searchable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('attachQuestions')
                    ->label('Agregar preguntas')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Agregar preguntas aleatorias por especialidad')
                    ->modalDescription('Elige una o varias especialidades y define cuántas preguntas quieres agregar de cada una. Se seleccionan aleatoriamente sin repetir y sin duplicar las ya agregadas al simulador.')
                    ->form([
                        Forms\Components\Repeater::make('specialties')
                            ->label('Especialidades a agregar')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Agregar otra especialidad')
                            ->columns(12)
                            ->schema([
                                Forms\Components\Select::make('specialty_id')
                                    ->label('Especialidad')
                                    ->options(fn () => Specialty::query()->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(6),

                                Forms\Components\Placeholder::make('available')
                                    ->label('Disponibles')
                                    ->content(function (Get $get) use ($simId) {
                                        $sid = (int) ($get('specialty_id') ?? 0);
                                        if ($sid <= 0) {
                                            return '—';
                                        }

                                        $count = $this->availableCountForSpecialty($simId, $sid);
                                        return "{$count} preguntas";
                                    })
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('qty')
                                    ->label('Cantidad a agregar')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->reactive()
                                    ->helperText('Se agregarán al azar (sin repetir).')
                                    ->columnSpan(3)
                                    ->rule(function (Get $get) use ($simId) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get, $simId) {
                                            $sid = (int) ($get('specialty_id') ?? 0);
                                            $qty = (int) ($value ?? 0);

                                            if ($sid <= 0) {
                                                $fail('Selecciona una especialidad.');
                                                return;
                                            }

                                            if ($qty <= 0) {
                                                $fail('La cantidad debe ser mayor a 0.');
                                                return;
                                            }

                                            $available = $this->availableCountForSpecialty($simId, $sid);

                                            if ($qty > $available) {
                                                $fail("No hay suficientes preguntas disponibles. Disponibles: {$available}.");
                                            }
                                        };
                                    }),
                            ]),
                    ])
                    ->action(function (array $data) use ($simId) {
                        $rows = collect($data['specialties'] ?? [])
                            ->filter(fn ($r) => ! empty($r['specialty_id']) && ! empty($r['qty']))
                            ->values();

                        if ($rows->isEmpty()) {
                            Notification::make()
                                ->title('No configuraste especialidades')
                                ->warning()
                                ->send();
                            return;
                        }

                        // IDs ya agregados al simulador (para evitar duplicados)
                        $attachedIds = DB::table('simulator_questions')
                            ->where('simulator_id', $simId)
                            ->pluck('question_id')
                            ->map(fn ($v) => (int) $v)
                            ->all();

                        $maxOrder = (int) (DB::table('simulator_questions')
                            ->where('simulator_id', $simId)
                            ->max('order') ?? 0);

                        $nextOrder = $maxOrder;
                        $totalAdded = 0;
                        $summary = [];

                        DB::beginTransaction();

                        try {
                            foreach ($rows as $r) {
                                $specialtyId = (int) $r['specialty_id'];
                                $qty = (int) $r['qty'];

                                // Tomar IDs disponibles (activas, de esa especialidad, no adjuntas)
                                $q = Question::query()
                                    ->where('is_active', true)
                                    ->where('specialty_id', $specialtyId);

                                if (! empty($attachedIds)) {
                                    $q->whereNotIn('id', $attachedIds);
                                }

                                $picked = $q->inRandomOrder()
                                    ->limit($qty)
                                    ->pluck('id')
                                    ->map(fn ($v) => (int) $v)
                                    ->all();

                                // Adjuntar en pivot con order consecutivo
                                foreach ($picked as $qid) {
                                    $nextOrder++;

                                    DB::table('simulator_questions')->updateOrInsert(
                                        ['simulator_id' => $simId, 'question_id' => $qid],
                                        ['order' => $nextOrder, 'updated_at' => now(), 'created_at' => now()]
                                    );

                                    $attachedIds[] = $qid; // mantener lista actualizada
                                }

                                $name = Specialty::query()->whereKey($specialtyId)->value('name') ?: "Especialidad {$specialtyId}";
                                $addedCount = count($picked);
                                $totalAdded += $addedCount;

                                $summary[] = "{$name}: {$addedCount} agregadas";
                            }

                            DB::commit();

                            $this->compactOrder();
                            $this->hardRefreshTable();

                            Notification::make()
                                ->title('Preguntas agregadas')
                                ->body("Total agregadas: {$totalAdded}\n" . implode("\n", $summary))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            DB::rollBack();

                            Notification::make()
                                ->title('No se pudieron agregar preguntas')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('deleteOne')
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) use ($simId) {
                        $questionId = (int) $record->id;

                        $deleted = (int) DB::table('simulator_questions')
                            ->where('simulator_id', $simId)
                            ->where('question_id', $questionId)
                            ->delete();

                        if ($deleted > 0) {
                            $this->compactOrder();
                            $this->hardRefreshTable();

                            Notification::make()
                                ->title('Pregunta eliminada correctamente')
                                ->success()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->title('No se pudo eliminar (relación no encontrada)')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('deleteSelected')
                        ->label('Eliminar seleccionadas')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function () use ($simId) {
                            $keys = $this->getSelectedKeys();

                            if (empty($keys)) {
                                Notification::make()
                                    ->title('No seleccionaste preguntas')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $deleted = 0;

                            // A) tratar keys como question_id
                            $deleted = (int) DB::table('simulator_questions')
                                ->where('simulator_id', $simId)
                                ->whereIn('question_id', $keys)
                                ->delete();

                            // B) si no borró nada, tratar keys como pivot.id -> convertir a question_id
                            if ($deleted === 0) {
                                $questionIds = DB::table('simulator_questions')
                                    ->where('simulator_id', $simId)
                                    ->whereIn('id', $keys)
                                    ->pluck('question_id')
                                    ->map(fn ($v) => (int) $v)
                                    ->all();

                                if (! empty($questionIds)) {
                                    $deleted = (int) DB::table('simulator_questions')
                                        ->where('simulator_id', $simId)
                                        ->whereIn('question_id', $questionIds)
                                        ->delete();
                                }
                            }

                            if ($deleted > 0) {
                                $this->compactOrder();
                                $this->hardRefreshTable();

                                Notification::make()
                                    ->title("Se eliminaron {$deleted} preguntas correctamente")
                                    ->success()
                                    ->send();
                                return;
                            }

                            Notification::make()
                                ->title('No se pudo eliminar (no se encontraron relaciones)')
                                ->warning()
                                ->send();
                        }),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('backToList')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->url(SimulatorResource::getUrl('index')),
        ];
    }
}
