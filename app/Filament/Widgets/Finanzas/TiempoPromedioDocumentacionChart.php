<?php

namespace App\Filament\Widgets\Finanzas;

use App\Support\FinanceDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TiempoPromedioDocumentacionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Tiempo promedio documentacion a factura cargada';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = FinanceDashboardStats::getAverageDocumentationClosureTrend($this->pageFilters);

        if ($rows->isEmpty()) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [[
                    'label' => 'Dias promedio',
                    'data' => [0],
                    'backgroundColor' => 'rgba(148, 163, 184, 0.18)',
                    'borderColor' => '#94a3b8',
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
                'backgroundColor' => 'rgba(37, 99, 235, 0.18)',
                'borderColor' => '#2563eb',
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
                        'label' => RawJs::make("(context) => context.label === 'Sin datos' ? 'Aun no hay cierres documentales en el rango' : `${context.parsed.y} dias promedio`")
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