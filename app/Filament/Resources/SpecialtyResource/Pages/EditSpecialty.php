<?php

namespace App\Filament\Resources\SpecialtyResource\Pages;

use App\Filament\Resources\SpecialtyResource;
use Filament\Resources\Pages\EditRecord;

class EditSpecialty extends EditRecord
{
    protected static string $resource = SpecialtyResource::class;

    protected function getRedirectUrl(): string
    {
        return SpecialtyResource::getUrl('index'); // ✅ vuelve al listado
    }
}
