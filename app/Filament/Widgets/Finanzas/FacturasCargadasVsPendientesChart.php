<?php

namespace App\Filament\Widgets\Finanzas;

use App\Support\FinanceDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FacturasCargadasVsPendientesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Facturas cargadas vs documentacion pendiente';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $summary = FinanceDashboardStats::getDocumentationSummary($this->pageFilters);

        return [
            'labels' => ['Facturas cargadas', 'Documentacion pendiente'],
            'datasets' => [[
                'label' => 'ODC',
                'data' => [$summary['loaded'], $summary['pending']],
                'backgroundColor' => ['#0ea5e9', '#ef4444'],
                'borderColor' => ['#0284c7', '#dc2626'],
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
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}