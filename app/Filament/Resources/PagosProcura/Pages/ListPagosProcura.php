<?php

namespace App\Filament\Resources\PagosProcura\Pages;

use App\Filament\Resources\PagosProcura\PagosProcuraResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPagosProcura extends ListRecords
{
    protected static string $resource = PagosProcuraResource::class;

    public function getTabs(): array
    {
        return [
            'pendientes_pago' => Tab::make('Pendientes de pago')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PENDIENTE_PAGO_FINANZAS')),
            'pagos_registrados' => Tab::make('Pagos registrados')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PAGO_REGISTRADO_FINANZAS')),
            'pagadas_transito' => Tab::make('Pagadas y en transito')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_post_compra', 'PAGADO_Y_EN_TRANSITO')),
        ];
    }
}
