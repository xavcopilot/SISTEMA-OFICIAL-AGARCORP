<?php

namespace App\Filament\Resources\RecepcionMaterialesNuevos;

use App\Filament\Resources\RecepcionMaterialesNuevos\Pages;
use App\Filament\Resources\RecepcionMaterialesNuevos\Tables\RecepcionMaterialesNuevosTable;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecepcionMaterialesNuevosResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Retiros y Compras';

    protected static ?string $navigationLabel = 'Recepcion de Materiales Nuevos';

    protected static ?string $modelLabel = 'Recepcion Almacen';

    protected static ?string $pluralModelLabel = 'Recepcion de Materiales Nuevos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Almacen');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return RecepcionMaterialesNuevosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecepcionMaterialesNuevos::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sumario.solicitudCompra', 'proveedor'])
            ->where('workflow_post_compra', 'DOCUMENTO_RECEPCION_CARGADO_PROCURA');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $count = static::getEloquentQuery()->count();

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
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
