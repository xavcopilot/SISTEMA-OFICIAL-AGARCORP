<?php

namespace App\Filament\Resources\InspeccionSumarios\Pages;

use App\Filament\Resources\InspeccionSumarios\InspeccionSumariosResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInspeccionSumarios extends ListRecords
{
    protected static string $resource = InspeccionSumariosResource::class;

    public function getTabs(): array
    {
        return [
            'mis_inspecciones' => Tab::make('Mis Inspecciones')
                ->badge(InspeccionSumariosResource::getNavigationBadge())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_estado', 'PENDIENTE_VALIDACION_FINANZAS')),
            'historial_inspeccion' => Tab::make('Historial')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('validado_finanzas_at')
                    ->where('validado_por_user_id', auth()->id())),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ayuda_flujo')
                ->label('Flujo')
                ->icon('heroicon-o-information-circle')
                ->color('gray')
                ->modalHeading('Inspeccion de Sumarios | Flujo')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalDescription('Aqui ves sumarios enviados por Procura. Puedes firmar la revision para enviarlo a Gerencia de Finanzas o rechazar con motivo para retorno a Procura.'),
        ];
    }
}
