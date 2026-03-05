<?php

namespace App\Filament\Resources\SimulatorResource\RelationManagers;

use App\Models\Question;
use App\Models\Specialty;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';
    protected static ?string $title = 'Preguntas del simulador';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('simulator_questions.order')
            ->columns([
                Tables\Columns\TextColumn::make('pivot.order')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('specialty.name')
                    ->label('Especialidad')
                    ->sortable(),

                Tables\Columns\TextColumn::make('gift_id')
                    ->label('ID')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('stem')
                    ->label('Enunciado')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Agregar preguntas')
                    ->form([
                        Forms\Components\Select::make('specialty_filter')
                            ->label('Filtrar por especialidad (opcional)')
                            ->options(fn () => Specialty::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->reactive()
                            ->visible(fn () => $this->ownerRecord->scope === 'total'),

                        Forms\Components\Select::make('recordId')
                            ->label('Pregunta')
                            ->searchable()
                            ->options(function (callable $get) {
                                $sim = $this->ownerRecord;

                                $q = Question::query()
                                    ->where('is_active', true)
                                    ->with('specialty')
                                    ->orderByDesc('id');

                                // ✅ Restringir por scope del simulador
                                if ($sim->scope === 'single' && $sim->single_specialty_id) {
                                    $q->where('specialty_id', $sim->single_specialty_id);
                                } elseif ($sim->scope === 'combined') {
                                    $ids = $sim->specialties()->pluck('specialties.id')->all();
                                    // Si aún no seleccionan especialidades, no mostrar opciones
                                    if (empty($ids)) {
                                        return [];
                                    }
                                    $q->whereIn('specialty_id', $ids);
                                } elseif ($sim->scope === 'total') {
                                    // total: opcional filtro manual
                                    if ($get('specialty_filter')) {
                                        $q->where('specialty_id', (int) $get('specialty_filter'));
                                    }
                                }

                                return $q->limit(200)->get()->mapWithKeys(function ($qq) {
                                    $label = ($qq->specialty?->name ?? '—') . ' | ' . ($qq->gift_id ?? '-') . ' | ' . mb_strimwidth($qq->stem, 0, 80, '…');
                                    return [$qq->id => $label];
                                })->toArray();
                            })
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $sim = $this->getOwnerRecord();

                        // ✅ order al final
                        $maxOrder = (int) ($sim->questions()->max('simulator_questions.order') ?? 0);
                        $next = $maxOrder + 1;

                        $sim->questions()->syncWithoutDetaching([
                            (int) $data['recordId'] => ['order' => $next],
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()->label('Quitar'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()->label('Quitar seleccionadas'),
            ]);
    }
}
