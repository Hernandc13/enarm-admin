<?php

namespace App\Filament\Resources\SimulatorCategoryResource\Pages;

use App\Filament\Resources\SimulatorCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditSimulatorCategory extends EditRecord
{
    protected static string $resource = SimulatorCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return SimulatorCategoryResource::getUrl('index');
    }
}