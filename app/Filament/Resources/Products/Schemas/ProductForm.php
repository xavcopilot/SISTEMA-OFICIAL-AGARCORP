<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Departamento;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ubicacion')
                    ->label('Ubicacion')
                    ->required()
                    ->maxLength(255),

                TextInput::make('dpto_responsable')
                    ->label('Departamento Responsable')
                    ->datalist(fn (): array => Departamento::query()->orderBy('nombre')->pluck('nombre')->all())
                    ->helperText('Puedes escribir libremente o elegir un departamento sugerido.')
                    ->required()
                    ->maxLength(255),

                TextInput::make('stock_minimo')
                    ->label('Stock Minimo')
                    ->numeric()
                    ->required()
                    ->minValue(0),

                TextInput::make('precio_unitario')
                    ->label('Precio Unitario')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->prefix('$'),
            ]);
    }
}
