<?php

namespace App\Filament\Resources\Impresoras\Pages;

use App\Filament\Resources\Impresoras\ImpresoraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImpresoras extends ListRecords
{
    protected static string $resource = ImpresoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
