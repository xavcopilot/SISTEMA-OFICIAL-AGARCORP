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
            'validadoPor',
            'decisionGerenciaPor',
            'items.opciones',
        ]);

        return self::renderHeaderSummary($sumario)
            . '<div style="margin-bottom:8px;font-weight:700;">Cuadro comparativo de cotizaciones</div>'
            . self::renderComparativeTable($sumario)
            . self::renderFooterSummary($sumario)
            . self::renderRejectionSummary($sumario)
            . self::renderGeneralCommentSummary($sumario);
    }

    public static function renderHeader(mixed $record): string
    {
        $sumario = $record->loadMissing([
            'solicitudCompra',
            'elaboradoPor',
            'revisadoPor',
            'validadoPor',
            'decisionGerenciaPor',
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
            'validadoPor',
            'decisionGerenciaPor',
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
        $groupedConfig = SumarioProviderGrouping::build($providerNames);
        $selectedTotals = self::resolveSelectedProviderTotals($sumario);
        $totalsHtml = '';

        foreach ([1, 2, 3] as $slot) {
            if (! ($groupedConfig['total_visible'][$slot] ?? false)) {
                continue;
            }

            $totalsHtml .= '<div><strong>Total compra Prov. (' . e($groupedConfig['total_labels'][$slot] ?? ('Proveedor ' . $slot)) . '):</strong> $ ' . number_format((float) ($selectedTotals[$slot] ?? 0), 2, ',', '.') . '</div>';
        }

        return '<div style="margin-top:12px;border:1px solid #d1d5db;border-radius:10px;overflow:hidden;">'
            . '<div style="padding:10px 12px;background:#eef2ff;font-weight:700;">Pie del formato</div>'
            . '<div style="padding:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;font-size:12px;">'
            . $totalsHtml
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

    private static function renderRejectionSummary(mixed $sumario): string
    {
        $rejection = self::resolveRejectionSummaryData($sumario);

        if ($rejection === null) {
            return '';
        }

        return '<div style="margin-top:10px;border:1px solid #fecaca;border-radius:10px;overflow:hidden;">'
            . '<div style="padding:10px 12px;background:#fef2f2;font-weight:700;color:#991b1b;">Detalle del rechazo</div>'
            . '<div style="padding:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;font-size:12px;">'
            . '<div><strong>Rechazado por:</strong> ' . e($rejection['user']) . '</div>'
            . '<div><strong>Area:</strong> ' . e($rejection['stage']) . '</div>'
            . '<div><strong>Resultado:</strong> RECHAZADO</div>'
            . '<div style="grid-column:1 / -1;"><strong>Motivo de rechazo:</strong><br>' . nl2br(e($rejection['comment'])) . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * @return array{user:string,stage:string,comment:string}|null
     */
    private static function resolveRejectionSummaryData(mixed $sumario): ?array
    {
        $gerenciaComment = trim((string) ($sumario->decision_gerencia_comentario ?? ''));
        $finanzasComment = trim((string) ($sumario->validacion_finanzas_comentario ?? ''));

        if ((string) ($sumario->decision_gerencia_resultado ?? '') === 'RECHAZADO' || $gerenciaComment !== '') {
            return [
                'user' => (string) ($sumario->decisionGerenciaPor?->name ?? 'No registrado'),
                'stage' => 'Gerencia de Finanzas',
                'comment' => $gerenciaComment !== '' ? $gerenciaComment : 'Sin motivo registrado.',
            ];
        }

        if ((string) ($sumario->validacion_finanzas_resultado ?? '') === 'RECHAZADO' || $finanzasComment !== '') {
            return [
                'user' => (string) ($sumario->validadoPor?->name ?? 'No registrado'),
                'stage' => 'Validacion Finanzas',
                'comment' => $finanzasComment !== '' ? $finanzasComment : 'Sin motivo registrado.',
            ];
        }

        return null;
    }

    /**
     * @return array<int, float>
     */
    private static function resolveSelectedProviderTotals(mixed $sumario): array
    {
        return SumarioProviderGrouping::groupedTotalsFromSumario($sumario);
    }

    /**
     * @return array<int, string>
     */
    private static function resolveProviderColumnNames(mixed $sumario): array
    {
        return SumarioProviderGrouping::providerNamesFromSumario($sumario);
    }

    private static function renderComparativeTable(mixed $sumario): string
    {
        $rows = '';
        $groupedProviderConfig = SumarioProviderGrouping::build(self::resolveProviderColumnNames($sumario));
        $groupedTotals = SumarioProviderGrouping::groupedTotalsFromSumario($sumario);

        foreach ($sumario->items as $sumarioItem) {
            $opciones = $sumarioItem->opciones->keyBy('opcion_numero');
            $selectedOption = $sumarioItem->opciones->firstWhere('seleccionada', true);
            $selectedOptionNumber = (int) ($selectedOption?->opcion_numero ?? 0);

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

        $footerTotals = '';

        foreach ([1, 2, 3] as $slot) {
            if (! ($groupedProviderConfig['total_visible'][$slot] ?? false)) {
                continue;
            }

            $footerTotals .= '<td colspan="4" style="border:1px solid #d1d5db;padding:8px;text-align:center;white-space:nowrap;">Total compra ' . e($groupedProviderConfig['total_labels'][$slot] ?? ('Proveedor ' . $slot)) . ': <strong>$ ' . number_format((float) ($groupedTotals[$slot] ?? 0), 2, ',', '.') . '</strong></td>';
        }

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
            . $footerTotals
            . '<td colspan="1" style="border:1px solid #d1d5db;padding:8px;"></td>'
            . '</tr>'
            . '</tfoot>'
            . '</table>'
            . '</div>';

        return $headerInfo . $table;
    }
}
