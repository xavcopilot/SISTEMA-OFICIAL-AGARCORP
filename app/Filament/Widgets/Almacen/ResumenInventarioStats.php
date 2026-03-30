<?php

namespace App\Filament\Widgets\Almacen;

use App\Support\InventoryDashboardStats;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenInventarioStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Resumen general';

    protected function getStats(): array
    {
        $summary = InventoryDashboardStats::getSummary($this->pageFilters);

        return [
            Stat::make('Numero de items', number_format($summary['item_count']))
                ->description('SKUs registrados en el inventario')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('info'),

            Stat::make('Cantidad total', number_format($summary['quantity_total']))
                ->description('Unidades disponibles en almacen')
                ->descriptionIcon('heroicon-m-cube')
                ->color('success'),

            Stat::make('Activos totales', '$ ' . number_format($summary['assets_total'], 2, ',', '.'))
                ->description('Valor total estimado del inventario')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}