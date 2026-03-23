<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SpecialtyResource\Pages;
use App\Models\Specialty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SpecialtyResource extends Resource
{
    protected static ?string $model = Specialty::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Categorías del Banco';
    protected static ?string $modelLabel = 'Especialidad';
    protected static ?string $pluralModelLabel = 'Categorías del Banco';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Forms\Components\Toggle::make('is_active')
                ->label('Activa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Especialidad')
                ->searchable()
                ->sortable(),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Activa')
                ->boolean(),

            Tables\Columns\TextColumn::make('questions_count')
                ->counts('questions')
                ->label('Preguntas')
                ->sortable(),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Actualizado')
                ->dateTime(),
        ])
        ->actions([
            Tables\Actions\EditAction::make()->label('Editar'),
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
            'index'  => Pages\ListSpecialties::route('/'),
            'create' => Pages\CreateSpecialty::route('/create'),
            'edit'   => Pages\EditSpecialty::route('/{record}/edit'),
        ];
    }
}
