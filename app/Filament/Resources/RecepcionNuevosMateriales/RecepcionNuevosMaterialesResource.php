<?php

namespace App\Filament\Resources\RecepcionNuevosMateriales;

use App\Filament\Resources\RecepcionNuevosMateriales\Pages;
use App\Filament\Resources\RecepcionNuevosMateriales\Tables\RecepcionNuevosMaterialesTable;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecepcionNuevosMaterialesResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Recepcion de Nuevos Materiales';

    protected static ?string $modelLabel = 'Recepcion de Material';

    protected static ?string $pluralModelLabel = 'Recepcion de Nuevos Materiales';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return RecepcionNuevosMaterialesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecepcionNuevosMateriales::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sumario.solicitudCompra', 'proveedor'])
            ->whereIn('workflow_post_compra', [
                'DOCUMENTO_RECEPCION_CARGADO_PROCURA',
                'EN_TRANSICION_ALMACEN',
                'CONFORMIDAD_POR_ITEMS_COMPLETA',
            ]);
    }

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
}
