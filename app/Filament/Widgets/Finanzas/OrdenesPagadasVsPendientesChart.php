<?php

namespace App\Filament\Widgets\Finanzas;

use App\Support\FinanceDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OrdenesPagadasVsPendientesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Ordenes pagadas vs pendientes de pago';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $summary = FinanceDashboardStats::getPaymentSummary($this->pageFilters);

        return [
            'labels' => ['Pagadas', 'Pendientes'],
            'datasets' => [[
                'label' => 'ODC',
                'data' => [$summary['paid'], $summary['pending']],
                'backgroundColor' => ['#16a34a', '#f59e0b'],
                'borderColor' => ['#15803d', '#d97706'],
            ]],
        ];
    }

    protected function getOptions(): array | RawJs | null
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}