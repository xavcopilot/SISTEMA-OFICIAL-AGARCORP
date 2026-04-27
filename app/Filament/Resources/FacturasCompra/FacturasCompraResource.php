<?php

namespace App\Filament\Resources\FacturasCompra;

use App\Filament\Resources\FacturasCompra\Pages;
use App\Filament\Resources\FacturasCompra\Tables\FacturasCompraTable;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FacturasCompraResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Pagos';

    protected static ?string $navigationLabel = 'Facturas de Compra';

    protected static ?string $modelLabel = 'Factura de Compra';

    protected static ?string $pluralModelLabel = 'Facturas de Compra';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return FacturasCompraTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacturasCompras::route('/'),
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
