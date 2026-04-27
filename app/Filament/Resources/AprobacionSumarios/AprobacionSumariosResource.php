<?php

namespace App\Filament\Resources\AprobacionSumarios;

use App\Filament\Resources\AprobacionSumarios\Pages;
use App\Filament\Resources\Sumarios\Schemas\SumarioForm;
use App\Filament\Resources\Sumarios\Tables\SumariosTable;
use App\Models\Sumario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AprobacionSumariosResource extends Resource
{
    protected static ?string $model = Sumario::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Aprobaciones';

    protected static ?string $navigationLabel = 'Aprobacion de Sumarios';

    protected static ?string $modelLabel = 'Aprobacion de Sumario';

    protected static ?string $pluralModelLabel = 'Aprobacion de Sumarios';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return SumarioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SumariosTable::configureForManagementApproval($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAprobacionSumarios::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('workflow_estado', ['VALIDADO_FINANZAS']);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('ApprovePayment:Sumario');
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

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
