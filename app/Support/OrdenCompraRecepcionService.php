<?php

namespace App\Support;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Models\Departamento;
use App\Models\OrdenCompra;
use App\Models\User;
use App\Support\Filament\DatabaseNotificationSender;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class OrdenCompraRecepcionService
{
    public function cargarDocumentoProcura(OrdenCompra $ordenCompra, User $user, string $tipoDocumento, ?string $documentoPath = null): OrdenCompra
    {
        $tipoDocumento = strtoupper(trim($tipoDocumento));

        if (! in_array($tipoDocumento, ['FACTURA', 'NOTA'], true)) {
            throw new \InvalidArgumentException('Tipo de documento de recepcion no valido.');
        }

        if (blank($documentoPath)) {
            throw new \InvalidArgumentException('Debe cargar la imagen o PDF de la factura o nota de entrega.');
        }

        return DB::transaction(function () use ($ordenCompra, $user, $tipoDocumento, $documentoPath): OrdenCompra {
            $ordenCompra = OrdenCompra::query()
                ->with(['items', 'sumario.solicitudCompra.solicitadoPor'])
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            $invoiceAfterNota = $this->isInvoiceAfterNotaPending($ordenCompra, $tipoDocumento);

            if ($ordenCompra->recepcion_procesada_at && ! $invoiceAfterNota) {
                $payload = [
                    'tipo_documento_recepcion' => $tipoDocumento,
                    'factura_pendiente' => $tipoDocumento === 'NOTA',
                ];

                if ($tipoDocumento === 'NOTA') {
                    $payload['nota_entrega_path'] = $documentoPath;
                } else {
                    $payload['factura_path'] = $documentoPath;
                }

                $ordenCompra->forceFill($payload)->save();

                if ($tipoDocumento === 'FACTURA' && filled($documentoPath)) {
                    $this->notifyFinanzas($ordenCompra);
                }

                return $ordenCompra->fresh(['sumario.solicitudCompra.solicitadoPor']);
            }

            if ($invoiceAfterNota) {
                $ordenCompra->forceFill([
                    'tipo_documento_recepcion' => 'FACTURA',
                    'factura_path' => $documentoPath,
                    'factura_pendiente' => false,
                ])->save();

                $this->notifyFinanzas($ordenCompra);

                return $ordenCompra->fresh(['sumario.solicitudCompra.solicitadoPor']);
            }

            $payload = [
                'tipo_documento_recepcion' => $tipoDocumento,
                'factura_pendiente' => $tipoDocumento === 'NOTA',
                'estado' => 'RECIBIDA',
                'workflow_post_compra' => 'DOCUMENTO_RECEPCION_CARGADO_PROCURA',
                'confirmado_por_user_id' => $user->id,
            ];

            if ($tipoDocumento === 'NOTA') {
                $payload['nota_entrega_path'] = $documentoPath;
            } else {
                $payload['factura_path'] = $documentoPath;
            }

            $ordenCompra->forceFill($payload)->save();

            if ($tipoDocumento === 'FACTURA' && filled($documentoPath)) {
                $this->notifyFinanzas($ordenCompra);
            }

            return $ordenCompra->fresh(['sumario.solicitudCompra.solicitadoPor']);
        });
    }

    private function isInvoiceAfterNotaPending(OrdenCompra $ordenCompra, string $tipoDocumento): bool
    {
        return $tipoDocumento === 'FACTURA'
            && (string) ($ordenCompra->tipo_documento_recepcion ?? '') === 'NOTA'
            && filled($ordenCompra->recepcion_procesada_at)
            && blank($ordenCompra->factura_cargada_administracion_at);
    }

    public function marcarZonaTransicionAlmacen(OrdenCompra $ordenCompra, User $user): OrdenCompra
    {
        return DB::transaction(function () use ($ordenCompra, $user): OrdenCompra {
            $ordenCompra = OrdenCompra::query()
                ->with(['items', 'sumario.solicitudCompra.solicitadoPor'])
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            if ($ordenCompra->recepcion_procesada_at) {
                return $ordenCompra;
            }

            if (blank($ordenCompra->tipo_documento_recepcion)) {
                throw new \RuntimeException('Procura debe cargar primero la factura o nota de entrega.');
            }

            $now = now();

            $ordenCompra->forceFill([
                'recepcion_procesada_at' => $now,
                'recibido_por_user_id' => $user->id,
                'workflow_post_compra' => 'EN_TRANSICION_ALMACEN',
            ])->save();

            $ordenCompra->items()->update([
                'estado_recepcion' => 'ZONA_TRANSICION',
                'en_transicion_at' => $now,
            ]);

            $this->notifySolicitante($ordenCompra);

            return $ordenCompra->fresh(['sumario.solicitudCompra.solicitadoPor']);
        });
    }

    public function procesarRecepcion(OrdenCompra $ordenCompra, User $user, string $tipoDocumento, ?string $documentoPath = null): OrdenCompra
    {
        $this->cargarDocumentoProcura($ordenCompra, $user, $tipoDocumento, $documentoPath);

        return $this->marcarZonaTransicionAlmacen($ordenCompra, $user);
    }

    private function notifySolicitante(OrdenCompra $ordenCompra): void
    {
        $solicitante = $ordenCompra->sumario?->solicitudCompra?->solicitadoPor;

        if (! $solicitante) {
            return;
        }

        $notification = Notification::make()
            ->title('Tu articulo ha llegado')
            ->body('La ODC ' . (string) $ordenCompra->correlativo_odc . ' ya esta en zona de transicion. Ingresa y presiona "Conformidad de Materiales" para aceptar o rechazar cada item.')
            ->success();

        DatabaseNotificationSender::sendNow($notification, $solicitante, dispatchEvent: true);
    }

    private function notifyFinanzas(OrdenCompra $ordenCompra): void
    {
        $departamentoId = Departamento::query()
            ->where('nombre', 'FINANZAS')
            ->value('id');

        $usuarios = User::query()
            ->when($departamentoId, fn ($query) => $query->where('departamento_id', $departamentoId))
            ->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        $url = OrdenCompraResource::getUrl('edit', ['record' => $ordenCompra]);

        $usuarios->each(function (User $financeUser) use ($ordenCompra, $url): void {
            $notification = Notification::make()
                ->title('Factura recibida desde Procura')
                ->body('La ODC ' . (string) $ordenCompra->correlativo_odc . ' tiene factura cargada. Validar y enviar a Administracion.')
                ->actions([
                    \Filament\Actions\Action::make('abrir')
                        ->label('Abrir ODC')
                        ->url($url)
                        ->button(),
                ])
                ->warning();

            DatabaseNotificationSender::sendNow($notification, $financeUser, dispatchEvent: true);
        });
    }
}
