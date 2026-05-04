<?php

namespace App\Filament\Widgets\Procura;

use App\Support\ProcuraDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TiempoSolicitudASumarioChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Tiempo promedio solicitud a sumario';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = ProcuraDashboardStats::getRequestToSummaryTrend($this->pageFilters);

        if ($rows->isEmpty()) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [[
                    'label' => 'Dias promedio',
                    'data' => [0],
                    'backgroundColor' => 'rgba(14, 165, 233, 0.18)',
                    'borderColor' => '#0ea5e9',
                    'fill' => true,
                    'tension' => 0.25,
                ]],
            ];
        }

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Dias promedio',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => 'rgba(14, 165, 233, 0.18)',
                'borderColor' => '#0ea5e9',
                'fill' => true,
                'tension' => 0.25,
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
                        'label' => RawJs::make('(context) => context.label === "Sin datos" ? "Aun no hay solicitudes convertidas en sumario en el rango" : `${context.parsed.y} dias promedio`')
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}