<?php

namespace App\Filament\Resources\Proveedores\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ProveedorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('rif')
                            ->label('Rif')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('direccion')
                            ->label('Direccion')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('ciudad')
                            ->label('Ciudad')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('contacto')
                            ->label('Contacto')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('telefono')
                            ->label('Telefono')
                            ->required()
                            ->tel()
                            ->maxLength(50),
                    ]),
            ]);
    }
}