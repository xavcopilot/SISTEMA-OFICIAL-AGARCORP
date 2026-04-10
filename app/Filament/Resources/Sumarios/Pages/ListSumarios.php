<?php

namespace App\Filament\Resources\Sumarios\Pages;

use App\Filament\Resources\Sumarios\SumarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSumarios extends ListRecords
{
    protected static string $resource = SumarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => SumarioResource::canCreate()),
        ];
    }
}
