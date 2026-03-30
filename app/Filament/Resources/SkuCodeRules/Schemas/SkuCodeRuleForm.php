<?php

namespace App\Filament\Resources\SkuCodeRules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SkuCodeRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('prefix')
                    ->label('Prefijo SKU')
                    ->required()
                    ->maxLength(3)
                    ->rule('regex:/^[A-Za-z0-9]+$/')
                    ->helperText('Solo letras y numeros (3 caracteres). Ejemplo: CON, INF, EPP'),

                TextInput::make('next_correlative')
                    ->label('Siguiente correlativo')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->helperText('Numero de referencia para el proximo SKU. Si existen vacios internos de SKU, se rellenan primero (este valor no aplica de forma directa hasta cerrar esos vacios).'),

                Toggle::make('is_active')
                    ->label('Regla activa')
                    ->default(true),

                Textarea::make('notes')
                    ->label('Notas')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
