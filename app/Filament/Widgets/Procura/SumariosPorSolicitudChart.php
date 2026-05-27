<?php

namespace App\Filament\Widgets\Procura;

use App\Support\ProcuraDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SumariosPorSolicitudChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Cantidad de sumarios por solicitud';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = ProcuraDashboardStats::getSummariesPerRequest($this->pageFilters);

        if ($rows->isEmpty()) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [[
                    'label' => 'Sumarios',
                    'data' => [0],
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                ]],
            ];
        }

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Sumarios',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => '#10b981',
                'borderColor' => '#059669',
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
                        'label' => RawJs::make('(context) => context.label === "Sin datos" ? "Aun no hay solicitudes con sumarios en el rango" : `${context.parsed.y} sumarios`')
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