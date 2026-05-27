<?php

namespace App\Filament\Resources\AdministracionPagosOdc;

use App\Filament\Resources\AdministracionPagosOdc\Pages;
use App\Filament\Resources\AdministracionPagosOdc\Tables\AdministracionPagosOdcTable;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdministracionPagosOdcResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Pagos';

    protected static ?string $navigationLabel = 'Realizacion de Pagos ODC';

    protected static ?string $modelLabel = 'Pago de ODC';

    protected static ?string $pluralModelLabel = 'Realizacion de Pagos ODC';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return 'Realizacion de Pagos ODC';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return AdministracionPagosOdcTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdministracionPagosOdcs::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sumario.solicitudCompra', 'proveedor'])
            ->where(function (Builder $query): void {
                $query
                    ->where('workflow_post_compra', 'PENDIENTE_PAGO_FINANZAS')
                    ->orWhereNotNull('pago_registrado_at')
                    ->orWhereNotNull('comprobante_pago_path');
            });
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Finanzas Pagos');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $count = static::getEloquentQuery()
            ->where('workflow_post_compra', 'PENDIENTE_PAGO_FINANZAS')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() !== null ? 'warning' : 'gray';
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
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Finanzas Pagos');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
