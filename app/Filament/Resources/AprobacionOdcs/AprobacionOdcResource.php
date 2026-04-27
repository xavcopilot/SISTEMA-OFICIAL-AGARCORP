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
            ->where('workflow_post_compra', 'PENDIENTE_APROBACION_GERENCIA_FINANZAS');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('Gerencia de Finanzas');
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
