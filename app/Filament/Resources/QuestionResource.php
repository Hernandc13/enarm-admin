<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Question;
use App\Models\Specialty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Banco de preguntas';
    protected static ?string $modelLabel = 'Pregunta';
    protected static ?string $pluralModelLabel = 'Banco de preguntas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('specialty_id')
                ->label('Especialidad')
                ->options(fn () => Specialty::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),

            Forms\Components\TextInput::make('gift_id')
                ->label('ID de pregunta')
                ->maxLength(255)
                ->helperText('Ejemplo: Pregunta1, Pregunta2, Cardio15')
                ->nullable(),

            Forms\Components\Textarea::make('stem')
                ->label('Enunciado')
                ->required()
                ->rows(4),

            Forms\Components\Textarea::make('general_feedback')
                ->label('Retroalimentación general')
                ->required()
                ->rows(3)
                ->helperText('Se mostrará como retroalimentación de la pregunta.'),

            Forms\Components\Toggle::make('is_active')
                ->label('Activa')
                ->default(true),

            Forms\Components\Section::make('Opciones (A–D)')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Textarea::make('option_a')
                            ->label('Opción A')
                            ->required()
                            ->rows(2),

                        Forms\Components\Textarea::make('option_b')
                            ->label('Opción B')
                            ->required()
                            ->rows(2),

                        Forms\Components\Textarea::make('option_c')
                            ->label('Opción C')
                            ->required()
                            ->rows(2),

                        Forms\Components\Textarea::make('option_d')
                            ->label('Opción D')
                            ->required()
                            ->rows(2),

                        Forms\Components\Select::make('correct_option')
                            ->label('Respuesta correcta')
                            ->options([
                                'A' => 'A',
                                'B' => 'B',
                                'C' => 'C',
                                'D' => 'D',
                            ])
                            ->required(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('specialty.name')
                    ->label('Especialidad')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('gift_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('stem')
                    ->label('Enunciado')
                    ->limit(70)
                    ->wrap()
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('specialty_id')
                    ->label('Especialidad')
                    ->options(fn () => Specialty::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit'   => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}