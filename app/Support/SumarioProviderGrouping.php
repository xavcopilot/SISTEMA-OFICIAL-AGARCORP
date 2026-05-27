<?php

namespace App\Support;

class SumarioProviderGrouping
{
    /**
     * @param  array<int, string|null>  $providerNames
     * @return array{
     *     raw_names: array<int, string>,
     *     selection_options: array<string, string>,
     *     column_to_slot: array<string, int>,
     *     total_labels: array<int, string>,
     *     total_visible: array<int, bool>,
     *     slot_to_columns: array<int, array<int, string>>
     * }
     */
    public static function build(array $providerNames): array
    {
        $rawNames = [
            1 => trim((string) ($providerNames[1] ?? '')),
            2 => trim((string) ($providerNames[2] ?? '')),
            3 => trim((string) ($providerNames[3] ?? '')),
        ];

        $duplicateTotals = [];

        foreach ($rawNames as $name) {
            $normalized = self::normalizeProviderName($name);

            if ($normalized === '') {
                continue;
            }

            $duplicateTotals[$normalized] = ($duplicateTotals[$normalized] ?? 0) + 1;
        }

        $selectionOptions = [
            '' => 'Sin seleccionar',
        ];
        $columnToSlot = [];
        $slotToColumns = [1 => [], 2 => [], 3 => []];
        $totalLabels = [1 => '', 2 => '', 3 => ''];
        $totalVisible = [1 => false, 2 => false, 3 => false];

        $occurrenceByNormalized = [];
        $groupSlotByKey = [];
        $nextSlot = 1;

        foreach ([1, 2, 3] as $number) {
            $column = self::numberToColumn($number);
            $rawName = $rawNames[$number];
            $fallbackName = 'Proveedor ' . $number;
            $displayName = $rawName !== '' ? $rawName : $fallbackName;
            $normalized = self::normalizeProviderName($rawName);
            $groupKey = $normalized !== '' ? $normalized : '__blank_' . $number;

            if (! isset($groupSlotByKey[$groupKey])) {
                $groupSlotByKey[$groupKey] = $nextSlot;
                $totalLabels[$nextSlot] = $displayName;
                $totalVisible[$nextSlot] = true;
                $nextSlot++;
            }

            $slot = $groupSlotByKey[$groupKey];
            $columnToSlot[$column] = $slot;
            $slotToColumns[$slot][] = $column;

            if ($normalized !== '' && ($duplicateTotals[$normalized] ?? 0) > 1) {
                $occurrenceByNormalized[$normalized] = ($occurrenceByNormalized[$normalized] ?? 0) + 1;
                $displayName .= ' (Tipo ' . $occurrenceByNormalized[$normalized] . ')';
            }

            $selectionOptions[$column] = $displayName;
        }

        return [
            'raw_names' => $rawNames,
            'selection_options' => $selectionOptions,
            'column_to_slot' => $columnToSlot,
            'total_labels' => $totalLabels,
            'total_visible' => $totalVisible,
            'slot_to_columns' => $slotToColumns,
        ];
    }

    /**
     * @param  array<int, string|null>  $providerNames
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, float>
     */
    public static function groupedTotalsFromRows(array $providerNames, array $rows): array
    {
        $config = self::build($providerNames);
        $totals = [1 => 0.0, 2 => 0.0, 3 => 0.0];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $selectedColumn = self::normalizeSelectedColumn((string) ($row['proveedor_seleccionado'] ?? ''));

            if ($selectedColumn === null) {
                continue;
            }

            $providerNumber = self::columnToNumber($selectedColumn);
            $slot = $config['column_to_slot'][$selectedColumn] ?? $providerNumber;
            $priceKey = 'precio_total_prov' . $providerNumber;

            $totals[$slot] += filled($row[$priceKey] ?? null) ? (float) $row[$priceKey] : 0.0;
        }

        return [
            1 => round($totals[1], 2),
            2 => round($totals[2], 2),
            3 => round($totals[3], 2),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function providerNamesFromSumario(mixed $sumario): array
    {
        $names = [1 => '', 2 => '', 3 => ''];

        foreach ($sumario->items ?? [] as $item) {
            foreach ($item->opciones ?? [] as $opcion) {
                $number = (int) ($opcion->opcion_numero ?? 0);

                if (! in_array($number, [1, 2, 3], true) || $names[$number] !== '') {
                    continue;
                }

                $names[$number] = trim((string) ($opcion->proveedor_nombre ?: $opcion->proveedor?->nombre ?: ''));
            }
        }

        return $names;
    }

    /**
     * @return array<int, float>
     */
    public static function groupedTotalsFromSumario(mixed $sumario): array
    {
        $providerNames = self::providerNamesFromSumario($sumario);
        $config = self::build($providerNames);
        $totals = [1 => 0.0, 2 => 0.0, 3 => 0.0];

        foreach ($sumario->items ?? [] as $item) {
            $selectedOption = $item->opciones->firstWhere('seleccionada', true);
            $number = (int) ($selectedOption?->opcion_numero ?? 0);

            if (! in_array($number, [1, 2, 3], true)) {
                continue;
            }

            $column = self::numberToColumn($number);
            $slot = $config['column_to_slot'][$column] ?? $number;
            $totals[$slot] += (float) ($selectedOption?->precio_total ?? 0);
        }

        return [
            1 => round($totals[1], 2),
            2 => round($totals[2], 2),
            3 => round($totals[3], 2),
        ];
    }

    public static function totalLabel(array $providerNames, int $slot): string
    {
        return self::build($providerNames)['total_labels'][$slot] ?? '';
    }

    public static function totalVisible(array $providerNames, int $slot): bool
    {
        return (bool) (self::build($providerNames)['total_visible'][$slot] ?? false);
    }

    /**
     * @param  array<int, string|null>  $providerNames
     * @return array<string, string>
     */
    public static function selectionOptions(array $providerNames): array
    {
        return self::build($providerNames)['selection_options'];
    }

    public static function normalizeSelectedColumn(string $selected): ?string
    {
        $selected = strtoupper(trim($selected));

        return match ($selected) {
            '1', 'A' => 'A',
            '2', 'B' => 'B',
            '3', 'C' => 'C',
            default => null,
        };
    }

    public static function numberToColumn(int $number): string
    {
        return match ($number) {
            1 => 'A',
            2 => 'B',
            default => 'C',
        };
    }

    public static function columnToNumber(string $column): int
    {
        return match (strtoupper($column)) {
            'A' => 1,
            'B' => 2,
            default => 3,
        };
    }

    private static function normalizeProviderName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $name = preg_replace('/\s+/', ' ', $name) ?: $name;

        return mb_strtolower($name);
    }
}