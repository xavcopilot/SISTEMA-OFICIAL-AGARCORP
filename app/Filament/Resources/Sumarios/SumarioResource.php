<?php

namespace App\Filament\Resources\Sumarios;

use App\Filament\Resources\Sumarios\Pages;
use App\Filament\Resources\Sumarios\Schemas\SumarioForm;
use App\Filament\Resources\Sumarios\Tables\SumariosTable;
use App\Models\Sumario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SumarioResource extends Resource
{
    protected static ?string $model = Sumario::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Sumario Cotizaciones';

    protected static ?string $modelLabel = 'Sumario';

    protected static ?string $pluralModelLabel = 'Sumarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return SumarioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SumariosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSumarios::route('/'),
            'create' => Pages\CreateSumario::route('/create'),
            'edit' => Pages\EditSumario::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::hasReadAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $isOnlyInspectionProfile = $user->can('ValidateFinance:Sumario')
            && ! $user->can('SubmitValidation:Sumario')
            && ! $user->can('ApprovePayment:Sumario')
            && ! $user->can('GenerateOdcs:Sumario')
            && ! $user->can('Create:Sumario');

        if ($isOnlyInspectionProfile) {
            return false;
        }

        return self::hasReadAccess();
    }

    public static function canCreate(): bool
    {
        return self::hasCreateAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return self::hasUpdateAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return self::hasDeleteAccess();
    }

    private static function hasReadAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('ViewAny:Sumario');
    }

    private static function hasCreateAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('Create:Sumario');
    }

    private static function hasUpdateAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('Update:Sumario');
    }

    private static function hasDeleteAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('Delete:Sumario');
    }
}
