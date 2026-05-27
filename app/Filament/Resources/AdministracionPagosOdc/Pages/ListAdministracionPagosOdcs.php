<?php

namespace App\Filament\Resources\AdministracionPagosOdc\Pages;

use App\Filament\Resources\AdministracionPagosOdc\AdministracionPagosOdcResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAdministracionPagosOdcs extends ListRecords
{
    protected static string $resource = AdministracionPagosOdcResource::class;

    public function getTabs(): array
    {
        return [
            'pagos_pendientes' => Tab::make('Pagos Pendientes')
                ->badge(AdministracionPagosOdcResource::getNavigationBadge())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PENDIENTE_PAGO_FINANZAS')),
            'pagos_registrados' => Tab::make('Pagos Registrados')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $subQuery): void {
                        $subQuery
                            ->whereNotNull('pago_registrado_at')
                            ->orWhereNotNull('comprobante_pago_path');
                    })),
        ];
    }
}
