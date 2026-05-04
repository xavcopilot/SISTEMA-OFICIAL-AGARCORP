<?php

namespace App\Filament\Resources\PagosProcura;

use App\Filament\Resources\PagosProcura\Pages;
use App\Filament\Resources\OrdenesCompra\Tables\OrdenesCompraTable;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PagosProcuraResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Pagos Procura';

    protected static ?string $modelLabel = 'Pago Procura';

    protected static ?string $pluralModelLabel = 'Pagos Procura';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return OrdenesCompraTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPagosProcura::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sumario.solicitudCompra', 'proveedor'])
            ->whereIn('workflow_post_compra', [
                'PENDIENTE_PAGO_FINANZAS',
                'PAGO_REGISTRADO_FINANZAS',
                'PAGADO_Y_EN_TRANSITO',
            ]);
    }

    public static function canAccess(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
