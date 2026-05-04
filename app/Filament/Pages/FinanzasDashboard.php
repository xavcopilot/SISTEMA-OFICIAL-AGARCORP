<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class FinanzasDashboard extends Dashboard
{
    protected static string $routePath = 'finanzas/dashboard';

    protected static ?string $title = 'Dashboard de Finanzas';

    protected static string | UnitEnum | null $navigationGroup = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard de Finanzas';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('Gerencia de Finanzas') || $user->hasRole('Alta Gerencia'));
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

    public function getWidgets(): array
    {
        return [];
    }

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}