<?php

namespace App\Filament\Resources\SkuCodeRules;

use App\Filament\Resources\SkuCodeRules\Pages\CreateSkuCodeRule;
use App\Filament\Resources\SkuCodeRules\Pages\EditSkuCodeRule;
use App\Filament\Resources\SkuCodeRules\Pages\ListSkuCodeRules;
use App\Filament\Resources\SkuCodeRules\Schemas\SkuCodeRuleForm;
use App\Filament\Resources\SkuCodeRules\Tables\SkuCodeRulesTable;
use App\Models\SkuCodeRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SkuCodeRuleResource extends Resource
{
    protected static ?string $model = SkuCodeRule::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Codificacion SKU';

    protected static ?string $modelLabel = 'Regla de codificacion';

    protected static ?string $pluralModelLabel = 'Codificacion SKU';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('A.I.T');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('A.I.T');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasRole('A.I.T');
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('A.I.T');
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('A.I.T');
    }

    public static function form(Schema $schema): Schema
    {
        return SkuCodeRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SkuCodeRulesTable::configure($table);
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
            'index' => ListSkuCodeRules::route('/'),
            'create' => CreateSkuCodeRule::route('/create'),
            'edit' => EditSkuCodeRule::route('/{record}/edit'),
        ];
    }
}
