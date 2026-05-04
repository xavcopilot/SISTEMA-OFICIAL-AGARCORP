<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class MainDashboard extends Dashboard
{
    protected static ?string $title = '';

    protected static ?string $navigationLabel = 'Escritorio';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -999;
}
