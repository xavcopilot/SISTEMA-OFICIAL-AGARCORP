<?php

namespace App\Support;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Models\Departamento;
use App\Models\OrdenCompra;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class OrdenCompraRecepcionService
{
    public function procesarRecepcion(OrdenCompra $ordenCompra, User $user, string $tipoDocumento, ?string $facturaPath = null): OrdenCompra
    {
        $tipoDocumento = strtoupper(trim($tipoDocumento));

        if (! in_array($tipoDocumento, ['FACTURA', 'NOTA'], true)) {
            throw new \InvalidArgumentException('Tipo de documento de recepcion no valido.');
        }

        if ($tipoDocumento === 'FACTURA' && blank($facturaPath)) {
            throw new \InvalidArgumentException('Debe cargar la imagen de la factura.');
        }

        return DB::transaction(function () use ($ordenCompra, $user, $tipoDocumento, $facturaPath): OrdenCompra {
            $ordenCompra = OrdenCompra::query()
                ->with(['items', 'sumario.solicitudCompra.solicitadoPor'])
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            if ($ordenCompra->recepcion_procesada_at) {
                return $ordenCompra;
            }

            $now = now();

            $ordenCompra->forceFill([
                'tipo_documento_recepcion' => $tipoDocumento,
                'factura_path' => $tipoDocumento === 'FACTURA' ? $facturaPath : null,
                'factura_pendiente' => $tipoDocumento === 'NOTA',
                'recepcion_procesada_at' => $now,
                'recibido_por_user_id' => $user->id,
                'estado' => 'RECIBIDA',
            ])->save();

            $ordenCompra->items()->update([
                'estado_recepcion' => 'ZONA_TRANSICION',
                'en_transicion_at' => $now,
            ]);

            $this->notifySolicitante($ordenCompra);

            if ($tipoDocumento === 'FACTURA' && filled($facturaPath)) {
                $this->notifyAdministracion($ordenCompra);
            }

            return $ordenCompra->fresh(['sumario.solicitudCompra.solicitadoPor']);
        });
    }

    private function notifySolicitante(OrdenCompra $ordenCompra): void
    {
        $solicitante = $ordenCompra->sumario?->solicitudCompra?->solicitadoPor;

        if (! $solicitante) {
            return;
        }

        Notification::make()
            ->title('Tu articulo ha llegado')
            ->body('La ODC ' . (string) $ordenCompra->correlativo_odc . ' ya esta en zona de transicion. Ingresa y presiona "Aceptar Conformidad" para registrar entrada oficial.')
            ->success()
            ->sendToDatabase($solicitante);
    }

    private function notifyAdministracion(OrdenCompra $ordenCompra): void
    {
        $departamentoId = Departamento::query()
            ->whereIn('nombre', ['ADMINISTRACIÓN', 'ADMINISTRACION'])
            ->value('id');

        $usuarios = User::query()
            ->when($departamentoId, fn ($query) => $query->where('departamento_id', $departamentoId))
            ->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        $url = OrdenCompraResource::getUrl('edit', ['record' => $ordenCompra]);

        $usuarios->each(function (User $adminUser) use ($ordenCompra, $url): void {
            Notification::make()
                ->title('Nueva factura para proceso contable')
                ->body('La ODC ' . (string) $ordenCompra->correlativo_odc . ' subio factura en recepcion. Revisar en bandeja de Administracion.')
                ->actions([
                    \Filament\Actions\Action::make('abrir')
                        ->label('Abrir ODC')
                        ->url($url)
                        ->button(),
                ])
                ->warning()
                ->sendToDatabase($adminUser);
        });
    }
}
