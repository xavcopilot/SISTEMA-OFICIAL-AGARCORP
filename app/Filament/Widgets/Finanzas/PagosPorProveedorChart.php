<?php

namespace App\Filament\Widgets\Finanzas;

use App\Support\FinanceDashboardStats;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PagosPorProveedorChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Pagos por proveedor';

    protected int | string | array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = FinanceDashboardStats::getPaymentsByProvider($this->pageFilters);

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Monto pagado',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => '#7c3aed',
                'borderColor' => '#6d28d9',
            ]],
        ];
    }

    protected function getOptions(): array | RawJs | null
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}