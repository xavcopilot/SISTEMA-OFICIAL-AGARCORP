<?php

namespace App\Support;

use App\Models\OrdenCompra;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OrdenCompraAdministracionService
{
    public function registrarDatosFactura(OrdenCompra $ordenCompra, User $user, array $data): OrdenCompra
    {
        return DB::transaction(function () use ($ordenCompra, $user, $data): OrdenCompra {
            $ordenCompra = OrdenCompra::query()
                ->with(['sumario.solicitudCompra'])
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            $ordenCompra->forceFill([
                'factura_numero' => trim((string) ($data['factura_numero'] ?? '')),
                'factura_numero_control' => trim((string) ($data['factura_numero_control'] ?? '')),
                'factura_fecha_emision' => $data['factura_fecha_emision'] ?? null,
                'factura_base_imponible' => round((float) ($data['factura_base_imponible'] ?? 0), 2),
                'factura_monto_iva' => round((float) ($data['factura_monto_iva'] ?? 0), 2),
                'factura_monto_total' => round((float) ($data['factura_monto_total'] ?? 0), 2),
                'retencion_iva_monto' => round((float) ($data['retencion_iva_monto'] ?? 0), 2),
                'retencion_islr_monto' => round((float) ($data['retencion_islr_monto'] ?? 0), 2),
                'comprobantes_retencion_paths' => array_values(array_filter(Arr::wrap($data['comprobantes_retencion_paths'] ?? []))),
                'observacion_administracion' => trim((string) ($data['observacion_administracion'] ?? '')),
                'factura_cargada_administracion_at' => now(),
                'factura_cargada_por_user_id' => $user->id,
                'factura_procesada_administracion_at' => now(),
                'workflow_post_compra' => 'BACKUP_FACTURA_COMPLETADO',
            ])->save();

            app(SolicitudCompraCompletionService::class)->syncFromOrdenCompra($ordenCompra);

            return $ordenCompra->fresh(['sumario.solicitudCompra']);
        });
    }
}
