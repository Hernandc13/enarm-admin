<?php

namespace App\Filament\Resources\SimulatorResource\Pages;

use App\Filament\Resources\SimulatorResource;
use Filament\Resources\Pages\EditRecord;

class EditSimulator extends EditRecord
{
    protected static string $resource = SimulatorResource::class;

    protected function getRedirectUrl(): string
    {
        return SimulatorResource::getUrl('index');
    }
}