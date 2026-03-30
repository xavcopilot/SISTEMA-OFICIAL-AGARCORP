<?php

namespace App\Filament\Widgets\Almacen;

use App\Support\InventoryDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ConsumoPorDepartamentoChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Consumo total por departamentos';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = InventoryDashboardStats::getConsumptionByDepartment($this->pageFilters);

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Consumo',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => '#be123c',
                'borderColor' => '#9f1239',
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