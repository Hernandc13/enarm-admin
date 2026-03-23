<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SimulatorResource\Pages;
use App\Models\Simulator;
use App\Models\SimulatorCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SimulatorResource extends Resource
{
    protected static ?string $model = Simulator::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Simuladores';
    protected static ?string $modelLabel = 'Simulador';
    protected static ?string $pluralModelLabel = 'Simuladores';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category_id')
                ->label('Categoría')
                ->relationship(
                    name: 'category',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')
                )
                ->searchable()
                ->preload()
                ->required()
                ->native(false)
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->rows(3),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Orden')
                        ->numeric()
                        ->default(0),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true),
                ])
                ->createOptionUsing(function (array $data): int {
                    return SimulatorCategory::create($data)->id;
                })
                ->helperText('Selecciona la categoría a la que pertenece este simulador.'),

            Forms\Components\TextInput::make('name')
                ->label('Nombre del simulador')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('mode')
                ->label('Tipo de simulador')
                ->required()
                ->options([
                    Simulator::MODE_STUDY => 'Modo estudio',
                    Simulator::MODE_EXAM  => 'Modo examen',
                ])
                ->default(Simulator::MODE_EXAM)
                ->native(false)
                ->helperText('Estudio: práctica sin presión. Examen: simula condiciones reales.'),

            Forms\Components\Textarea::make('description')
                ->label('Descripción')
                ->rows(3),

            Forms\Components\Section::make('Disponibilidad')
                ->schema([
                    Forms\Components\DateTimePicker::make('available_from')
                        ->label('Fecha inicio')
                        ->seconds(false),

                    Forms\Components\DateTimePicker::make('available_until')
                        ->label('Fecha fin')
                        ->seconds(false),
                ]),

            Forms\Components\Section::make('Configuración')
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label('Publicado')
                        ->default(false),

                    Forms\Components\Toggle::make('shuffle_questions')
                        ->label('Barajar preguntas')
                        ->default(true),

                    Forms\Components\Toggle::make('shuffle_options')
                        ->label('Barajar opciones')
                        ->default(true),

                    Forms\Components\Grid::make(12)->schema([
                        Forms\Components\Toggle::make('max_attempts_unlimited')
                            ->label('Ilimitados')
                            ->default(true)
                            ->reactive()
                            ->afterStateHydrated(function (Forms\Components\Toggle $component, $state, callable $set, callable $get) {
                                $set('max_attempts_unlimited', $get('max_attempts') === null);
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('max_attempts', null);
                                }
                            })
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('max_attempts')
                            ->label('Intentos máximos')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Desmarca "Ilimitados" para especificar un número.')
                            ->disabled(fn (callable $get) => (bool) $get('max_attempts_unlimited'))
                            ->dehydrated(true)
                            ->dehydrateStateUsing(function ($state, callable $get) {
                                if ((bool) $get('max_attempts_unlimited')) {
                                    return null;
                                }

                                if ($state === null || $state === '') {
                                    return null;
                                }

                                return (int) $state;
                            })
                            ->columnSpan(9),
                    ]),

                    Forms\Components\Grid::make(12)->schema([
                        Forms\Components\Toggle::make('time_limit_unlimited')
                            ->label('Sin límite')
                            ->default(true)
                            ->reactive()
                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                $set('time_limit_unlimited', $get('time_limit_seconds') === null);
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('time_limit_minutes', null);
                                    $set('time_limit_seconds', null);
                                }
                            })
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('time_limit_minutes')
                            ->label('Límite de tiempo (minutos)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Desmarca "Sin límite" para definir minutos.')
                            ->disabled(fn (callable $get) => (bool) $get('time_limit_unlimited'))
                            ->reactive()
                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                $seconds = $get('time_limit_seconds');
                                $set('time_limit_minutes', $seconds ? (int) round(((int) $seconds) / 60) : null);
                            })
                            ->dehydrated(false)
                            ->columnSpan(9),

                        Forms\Components\Hidden::make('time_limit_seconds')
                            ->dehydrated(true)
                            ->dehydrateStateUsing(function ($state, callable $get) {
                                if ((bool) $get('time_limit_unlimited')) {
                                    return null;
                                }

                                $mins = $get('time_limit_minutes');

                                if ($mins === null || $mins === '') {
                                    return null;
                                }

                                return (int) $mins * 60;
                            }),
                    ]),

                    Forms\Components\Grid::make(12)->schema([
                        Forms\Components\Toggle::make('min_score_none')
                            ->label('Sin mínima')
                            ->default(true)
                            ->reactive()
                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                $set('min_score_none', $get('min_passing_score') === null);
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('min_passing_score', null);
                                }
                            })
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('min_passing_score')
                            ->label('Calificación mínima aprobatoria (0–100)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Desmarca "Sin mínima" para definir un valor.')
                            ->disabled(fn (callable $get) => (bool) $get('min_score_none'))
                            ->dehydrated(true)
                            ->dehydrateStateUsing(function ($state, callable $get) {
                                if ((bool) $get('min_score_none')) {
                                    return null;
                                }

                                if ($state === null || $state === '') {
                                    return null;
                                }

                                return (int) $state;
                            })
                            ->columnSpan(9),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Simulator::query()->with(['category'])->withCount('questions'))
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Simulador')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('mode')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Simulator::MODE_STUDY => 'Modo estudio',
                        Simulator::MODE_EXAM  => 'Modo examen',
                        default               => $state ?: '—',
                    })
                    ->colors([
                        'success' => Simulator::MODE_STUDY,
                        'danger'  => Simulator::MODE_EXAM,
                    ])
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Publicado')
                    ->boolean(),

                Tables\Columns\TextColumn::make('questions_count')
                    ->label('Preguntas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_from')
                    ->label('Inicio')
                    ->dateTime()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('available_until')
                    ->label('Fin')
                    ->dateTime()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('mode')
                    ->label('Tipo')
                    ->options([
                        Simulator::MODE_STUDY => 'Modo estudio',
                        Simulator::MODE_EXAM  => 'Modo examen',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('questions')
                    ->label('Agregar preguntas')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->url(fn (Simulator $record) => static::getUrl('questions', ['record' => $record])),

                Tables\Actions\EditAction::make()->label('Editar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'     => Pages\ListSimulators::route('/'),
            'create'    => Pages\CreateSimulator::route('/create'),
            'edit'      => Pages\EditSimulator::route('/{record}/edit'),
            'questions' => Pages\ManageSimulatorQuestions::route('/{record}/questions'),
        ];
    }
}