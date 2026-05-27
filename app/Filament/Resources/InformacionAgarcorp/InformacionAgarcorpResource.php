<?php

namespace App\Filament\Resources\InformacionAgarcorp;

use App\Filament\Resources\InformacionAgarcorp\Pages\EditInformacionAgarcorp;
use App\Filament\Resources\InformacionAgarcorp\Pages\ListInformacionAgarcorps;
use App\Filament\Resources\InformacionAgarcorp\Schemas\InformacionAgarcorpForm;
use App\Filament\Resources\InformacionAgarcorp\Tables\InformacionAgarcorpTable;
use App\Models\InformacionAgarcorp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InformacionAgarcorpResource extends Resource
{
    protected static ?string $model = InformacionAgarcorp::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Administracion';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'razon_social';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return 'Informacion AGARCORP';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Configuraciones';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('A.I.T');
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return InformacionAgarcorpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InformacionAgarcorpTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInformacionAgarcorps::route('/'),
            'edit' => EditInformacionAgarcorp::route('/{record}/edit'),
        ];
    }
}
