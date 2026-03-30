<?php

namespace App\Filament\Widgets\Almacen;

use App\Support\InventoryDashboardStats;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ConsumoPorCategoriaChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Consumo total por categoria';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = InventoryDashboardStats::getConsumptionByCategory($this->pageFilters);

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Consumo',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => ['#1d4ed8', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#7c3aed', '#0891b2', '#15803d', '#ca8a04', '#b91c1c'],
            ]],
        ];
    }
}