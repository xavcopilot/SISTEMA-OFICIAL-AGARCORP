<?php

namespace App\Filament\Resources\AprobacionOdcs;

use App\Filament\Resources\AprobacionOdcs\Pages;
use App\Filament\Resources\AprobacionOdcs\Tables\AprobacionOdcsTable;
use App\Filament\Resources\OrdenesCompra\Schemas\OrdenCompraForm;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AprobacionOdcResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Aprobaciones';

    protected static ?string $navigationLabel = 'Aprobacion de ODC';

    protected static ?string $modelLabel = 'Aprobacion de ODC';

    protected static ?string $pluralModelLabel = 'Aprobacion de ODC';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return OrdenCompraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AprobacionOdcsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAprobacionOdcs::route('/'),
            'edit' => Pages\EditAprobacionOdc::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sumario.solicitudCompra', 'proveedor'])
            ->whereIn('workflow_post_compra', [
                'PENDIENTE_APROBACION_GERENCIA_FINANZAS',
                'PENDIENTE_PAGO_FINANZAS',
                'PAGO_REGISTRADO_FINANZAS',
                'PAGADO_Y_EN_TRANSITO',
                'DOCUMENTO_RECEPCION_CARGADO_PROCURA',
                'EN_TRANSICION_ALMACEN',
                'CONFORMIDAD_POR_ITEMS_COMPLETA',
                'FACTURA_ENVIADA_ADMINISTRACION',
                'BACKUP_FACTURA_COMPLETADO',
                'CERRADA_CONFORME',
            ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Gerencia de Finanzas');
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

        $count = parent::getEloquentQuery()
            ->where('workflow_post_compra', 'PENDIENTE_APROBACION_GERENCIA_FINANZAS')
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

    public static function canView(Model $record): bool
    {
        return static::canAccess()
            && (string) ($record->workflow_post_compra ?? '') === 'PENDIENTE_APROBACION_GERENCIA_FINANZAS';
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess()
            && (string) ($record->workflow_post_compra ?? '') === 'PENDIENTE_APROBACION_GERENCIA_FINANZAS';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
