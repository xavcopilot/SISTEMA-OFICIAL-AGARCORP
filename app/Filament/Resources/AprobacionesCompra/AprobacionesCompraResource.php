<?php

namespace App\Filament\Resources\AprobacionesCompra;

use App\Filament\Resources\AprobacionesCompra\Pages;
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

class AprobacionesCompraResource extends Resource
{
    protected static ?string $model = SolicitudCompra::class;

    protected static ?string $navigationLabel = 'Aprobaciones de Compra';

    protected static ?string $modelLabel = 'Solicitud Compra';

    protected static ?string $pluralModelLabel = 'Aprobaciones de Compra';

    protected static string | \UnitEnum | null $navigationGroup = 'Compras';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->hasRole('Gerencia de Finanzas')
            ? 'Aprobacion de Solicitudes'
            : 'Aprobaciones de Compra';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return auth()->user()?->hasRole('Gerencia de Finanzas')
            ? 'Aprobaciones'
            : 'Solicitudes de Compra';
    }

    public static function form(Schema $schema): Schema
    {
        return SolicitudCompraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SolicitudesCompraTable::configureForApprovals($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAprobacionesCompras::route('/'),
            'view' => Pages\ViewAprobacionCompra::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return SolicitudCompraFlow::visibleTo(parent::getEloquentQuery(), auth()->user());
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return SolicitudCompraFlow::isReviewer($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::countPendingApprovalNotifications();

        return $count > 0 ? (string) $count : null;
    }

    public static function countPendingApprovalNotifications(?User $user = null): int
    {
        $user ??= auth()->user();

        if (! $user || ! SolicitudCompraFlow::isReviewer($user)) {
            return 0;
        }

        return (int) SolicitudCompraFlow::pendingAreaInboxQuery(static::getModel()::query(), $user)->count();
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
        return SolicitudCompraFlow::canView(auth()->user(), $record);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}