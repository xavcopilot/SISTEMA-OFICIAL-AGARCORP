<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ProcuraDashboard extends Dashboard
{
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