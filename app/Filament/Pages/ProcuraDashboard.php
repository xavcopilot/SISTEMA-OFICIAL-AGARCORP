<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Procura\OrdenesPorSumarioChart;
use App\Filament\Widgets\Procura\ResumenProcuraStats;
use App\Filament\Widgets\Procura\SumariosPorSolicitudChart;
use App\Filament\Widgets\Procura\TiempoSolicitudASumarioChart;
use App\Filament\Widgets\Procura\TiempoSumarioAOdcPorAnalistaChart;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ProcuraDashboard extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'procura/dashboard';

    protected static ?string $title = 'Dashboard de Procura';

    protected static string | UnitEnum | null $navigationGroup = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard de Procura';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('Procura') || $user->hasRole('Alta Gerencia'));
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        $user = auth()->user();

        if ($user && $user->hasRole('Alta Gerencia')) {
            return 'Dashboard';
        }

        return 'Dashboard';
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
            ResumenProcuraStats::class,
            TiempoSolicitudASumarioChart::class,
            TiempoSumarioAOdcPorAnalistaChart::class,
            SumariosPorSolicitudChart::class,
            OrdenesPorSumarioChart::class,
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