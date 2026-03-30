<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Categorias';

    protected static ?string $modelLabel = 'Categoria';

    protected static ?string $pluralModelLabel = 'Categorias';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole(['Almacen', 'A.I.T', 'admin']);
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole(['Almacen', 'A.I.T', 'admin']);
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasRole(['Almacen', 'A.I.T', 'admin']);
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->hasRole(['Almacen', 'A.I.T', 'admin']);
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole(['A.I.T', 'admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\Categories\Schemas\CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\Categories\Tables\CategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit'   => EditCategory::route('/{record}/edit'),
        ];
    }
}
