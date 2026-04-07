<?php

namespace App\Filament\Resources\DailyWithdrawals;

use App\Filament\Resources\DailyWithdrawals\Pages\ListDailyWithdrawals;
use App\Filament\Resources\DailyWithdrawals\Tables\DailyWithdrawalsTable;
use App\Models\DailyWithdrawal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class DailyWithdrawalResource extends Resource
{
    protected static ?string $model = DailyWithdrawal::class;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Bandeja de Retiros Diarios';

    protected static ?string $modelLabel = 'Retiro Diario';

    protected static ?string $pluralModelLabel = 'Retiros Diarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return DailyWithdrawalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyWithdrawals::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()?->can('ViewAny:DailyWithdrawal');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! SchemaFacade::hasTable('daily_withdrawals')) {
            return null;
        }

        try {
            $count = DailyWithdrawal::query()->pending()->count();
        } catch (\Throwable) {
            return null;
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() !== null ? 'warning' : 'gray';
    }
}
