<?php

namespace App\Filament\Resources\Sumarios\Pages;

use App\Filament\Resources\Sumarios\SumarioResource;
use App\Models\SolicitudCompra;
use App\Models\Sumario;
use App\Support\ControlCodeGenerator;
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

        $this->ensureDraftsForProcuraAcceptedRequests();
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

    private function ensureDraftsForProcuraAcceptedRequests(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->can('Create:Sumario')) {
            return;
        }

        $eligibleSolicitudes = SolicitudCompra::query()
            ->whereNotNull('fecha_receptor')
            ->where('estado', '!=', 'RECHAZADA')
            ->whereHas('items', fn (Builder $query): Builder => $query
                ->whereRaw('COALESCE(cantidad_pedida, COALESCE(cantidad_a_comprar, cantidad_solicitada)) > COALESCE(cantidad_en_sumario, 0)'))
            ->get(['id', 'tipo_solicitud', 'departamento_solicitante']);

        if ($eligibleSolicitudes->isEmpty()) {
            return;
        }

        $eligibleIds = $eligibleSolicitudes->pluck('id')->all();

        $existingDraftSolicitudIds = Sumario::query()
            ->whereIn('solicitud_compra_id', $eligibleIds)
            ->where('workflow_estado', 'BORRADOR')
            ->pluck('solicitud_compra_id')
            ->all();

        $existingDraftMap = array_flip(array_map('intval', $existingDraftSolicitudIds));

        foreach ($eligibleSolicitudes as $solicitud) {
            $solicitudId = (int) $solicitud->id;

            if (isset($existingDraftMap[$solicitudId])) {
                continue;
            }

            $tipoOrden = str_contains(strtoupper((string) ($solicitud->tipo_solicitud ?? '')), 'SERVICIO')
                ? 'SERVICIO'
                : 'COMPRA';

            Sumario::query()->create([
                'solicitud_compra_id' => $solicitudId,
                'correlativo_sdc' => ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc'),
                'fecha' => now()->toDateString(),
                'procedencia' => 'LOCAL',
                'tipo_orden' => $tipoOrden,
                'departamento_solicitante' => (string) ($solicitud->departamento_solicitante ?: 'PENDIENTE'),
                'estado' => 'BORRADOR',
                'workflow_estado' => 'BORRADOR',
                'elaborado_por_user_id' => $user->id,
            ]);
        }
    }
}
