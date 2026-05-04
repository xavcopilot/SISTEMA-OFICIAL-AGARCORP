<?php

namespace App\Filament\Widgets\Finanzas;

use App\Support\FinanceDashboardStats;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenFinanzasStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Resumen general de finanzas';

    protected function getStats(): array
    {
        $summary = FinanceDashboardStats::getSummary($this->pageFilters);

        return [
            Stat::make('Ordenes pagadas', number_format($summary['paid_orders']))
                ->description('ODC ya atendidas por Finanzas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Pendientes de pago', number_format($summary['pending_orders']))
                ->description('Backlog actual de pagos por registrar')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Facturas cargadas', number_format($summary['loaded_invoices']))
                ->description('Compras cerradas documentalmente')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('info'),

            Stat::make('Tiempo promedio documental', number_format($summary['average_document_days'], 2, ',', '.') . ' dias')
                ->description('De documentacion enviada a factura cargada')
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color('gray'),
        ];
    }
}