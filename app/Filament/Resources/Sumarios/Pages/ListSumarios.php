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

    public function mount(): void
    {
        parent::mount();

        if (blank($this->activeTab)) {
            $this->activeTab = 'creacion_sumarios';
        }
    }

    public function getTabs(): array
    {
        $creationCount = SumarioResource::countCreationNotifications();
        $correctionCount = SumarioResource::countCorrectionNotifications();

        return [
            'creacion_sumarios' => Tab::make('Creación de Sumarios')
                ->badge($creationCount > 0 ? (string) $creationCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_estado', 'BORRADOR')
                    ->whereHas('solicitudCompra.items', fn (Builder $itemsQuery): Builder => $itemsQuery
                        ->whereRaw('COALESCE(cantidad_pedida, COALESCE(cantidad_a_comprar, cantidad_solicitada)) > COALESCE(cantidad_en_sumario, 0)'))),
            'en_correccion' => Tab::make('Sumarios en correccion')
                ->badge($correctionCount > 0 ? (string) $correctionCount : null)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('workflow_estado', [
                        'PENDIENTE_VALIDACION_FINANZAS',
                        'VALIDADO_FINANZAS',
                        'RECHAZADO_VALIDACION_FINANZAS',
                        'RECHAZADO_GERENCIA_FINANZAS',
                    ])),
            'sumarios' => Tab::make('Historial de sumarios')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('workflow_estado', [
                    'APROBADO_GERENCIA_FINANZAS',
                    'ODC_GENERADA',
                    'RECHAZADO',
                    'RECHAZADO_GERENCIA_FINANZAS',
                ])),
            'borradores' => Tab::make('Borradores')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('workflow_estado', 'BORRADOR')
                    ->where(function (Builder $draftQuery): Builder {
                        return $draftQuery
                            ->whereDoesntHave('solicitudCompra.items', fn (Builder $itemsQuery): Builder => $itemsQuery
                                ->whereRaw('COALESCE(cantidad_pedida, COALESCE(cantidad_a_comprar, cantidad_solicitada)) > COALESCE(cantidad_en_sumario, 0)'));
                    })),
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
