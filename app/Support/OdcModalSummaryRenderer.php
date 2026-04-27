<?php

namespace App\Support;

class OdcModalSummaryRenderer
{
    public static function render(mixed $ordenCompra): string
    {
        $odc = $ordenCompra->loadMissing([
            'sumario.solicitudCompra',
            'proveedor',
            'items',
            'elaboradoPor.cargo',
            'aprobadoPor.cargo',
        ]);

        $rows = '';

        foreach ($odc->items ?? [] as $item) {
            $rows .= '<tr>'
                . '<td style="border:1px solid #bbf7d0;padding:8px;text-align:center;">' . e((string) ($item->item ?: $item->id)) . '</td>'
                . '<td style="border:1px solid #bbf7d0;padding:8px;">' . e((string) ($item->descripcion ?? '-')) . '</td>'
                . '<td style="border:1px solid #bbf7d0;padding:8px;text-align:center;">' . e((string) ($item->unidad_medida ?? 'UND')) . '</td>'
                . '<td style="border:1px solid #bbf7d0;padding:8px;text-align:right;">' . number_format((float) ($item->cantidad ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #bbf7d0;padding:8px;text-align:right;">' . number_format((float) ($item->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #bbf7d0;padding:8px;text-align:right;">' . number_format((float) ($item->precio_total ?? 0), 2, ',', '.') . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" style="border:1px solid #bbf7d0;padding:8px;color:#6b7280;">Sin items en esta ODC.</td></tr>';
        }

        return '<div style="display:flex;flex-direction:column;gap:12px;">'
            . '<div style="border:1px solid #d1d5db;border-radius:10px;overflow:hidden;">'
            . '<div style="padding:10px 12px;background:#eef2ff;font-weight:700;">Encabezado ODC</div>'
            . '<div style="padding:12px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;font-size:12px;">'
            . '<div><strong>Orden de compra N:</strong> ' . e((string) ($odc->correlativo_odc ?? '-')) . '</div>'
            . '<div><strong>Fecha:</strong> ' . e((string) optional($odc->created_at)->format('d/m/Y')) . '</div>'
            . '<div><strong>Asociado a sumario:</strong> ' . e((string) ($odc->sumario?->correlativo_sdc ?? '-')) . '</div>'
            . '<div><strong>Solicitud asociada:</strong> ' . e((string) ($odc->sumario?->solicitudCompra?->codigo_control ?? '-')) . '</div>'
            . '<div><strong>Proveedor:</strong> ' . e((string) ($odc->proveedor?->nombre ?? '-')) . '</div>'
            . '<div><strong>RIF proveedor:</strong> ' . e((string) ($odc->rif_proveedor ?? '-')) . '</div>'
            . '<div><strong>Contacto:</strong> ' . e((string) ($odc->contacto_proveedor ?? '-')) . '</div>'
            . '<div><strong>Email:</strong> ' . e((string) ($odc->email_proveedor ?? '-')) . '</div>'
            . '<div style="grid-column:1 / -1;"><strong>Para ser usado en:</strong> ' . e((string) ($odc->sumario?->solicitudCompra?->para_ser_usado_en ?? '-')) . '</div>'
            . '</div>'
            . '</div>'

            . '<div>'
            . '<div style="margin-bottom:8px;font-weight:700;">Detalle de productos ODC</div>'
            . '<div style="overflow:auto;border:1px solid #86efac;border-radius:10px;background:#f0fdf4;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#dcfce7;">'
            . '<th style="border:1px solid #bbf7d0;padding:8px;">Codigo</th>'
            . '<th style="border:1px solid #bbf7d0;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #bbf7d0;padding:8px;">UND</th>'
            . '<th style="border:1px solid #bbf7d0;padding:8px;">Cantidad</th>'
            . '<th style="border:1px solid #bbf7d0;padding:8px;">Valor unitario</th>'
            . '<th style="border:1px solid #bbf7d0;padding:8px;">Valor total</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot>'
            . '<tr style="background:#dcfce7;font-weight:600;">'
            . '<td colspan="4" style="border:1px solid #bbf7d0;padding:8px;text-align:right;">Sub total</td>'
            . '<td colspan="2" style="border:1px solid #bbf7d0;padding:8px;text-align:right;">$ ' . number_format((float) ($odc->sub_total ?? 0), 2, ',', '.') . '</td>'
            . '</tr>'
            . '<tr style="background:#dcfce7;font-weight:600;">'
            . '<td colspan="4" style="border:1px solid #bbf7d0;padding:8px;text-align:right;">IVA 16%</td>'
            . '<td colspan="2" style="border:1px solid #bbf7d0;padding:8px;text-align:right;">$ ' . number_format((float) ($odc->iva_16 ?? 0), 2, ',', '.') . '</td>'
            . '</tr>'
            . '<tr style="background:#bbf7d0;font-weight:700;">'
            . '<td colspan="4" style="border:1px solid #86efac;padding:8px;text-align:right;">Total general</td>'
            . '<td colspan="2" style="border:1px solid #86efac;padding:8px;text-align:right;">$ ' . number_format((float) ($odc->total_general ?? 0), 2, ',', '.') . '</td>'
            . '</tr>'
            . '</tfoot>'
            . '</table>'
            . '</div>'
            . '</div>'

            . '<div style="border:1px solid #d1d5db;border-radius:10px;overflow:hidden;">'
            . '<div style="padding:10px 12px;background:#eef2ff;font-weight:700;">Control y firmas</div>'
            . '<div style="padding:12px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;font-size:12px;">'
            . '<div><strong>Estado:</strong> ' . e(str_replace('_', ' ', (string) ($odc->estado ?? '-'))) . '</div>'
            . '<div><strong>Sitio de entrega:</strong> ' . e((string) ($odc->sitio_entrega ?? '-')) . '</div>'
            . '<div><strong>Tasa BCV:</strong> ' . e((string) ($odc->tasa_bcv ?? '-')) . '</div>'
            . '<div><strong>Condicion de pago:</strong> ' . e((string) ($odc->condicion_pago ?? '-')) . '</div>'
            . '<div><strong>Elaborado por:</strong> ' . e((string) ($odc->elaboradoPor?->name ?? '-')) . '</div>'
            . '<div><strong>Cargo elaborado:</strong> ' . e((string) ($odc->elaboradoPor?->cargo?->nombre ?? '-')) . '</div>'
            . '<div><strong>Aprobado por:</strong> ' . e((string) ($odc->aprobadoPor?->name ?? '-')) . '</div>'
            . '<div><strong>Cargo aprobado:</strong> ' . e((string) ($odc->aprobadoPor?->cargo?->nombre ?? '-')) . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
