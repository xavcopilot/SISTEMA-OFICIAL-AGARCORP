<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Almacen\CantidadAsignadaPorDepartamentoChart;
use App\Filament\Widgets\Almacen\CantidadPorCategoriaChart;
use App\Filament\Widgets\Almacen\ConsumoPorCategoriaChart;
use App\Filament\Widgets\Almacen\ConsumoPorDepartamentoChart;
use App\Filament\Widgets\Almacen\ResumenInventarioStats;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use UnitEnum;

class AlmacenDashboard extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'inventario/dashboard';

    protected static ?string $title = 'Dashboard de Almacen';

    protected static string | UnitEnum | null $navigationGroup = 'Inventario';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        $user = auth()->user();

        if ($user && $user->hasRole('Alta Gerencia')) {
            return 'Dashboard';
        }

        return 'Inventario';
    }

    protected static ?string $navigationLabel = 'Dashboard de Almacen';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Almacen') || $user->hasRole('Alta Gerencia');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('desde')
                ->label('Desde'),
            DatePicker::make('hasta')
                ->label('Hasta'),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            ResumenInventarioStats::class,
            CantidadPorCategoriaChart::class,
            ConsumoPorCategoriaChart::class,
            CantidadAsignadaPorDepartamentoChart::class,
            ConsumoPorDepartamentoChart::class,
        ];
    }

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}