<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre de la categoria')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),

                Repeater::make('subcategories')
                    ->label('Subcategorias')
                    ->relationship('subcategories')
                    ->addActionLabel('Agregar subcategoria')
                    ->collapsible()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la subcategoria')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->defaultItems(0),
            ])
            ->columns(1);
    }
}
