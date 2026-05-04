<?php

namespace App\Filament\Widgets\Procura;

use App\Support\ProcuraDashboardStats;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenProcuraStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Resumen general de procura';

    protected function getStats(): array
    {
        $summary = ProcuraDashboardStats::getSummary($this->pageFilters);

        return [
            Stat::make('Solicitud a sumario', number_format($summary['average_request_to_summary_days'], 2, ',', '.') . ' dias')
                ->description('Tiempo promedio hasta el primer analisis de cotizacion')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('info'),

            Stat::make('Sumario a ODC', number_format($summary['average_summary_to_order_days'], 2, ',', '.') . ' dias')
                ->description('Tiempo promedio hasta la primera orden de compra')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Sumarios por solicitud', number_format($summary['average_summaries_per_request'], 2, ',', '.'))
                ->description('Promedio de fragmentacion por necesidad')
                ->descriptionIcon('heroicon-m-rectangle-group')
                ->color('success'),

            Stat::make('ODC por sumario', number_format($summary['average_orders_per_summary'], 2, ',', '.'))
                ->description('Promedio de dispersion operativa por sumario')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('gray'),
        ];
    }
}