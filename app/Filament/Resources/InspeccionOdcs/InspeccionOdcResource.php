<?php

namespace App\Filament\Resources\InspeccionOdcs;

use App\Filament\Resources\InspeccionOdcs\Pages;
use App\Filament\Resources\InspeccionOdcs\Tables\InspeccionOdcsTable;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InspeccionOdcResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static ?string $navigationLabel = 'Inspeccion de ODC';

    protected static ?string $modelLabel = 'Inspeccion de ODC';

    protected static ?string $pluralModelLabel = 'Inspeccion de ODC';

    protected static string|\UnitEnum|null $navigationGroup = 'Validaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        $user = auth()->user();

        if ($user && $user->hasRole('Validador Finanzas')) {
            return 'Validaciones';
        }

        return 'Validaciones';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return InspeccionOdcsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInspeccionOdcs::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sumario.solicitudCompra', 'proveedor'])
            ->where('workflow_post_compra', 'PENDIENTE_VALIDACION_FINANZAS');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Validador Finanzas');
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

    public static function canView(Model $record): bool
    {
        return static::canAccess();
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