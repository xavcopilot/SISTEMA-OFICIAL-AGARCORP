<?php

namespace App\Filament\Resources\ConsultarSalidas;

use App\Filament\Resources\ConsultarSalidas\Pages\ListConsultarSalidas;
use App\Filament\Resources\ConsultarSalidas\Tables\ConsultarSalidasTable;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ConsultarSalidasResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Consultar Salidas';

    protected static ?string $modelLabel = 'Consulta de Salida';

    protected static ?string $pluralModelLabel = 'Consultar Salidas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ConsultarSalidasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsultarSalidas::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('Almacen');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}