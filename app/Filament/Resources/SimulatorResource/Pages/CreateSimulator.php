<?php

namespace App\Filament\Resources\SimulatorResource\Pages;

use App\Filament\Resources\SimulatorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSimulator extends CreateRecord
{
    protected static string $resource = SimulatorResource::class;

    protected function getRedirectUrl(): string
    {
        return SimulatorResource::getUrl('index'); // ✅ vuelve al listado
    }
}
