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

    protected static ?string $navigationLabel = 'Administracion de Pagos ODC';

    protected static ?string $modelLabel = 'Pago de ODC';

    protected static ?string $pluralModelLabel = 'Administracion de Pagos ODC';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 5;

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
            ->whereIn('workflow_post_compra', [
                'PENDIENTE_PAGO_FINANZAS',
                'PAGO_REGISTRADO_FINANZAS',
            ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Finanzas Pagos')
            && $user->can('Update:OrdenCompra');
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
}
