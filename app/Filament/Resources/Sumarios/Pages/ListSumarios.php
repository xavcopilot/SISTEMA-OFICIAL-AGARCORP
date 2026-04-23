<?php

namespace App\Filament\Resources\Sumarios\Pages;

use App\Filament\Resources\Sumarios\SumarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSumarios extends ListRecords
{
    protected static string $resource = SumarioResource::class;

    public function getTabs(): array
    {
        return [
            'sumarios' => Tab::make('Historial de sumarios')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('workflow_estado', '!=', 'BORRADOR')),
            'borradores' => Tab::make('Borradores')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('workflow_estado', 'BORRADOR')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => SumarioResource::canCreate()),
        ];
    }
}
