<?php

namespace App\Filament\Resources\Departamentos;

use App\Filament\Resources\Departamentos\Pages;
use App\Filament\Resources\Departamentos\Schemas\DepartamentoForm;
use App\Filament\Resources\Departamentos\Tables\DepartamentosTable;
use App\Models\Departamento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class DepartamentoResource extends Resource
{
    protected static ?string $model = Departamento::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Administracion';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Configuraciones';
    }

    protected static ?string $navigationLabel = 'Departamentos';
    protected static ?string $pluralModelLabel = 'Departamentos';

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedBuildingOffice;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('A.I.T') || $user->hasRole('Alta Gerencia');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    } 

    public static function form(Schema $schema): Schema
    {
        return DepartamentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartamentosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartamentos::route('/'),
            'create' => Pages\CreateDepartamento::route('/create'),
            'edit' => Pages\EditDepartamento::route('/{record}/edit'),
        ];
    }
}
