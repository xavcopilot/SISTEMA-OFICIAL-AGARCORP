<?php

namespace App\Filament\Resources\AprobacionesCompra\Pages;

use App\Filament\Resources\AprobacionesCompra\AprobacionesCompraResource;
use App\Filament\Widgets\Compras\AprobacionesCompraKpiStats;
use App\Models\SolicitudCompra;
use App\Support\SolicitudCompraFlow;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAprobacionesCompras extends ListRecords
{
    protected static string $resource = AprobacionesCompraResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            AprobacionesCompraKpiStats::class,
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        $reviewTabLabel = $user?->hasRole(SolicitudCompraFlow::APPROVER_ROLES)
            ? 'Bandeja de aprobaciones'
            : 'Bandeja de revision';

        $tabs = [
            'bandeja_revision' => Tab::make($reviewTabLabel)
                ->modifyQueryUsing(fn ($query) => SolicitudCompraFlow::pendingAreaInboxQuery($query, $user)),
        ];

        if ($user?->hasRole(SolicitudCompraFlow::STORAGE_ROLES)) {
            $tabs['historial_almacen'] = Tab::make('Historial almacén')
                ->modifyQueryUsing(fn ($query) => SolicitudCompraFlow::roleApprovalHistoryQuery($query, $user, 'almacen'));
        }

        if ($user?->hasRole(SolicitudCompraFlow::APPROVER_ROLES)) {
            $tabs['historial_aprobacion'] = Tab::make('Historial aprobación')
                ->modifyQueryUsing(fn ($query) => SolicitudCompraFlow::roleApprovalHistoryQuery($query, $user, 'aprobador'));
        }

        if ($user?->hasRole(SolicitudCompraFlow::PROCUREMENT_ROLES)) {
            $tabs['bandeja_sumarios'] = Tab::make('Bandeja de Sumarios')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    return $query
                        ->where('estado', SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA)
                        ->whereNotNull('fecha_receptor')
                        ->where('estado', '!=', SolicitudCompra::ESTADO_RECHAZADA)
                        ->whereHas('items', function (Builder $itemQuery): void {
                            $itemQuery->whereRaw('COALESCE(cantidad_pedida, COALESCE(cantidad_a_comprar, cantidad_solicitada)) > COALESCE(cantidad_en_sumario, 0)');
                        });
                });

            $tabs['historial_procura'] = Tab::make('Historial procura')
                ->modifyQueryUsing(fn ($query) => SolicitudCompraFlow::roleApprovalHistoryQuery($query, $user, 'procura'));
        }

        return $tabs;
    }
}