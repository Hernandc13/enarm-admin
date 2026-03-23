<?php

namespace App\Filament\Resources\SimulatorResource\Pages;

use App\Filament\Resources\SimulatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSimulators extends ListRecords
{
    protected static string $resource = SimulatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo simulador'),
        ];
    }
}