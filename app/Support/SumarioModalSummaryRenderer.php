<?php

namespace App\Support;

class SumarioModalSummaryRenderer
{
    public static function render(mixed $record): string
    {
        $sumario = $record->loadMissing([
            'solicitudCompra',
            'elaboradoPor',
            'revisadoPor',
            'items.opciones',
        ]);

        return self::renderHeaderSummary($sumario)
            . '<div style="margin-bottom:8px;font-weight:700;">Cuadro comparativo de cotizaciones</div>'
            . self::renderComparativeTable($sumario)
            . self::renderFooterSummary($sumario)
            . self::renderGeneralCommentSummary($sumario);
    }

    public static function renderHeader(mixed $record): string
    {
        $sumario = $record->loadMissing([
            'solicitudCompra',
            'elaboradoPor',
            'revisadoPor',
            'items.opciones',
        ]);

        return self::renderHeaderSummary($sumario);
    }

    public static function renderFooter(mixed $record): string
    {
        $sumario = $record->loadMissing([
            'solicitudCompra',
            'elaboradoPor',
            'revisadoPor',
            'items.opciones',
        ]);

        return self::renderFooterSummary($sumario);
    }

    /**
     * @return array<int, float>
     */
    public static function selectedProviderTotals(mixed $sumario): array
    {
        return self::resolveSelectedProviderTotals($sumario);
    }

    public static function selectedProviderTotalForColumn(mixed $sumario, int $providerNumber): float
    {
        static $totalsCacheBySumarioId = [];

        if (! in_array($providerNumber, [1, 2, 3], true)) {
            return 0.0;
        }

        $sumarioId = (int) ($sumario->id ?? 0);

        if ($sumarioId <= 0) {
            return 0.0;
        }

        if (! array_key_exists($sumarioId, $totalsCacheBySumarioId)) {
            $totalsCacheBySumarioId[$sumarioId] = self::resolveSelectedProviderTotals($sumario);
        }

        return (float) ($totalsCacheBySumarioId[$sumarioId][$providerNumber] ?? 0.0);
    }

    /**
     * @return array<int, string>
     */
    public static function providerColumnNames(mixed $sumario): array
    {
        return self::resolveProviderColumnNames($sumario);
    }

    private static function renderHeaderSummary(mixed $sumario): string
    {
        return '<div style="margin-bottom:12px;border:1px solid #d1d5db;border-radius:10px;overflow:hidden;">'
            . '<div style="padding:10px 12px;background:#eef2ff;font-weight:700;">Encabezado</div>'
            . '<div style="padding:12px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;font-size:12px;">'
            . '<div><strong>Sumario N:</strong> ' . e((string) ($sumario->correlativo_sdc ?? '-')) . '</div>'
            . '<div><strong>Fecha:</strong> ' . e(optional($sumario->fecha)->format('d/m/Y')) . '</div>'
            . '<div><strong>Procedencia:</strong> ' . e((string) ($sumario->procedencia ?? '-')) . '</div>'
            . '<div><strong>Tipo de orden:</strong> ' . e((string) ($sumario->tipo_orden ?? '-')) . '</div>'
            . '<div><strong>Departamento:</strong> ' . e((string) ($sumario->departamento_solicitante ?? '-')) . '</div>'
            . '<div><strong>Condiciones de pago:</strong> ' . e((string) ($sumario->condiciones_pago ?? '-')) . '</div>'
            . '<div><strong>Tiempo de entrega:</strong> ' . e((string) ($sumario->tiempo_entrega ?? '-')) . '</div>'
            . '<div><strong>Solicitud asociada:</strong> ' . e((string) ($sumario->solicitudCompra?->codigo_control ?? $sumario->solicitud_compra_id ?? '-')) . '</div>'
            . '</div>'
            . '</div>';
    }

    private static function renderFooterSummary(mixed $sumario): string
    {
        $providerNames = self::resolveProviderColumnNames($sumario);
        $selectedTotals = self::resolveSelectedProviderTotals($sumario);

        return '<div style="margin-top:12px;border:1px solid #d1d5db;border-radius:10px;overflow:hidden;">'
            . '<div style="padding:10px 12px;background:#eef2ff;font-weight:700;">Pie del formato</div>'
            . '<div style="padding:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;font-size:12px;">'
            . '<div><strong>Total compra Prov. (' . e($providerNames[1]) . '):</strong> $ ' . number_format((float) ($selectedTotals[1] ?? 0), 2, ',', '.') . '</div>'
            . '<div><strong>Total compra Prov. (' . e($providerNames[2]) . '):</strong> $ ' . number_format((float) ($selectedTotals[2] ?? 0), 2, ',', '.') . '</div>'
            . '<div><strong>Total compra Prov. (' . e($providerNames[3]) . '):</strong> $ ' . number_format((float) ($selectedTotals[3] ?? 0), 2, ',', '.') . '</div>'
            . '<div><strong>Prioridad:</strong> ' . e(str_replace('_', ' ', (string) ($sumario->prioridad ?? '-'))) . '</div>'
            . '<div><strong>Elaborado por:</strong> ' . e((string) ($sumario->elaboradoPor?->name ?? '-')) . '</div>'
            . '<div><strong>Revisado por:</strong> ' . e((string) ($sumario->revisadoPor?->name ?? '-')) . '</div>'
            . '<div style="grid-column:1 / -1;"><strong>Observaciones:</strong><br>' . nl2br(e((string) ($sumario->observaciones ?? '-'))) . '</div>'
            . '</div>'
            . '</div>';
    }

    private static function renderGeneralCommentSummary(mixed $sumario): string
    {
        $generalComment = trim((string) ($sumario->decision_gerencia_comentario ?? ''));

        if ($generalComment === '') {
            return '';
        }

        return '<div style="margin-top:10px;border:1px solid #d1d5db;border-radius:10px;overflow:hidden;">'
            . '<div style="padding:10px 12px;background:#eef2ff;font-weight:700;">Comentario general de Gerencia</div>'
            . '<div style="padding:12px;white-space:pre-wrap;">' . nl2br(e($generalComment)) . '</div>'
            . '</div>';
    }

    /**
     * @return array<int, float>
     */
    private static function resolveSelectedProviderTotals(mixed $sumario): array
    {
        $totals = [
            1 => 0.0,
            2 => 0.0,
            3 => 0.0,
        ];

        foreach ($sumario->items ?? [] as $item) {
            $selectedOption = $item->opciones->firstWhere('seleccionada', true);
            $selectedProvider = (int) ($selectedOption?->opcion_numero ?? 0);

            if (! in_array($selectedProvider, [1, 2, 3], true)) {
                continue;
            }

            $totals[$selectedProvider] += (float) ($selectedOption?->precio_total ?? 0);
        }

        return $totals;
    }

    /**
     * @return array<int, string>
     */
    private static function resolveProviderColumnNames(mixed $sumario): array
    {
        $names = [
            1 => 'Proveedor 1',
            2 => 'Proveedor 2',
            3 => 'Proveedor 3',
        ];

        foreach ($sumario->items ?? [] as $item) {
            foreach ($item->opciones ?? [] as $opcion) {
                $number = (int) ($opcion->opcion_numero ?? 0);

                if (! in_array($number, [1, 2, 3], true)) {
                    continue;
                }

                $name = trim((string) ($opcion->proveedor_nombre ?? ''));
                if ($name !== '') {
                    $names[$number] = $name;
                }
            }
        }

        return $names;
    }

    private static function renderComparativeTable(mixed $sumario): string
    {
        $rows = '';
        $totalSeleccionadoProv1 = 0.0;
        $totalSeleccionadoProv2 = 0.0;
        $totalSeleccionadoProv3 = 0.0;

        foreach ($sumario->items as $sumarioItem) {
            $opciones = $sumarioItem->opciones->keyBy('opcion_numero');
            $selectedOption = $sumarioItem->opciones->firstWhere('seleccionada', true);
            $selectedOptionNumber = (int) ($selectedOption?->opcion_numero ?? 0);

            if ($selectedOptionNumber === 1) {
                $totalSeleccionadoProv1 += (float) ($opciones->get(1)?->precio_total ?? 0);
            } elseif ($selectedOptionNumber === 2) {
                $totalSeleccionadoProv2 += (float) ($opciones->get(2)?->precio_total ?? 0);
            } elseif ($selectedOptionNumber === 3) {
                $totalSeleccionadoProv3 += (float) ($opciones->get(3)?->precio_total ?? 0);
            }

            $styleProv1 = $selectedOptionNumber === 1
                ? 'border:1px solid #86efac;padding:8px;background:#dcfce7;'
                : 'border:1px solid #d1d5db;padding:8px;';
            $styleProv2 = $selectedOptionNumber === 2
                ? 'border:1px solid #86efac;padding:8px;background:#dcfce7;'
                : 'border:1px solid #d1d5db;padding:8px;';
            $styleProv3 = $selectedOptionNumber === 3
                ? 'border:1px solid #86efac;padding:8px;background:#dcfce7;'
                : 'border:1px solid #d1d5db;padding:8px;';
            $styleProv1Numeric = $selectedOptionNumber === 1
                ? 'border:1px solid #86efac;padding:8px;text-align:right;background:#dcfce7;'
                : 'border:1px solid #d1d5db;padding:8px;text-align:right;';
            $styleProv2Numeric = $selectedOptionNumber === 2
                ? 'border:1px solid #86efac;padding:8px;text-align:right;background:#dcfce7;'
                : 'border:1px solid #d1d5db;padding:8px;text-align:right;';
            $styleProv3Numeric = $selectedOptionNumber === 3
                ? 'border:1px solid #86efac;padding:8px;text-align:right;background:#dcfce7;'
                : 'border:1px solid #d1d5db;padding:8px;text-align:right;';

            $rows .= '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $sumarioItem->descripcion) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $sumarioItem->unidad_medida) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;white-space:nowrap;">' . number_format((float) $sumarioItem->cantidad, 2, ',', '.') . '</td>'
                . '<td style="' . $styleProv1 . '">' . e((string) ($opciones->get(1)?->proveedor_nombre ?? '-')) . '</td>'
                . '<td style="' . $styleProv1 . '">' . e((string) ($opciones->get(1)?->marca ?? '-')) . '</td>'
                . '<td style="' . $styleProv1Numeric . 'white-space:nowrap;">' . number_format((float) ($opciones->get(1)?->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="' . $styleProv1Numeric . 'white-space:nowrap;">' . number_format((float) ($opciones->get(1)?->precio_total ?? 0), 2, ',', '.') . '</td>'
                . '<td style="' . $styleProv2 . '">' . e((string) ($opciones->get(2)?->proveedor_nombre ?? '-')) . '</td>'
                . '<td style="' . $styleProv2 . '">' . e((string) ($opciones->get(2)?->marca ?? '-')) . '</td>'
                . '<td style="' . $styleProv2Numeric . 'white-space:nowrap;">' . number_format((float) ($opciones->get(2)?->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="' . $styleProv2Numeric . 'white-space:nowrap;">' . number_format((float) ($opciones->get(2)?->precio_total ?? 0), 2, ',', '.') . '</td>'
                . '<td style="' . $styleProv3 . '">' . e((string) ($opciones->get(3)?->proveedor_nombre ?? '-')) . '</td>'
                . '<td style="' . $styleProv3 . '">' . e((string) ($opciones->get(3)?->marca ?? '-')) . '</td>'
                . '<td style="' . $styleProv3Numeric . 'white-space:nowrap;">' . number_format((float) ($opciones->get(3)?->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="' . $styleProv3Numeric . 'white-space:nowrap;">' . number_format((float) ($opciones->get(3)?->precio_total ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($sumarioItem->validacion_gerencia_resultado === 'RECHAZADO' ? 'X' : ($sumarioItem->validacion_gerencia_resultado === 'CORRECTO' ? 'Correcto' : '-'))) . '</td>'
                . '</tr>';
        }

        $headerInfo = '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:12px;">'
            . '<div><strong>Sumario:</strong> ' . e((string) $sumario->correlativo_sdc) . '</div>'
            . '<div><strong>Fecha:</strong> ' . e(optional($sumario->fecha)->format('d/m/Y')) . '</div>'
            . '<div><strong>Estado:</strong> ' . e(str_replace('_', ' ', (string) $sumario->estado)) . '</div>'
            . '<div><strong>Moneda:</strong> $ USD</div>'
            . '</div>';

        $table = '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead>'
            . '<tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">UND</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cantidad</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Proveedor 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Marca 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/U 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/T 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Proveedor 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Marca 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/U 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/T 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Proveedor 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Marca 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/U 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/T 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Gerencia</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot>'
            . '<tr style="background:#f9fafb;font-weight:600;">'
            . '<td colspan="3" style="border:1px solid #d1d5db;padding:8px;"></td>'
            . '<td colspan="4" style="border:1px solid #d1d5db;padding:8px;text-align:center;white-space:nowrap;">Total compra Proveedor 1: <strong>$ ' . number_format($totalSeleccionadoProv1, 2, ',', '.') . '</strong></td>'
            . '<td colspan="4" style="border:1px solid #d1d5db;padding:8px;text-align:center;white-space:nowrap;">Total compra Proveedor 2: <strong>$ ' . number_format($totalSeleccionadoProv2, 2, ',', '.') . '</strong></td>'
            . '<td colspan="4" style="border:1px solid #d1d5db;padding:8px;text-align:center;white-space:nowrap;">Total compra Proveedor 3: <strong>$ ' . number_format($totalSeleccionadoProv3, 2, ',', '.') . '</strong></td>'
            . '<td colspan="1" style="border:1px solid #d1d5db;padding:8px;"></td>'
            . '</tr>'
            . '</tfoot>'
            . '</table>'
            . '</div>';

        return $headerInfo . $table;
    }
}
