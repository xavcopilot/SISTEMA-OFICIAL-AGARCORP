<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Departamento;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('descripcion')
                    ->label('Descripcion')
                    ->required()
                    ->maxLength(255),

                TextInput::make('ubicacion')
                    ->label('Ubicacion')
                    ->required()
                    ->maxLength(255),

                Select::make('dpto_responsable')
                    ->label('Departamento Responsable')
                    ->options(fn (): array => Departamento::query()->orderBy('nombre')->pluck('nombre', 'nombre')->toArray())
                    ->required()
                    ->native(false),

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
