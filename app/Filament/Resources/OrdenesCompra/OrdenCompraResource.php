<?php

namespace App\Filament\Resources\OrdenesCompra;

use App\Filament\Resources\OrdenesCompra\Pages;
use App\Filament\Resources\OrdenesCompra\Schemas\OrdenCompraForm;
use App\Filament\Resources\OrdenesCompra\Tables\OrdenesCompraTable;
use App\Models\OrdenCompra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrdenCompraResource extends Resource
{
    protected static ?string $model = OrdenCompra::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Ordenes de Compra';

    protected static ?string $modelLabel = 'Orden de Compra';

    protected static ?string $pluralModelLabel = 'Ordenes de Compra';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return OrdenCompraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdenesCompraTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdenesCompra::route('/'),
            'edit' => Pages\EditOrdenCompra::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return self::hasReadAccess();
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (self::hasOperationalReadAccess()) {
            return true;
        }

        $solicitanteId = (int) ($record->sumario?->solicitudCompra?->solicitado_por_user_id ?? 0);

        return $solicitanteId > 0 && (int) $user->id === $solicitanteId;
    }

    public static function canEdit(Model $record): bool
    {
        return self::hasEditAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['sumario.solicitudCompra', 'proveedor']);

        if (self::hasOperationalReadAccess()) {
            return $query;
        }

        $userId = (int) (auth()->id() ?? 0);

        return $query->whereHas('sumario.solicitudCompra', fn (Builder $subQuery): Builder => $subQuery
            ->where('solicitado_por_user_id', $userId));
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->check() && auth()->user()?->can('Delete:OrdenCompra');
    }

    private static function hasReadAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (self::hasOperationalReadAccess()) {
            return true;
        }

        return OrdenCompra::query()
            ->whereHas('sumario.solicitudCompra', fn (Builder $query): Builder => $query
                ->where('solicitado_por_user_id', $user->id))
            ->exists();
    }

    private static function hasOperationalReadAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('ViewAny:OrdenCompra');
    }

    private static function hasEditAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('Update:OrdenCompra');
    }
}
