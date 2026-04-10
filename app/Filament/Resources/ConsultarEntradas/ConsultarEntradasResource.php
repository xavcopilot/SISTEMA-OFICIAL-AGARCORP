<?php

namespace App\Filament\Resources\ConsultarEntradas;

use App\Filament\Resources\ConsultarEntradas\Pages\ListConsultarEntradas;
use App\Filament\Resources\ConsultarEntradas\Tables\ConsultarEntradasTable;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ConsultarEntradasResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Consultar Entradas';

    protected static ?string $modelLabel = 'Consulta de Entrada';

    protected static ?string $pluralModelLabel = 'Consultar Entradas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ConsultarEntradasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsultarEntradas::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()?->can('ViewAny:InventoryMovement');
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
