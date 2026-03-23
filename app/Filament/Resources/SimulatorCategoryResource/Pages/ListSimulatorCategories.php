<?php

namespace App\Filament\Resources\SimulatorCategoryResource\Pages;

use App\Filament\Resources\SimulatorCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSimulatorCategories extends ListRecords
{
    protected static string $resource = SimulatorCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva categoría'),
        ];
    }
}