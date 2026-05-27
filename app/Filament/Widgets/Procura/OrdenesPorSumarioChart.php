<?php

namespace App\Filament\Widgets\Procura;

use App\Support\ProcuraDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OrdenesPorSumarioChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Ordenes de compra por sumario';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = ProcuraDashboardStats::getOrdersPerSummary($this->pageFilters);

        if ($rows->isEmpty()) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [[
                    'label' => 'ODC',
                    'data' => [0],
                    'backgroundColor' => '#64748b',
                    'borderColor' => '#475569',
                ]],
            ];
        }

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'ODC',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => '#64748b',
                'borderColor' => '#475569',
            ]],
        ];
    }

    protected function getOptions(): array | RawJs | null
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => RawJs::make('(context) => context.label === "Sin datos" ? "Aun no hay sumarios con ODC en el rango" : `${context.parsed.y} ordenes de compra`')
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}