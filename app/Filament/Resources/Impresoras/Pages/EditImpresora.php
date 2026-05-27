<?php

namespace App\Filament\Resources\Impresoras\Pages;

use App\Filament\Resources\Impresoras\ImpresoraResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImpresora extends EditRecord
{
    protected static string $resource = ImpresoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
