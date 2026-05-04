<?php

namespace App\Filament\Resources\SolicitudesCompra;

use App\Filament\Resources\SolicitudesCompra\Pages;
use App\Filament\Resources\SolicitudesCompra\Schemas\SolicitudCompraForm;
use App\Filament\Resources\SolicitudesCompra\Tables\SolicitudesCompraTable;
use App\Models\SolicitudCompra;
use App\Models\User;
use App\Support\SolicitudCompraFlow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SolicitudCompraResource extends Resource
{
    protected static ?string $model = SolicitudCompra::class;

    protected static ?string $navigationLabel = 'Solicitudes de Compra';

    protected static ?string $modelLabel = 'Solicitud Compra';

    protected static ?string $pluralModelLabel = 'Solicitudes de Compra';

    protected static string|\UnitEnum|null $navigationGroup = 'Solicitudes de Compra';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return SolicitudCompraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SolicitudesCompraTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolicitudCompras::route('/'),
            'create' => Pages\CreateSolicitudCompra::route('/create'),
            'view' => Pages\ViewSolicitudCompra::route('/{record}'),
            'edit' => Pages\EditSolicitudCompra::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return SolicitudCompraFlow::visibleTo($query, auth()->user());
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('ViewAny:SolicitudCompra') || $user->can('Create:SolicitudCompra');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::countRequesterNotifications();

        return $count > 0 ? (string) $count : null;
    }

    public static function countRequesterNotifications(?User $user = null): int
    {
        $user ??= auth()->user();

        if (! $user || ! static::canAccess()) {
            return 0;
        }

        return (int) static::getModel()::query()
            ->where('solicitado_por_user_id', $user->id)
            ->whereNotIn('estado', [
                SolicitudCompra::ESTADO_BORRADOR,
                SolicitudCompra::ESTADO_EN_ESPERA_ALMACEN,
            ])
            ->where('estado', '!=', SolicitudCompra::ESTADO_COMPLETADA)
            ->count();
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
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return SolicitudCompraFlow::canView(auth()->user(), $record);
    }

    public static function canEdit(Model $record): bool
    {
        return SolicitudCompraFlow::canEditRequest(auth()->user(), $record);
    }

    public static function canDelete(Model $record): bool
    {
        return SolicitudCompraFlow::canDeleteRequest(auth()->user(), $record);
    }
}
