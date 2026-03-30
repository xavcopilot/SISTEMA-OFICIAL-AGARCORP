<?php

namespace App\Filament\Widgets\Almacen;

use App\Support\InventoryDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CantidadAsignadaPorDepartamentoChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Cantidad asignada por departamentos';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = InventoryDashboardStats::getAssignedByDepartment($this->pageFilters);

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Asignado',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => '#7c2d12',
                'borderColor' => '#9a3412',
            ]],
        ];
    }

    protected function getOptions(): array | RawJs | null
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}