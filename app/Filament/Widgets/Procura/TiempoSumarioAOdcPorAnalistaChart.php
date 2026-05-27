<?php

namespace App\Filament\Widgets\Procura;

use App\Support\ProcuraDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TiempoSumarioAOdcPorAnalistaChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Tiempo promedio sumario a ODC por analista (dias)';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = ProcuraDashboardStats::getSummaryToOrderByAnalyst($this->pageFilters);

        if ($rows->isEmpty()) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [[
                    'label' => 'Dias promedio',
                    'data' => [0],
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                ]],
            ];
        }

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Dias promedio',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#d97706',
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
                        'label' => RawJs::make('(context) => context.label === "Sin datos" ? "Aun no hay sumarios convertidos en ODC en el rango" : `${context.parsed.y} dias promedio`')
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Dias',
                    ],
                ],
            ],
        ];
    }
}