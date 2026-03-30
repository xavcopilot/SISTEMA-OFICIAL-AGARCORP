<?php

namespace App\Filament\Resources\Impresoras;

use App\Filament\Resources\Impresoras\Pages\CreateImpresora;
use App\Filament\Resources\Impresoras\Pages\EditImpresora;
use App\Filament\Resources\Impresoras\Pages\ListImpresoras;
use App\Filament\Resources\Impresoras\Schemas\ImpresoraForm;
use App\Filament\Resources\Impresoras\Tables\ImpresorasTable;
use App\Models\Impresora;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImpresoraResource extends Resource
{
    protected static ?string $model = Impresora::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Administracion';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'impresoras';

    public static function form(Schema $schema): Schema
    {
        return ImpresoraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImpresorasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImpresoras::route('/'),
            'create' => CreateImpresora::route('/create'),
            'edit' => EditImpresora::route('/{record}/edit'),
        ];
    }
}
