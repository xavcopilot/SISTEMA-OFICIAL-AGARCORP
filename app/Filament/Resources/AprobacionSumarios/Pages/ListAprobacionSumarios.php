<?php

namespace App\Filament\Resources\AprobacionSumarios\Pages;

use App\Filament\Resources\AprobacionSumarios\AprobacionSumariosResource;
use App\Models\Sumario;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAprobacionSumarios extends ListRecords
{
    protected static string $resource = AprobacionSumariosResource::class;

    public function getTabs(): array
    {
        return [
            'bandeja_aprobacion' => Tab::make('Bandeja de aprobacion')
                ->badge(AprobacionSumariosResource::getNavigationBadge())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_estado', 'VALIDADO_FINANZAS')),
            'historial_aprobacion' => Tab::make('Historial de aprobacion')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('workflow_estado', [
                        'APROBADO_GERENCIA_FINANZAS',
                        'ODC_GENERADA',
                    ])),
        ];
    }
}
