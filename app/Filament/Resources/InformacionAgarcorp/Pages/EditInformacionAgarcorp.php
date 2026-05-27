<?php

namespace App\Filament\Resources\InformacionAgarcorp\Pages;

use App\Filament\Resources\InformacionAgarcorp\InformacionAgarcorpResource;
use Filament\Resources\Pages\EditRecord;

class EditInformacionAgarcorp extends EditRecord
{
    protected static string $resource = InformacionAgarcorpResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
