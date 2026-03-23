<?php

namespace App\Filament\Resources\SimulatorCategoryResource\Pages;

use App\Filament\Resources\SimulatorCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSimulatorCategory extends CreateRecord
{
    protected static string $resource = SimulatorCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return SimulatorCategoryResource::getUrl('index');
    }
}