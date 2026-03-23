<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SimulatorCategoryResource\Pages;
use App\Models\SimulatorCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SimulatorCategoryResource extends Resource
{
    protected static ?string $model = SimulatorCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationLabel = 'Categorías de simuladores';
    protected static ?string $modelLabel = 'Categoría';
    protected static ?string $pluralModelLabel = 'Categorías';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->label('Descripción')
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->label('Activa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('simulators_count')
                    ->label('Simuladores')
                    ->counts('simulators')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
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
            'index'  => Pages\ListSimulatorCategories::route('/'),
            'create' => Pages\CreateSimulatorCategory::route('/create'),
            'edit'   => Pages\EditSimulatorCategory::route('/{record}/edit'),
        ];
    }
}