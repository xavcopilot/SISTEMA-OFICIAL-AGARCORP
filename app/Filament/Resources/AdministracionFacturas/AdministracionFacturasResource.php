<?php

namespace App\Filament\Resources\AdministracionFacturas;

use App\Filament\Resources\AdministracionFacturas\Pages;
use App\Filament\Resources\AdministracionFacturas\Tables\AdministracionFacturasTable;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdministracionFacturasResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Administracion de Facturas';

    protected static ?string $modelLabel = 'Factura Administrativa';

    protected static ?string $pluralModelLabel = 'Administracion de Facturas';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return AdministracionFacturasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdministracionFacturas::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sumario.solicitudCompra', 'proveedor'])
            ->where('tipo_documento_recepcion', 'FACTURA')
            ->whereNotNull('factura_path')
            ->whereNotNull('factura_enviada_administracion_at');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->can('ViewAny:OrdenCompra')) {
            return false;
        }

        $departamento = (string) ($user->departamento?->nombre ?? '');

        return in_array($departamento, ['ADMINISTRACIÓN', 'ADMINISTRACION'], true);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
