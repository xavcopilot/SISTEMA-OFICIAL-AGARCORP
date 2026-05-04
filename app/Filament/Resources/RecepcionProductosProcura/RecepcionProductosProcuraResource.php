<?php

namespace App\Filament\Resources\RecepcionProductosProcura;

use App\Filament\Resources\RecepcionProductosProcura\Pages;
use App\Filament\Resources\RecepcionProductosProcura\Tables\RecepcionProductosProcuraTable;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecepcionProductosProcuraResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Productos';

    protected static ?string $navigationLabel = 'Recepcion de Productos';

    protected static ?string $modelLabel = 'Recepcion Procura';

    protected static ?string $pluralModelLabel = 'Recepcion de Productos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return RecepcionProductosProcuraTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecepcionProductosProcuras::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sumario.solicitudCompra', 'proveedor'])
            ->where('workflow_post_compra', 'PAGADO_Y_EN_TRANSITO');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Procura');
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
