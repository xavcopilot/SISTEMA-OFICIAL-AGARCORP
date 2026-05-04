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
use Illuminate\Database\Eloquent\Builder;
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

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Procura');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return self::hasCreateAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user || ! self::hasReadAccess()) {
            return null;
        }

        $query = static::getModel()::query();

        if ($user->can('ApprovePayment:Sumario')) {
            $query->where('workflow_estado', 'VALIDADO_FINANZAS');
        } elseif ($user->can('ValidateFinance:Sumario') && ! $user->can('ApprovePayment:Sumario')) {
            $query->where('workflow_estado', 'PENDIENTE_VALIDACION_FINANZAS');
        } elseif ($user->can('GenerateOdcs:Sumario')) {
            $query->whereIn('workflow_estado', [
                'APROBADO_GERENCIA_FINANZAS',
                'RECHAZADO_GERENCIA_FINANZAS',
            ]);
        } elseif ($user->can('Create:Sumario') || $user->can('Update:Sumario')) {
            $query->whereIn('workflow_estado', [
                'BORRADOR',
                'RECHAZADO_VALIDACION_FINANZAS',
                'RECHAZADO_GERENCIA_FINANZAS',
            ]);
        } else {
            return null;
        }

        $count = (int) $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() !== null ? 'warning' : 'gray';
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
