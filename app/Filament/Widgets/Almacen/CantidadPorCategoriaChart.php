<?php

namespace App\Filament\Widgets\Almacen;

use App\Support\InventoryDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CantidadPorCategoriaChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Cantidad de activos por categoria';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = InventoryDashboardStats::getQuantityByCategory($this->pageFilters);

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Cantidad',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => '#0f766e',
                'borderColor' => '#115e59',
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