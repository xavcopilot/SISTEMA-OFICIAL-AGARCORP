<?php

namespace App\Filament\Resources\AprobacionesCompra\Pages;

use App\Filament\Resources\AprobacionesCompra\AprobacionesCompraResource;
use App\Filament\Widgets\Compras\AprobacionesCompraKpiStats;
use App\Support\SolicitudCompraFlow;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

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
            $tabs['historial_procura'] = Tab::make('Historial procura')
                ->modifyQueryUsing(fn ($query) => SolicitudCompraFlow::roleApprovalHistoryQuery($query, $user, 'procura'));
        }

        return $tabs;
    }
}