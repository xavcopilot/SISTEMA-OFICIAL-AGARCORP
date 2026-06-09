<?php

namespace App\Filament\Resources\Proveedores\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProveedorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de Empresa')
                    ->schema([
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
                    ])
                    ->columnSpanFull(),

                Section::make('Datos Bancarios')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('banco')
                                    ->label('Banco')
                                    ->maxLength(255),

                                TextInput::make('numero_cuenta')
                                    ->label('N-Cuenta')
                                    ->maxLength(50),

                                Select::make('tipo_documento')
                                    ->label('Tipo de Documento')
                                    ->options([
                                        'V' => 'V',
                                        'E' => 'E',
                                        'P' => 'P',
                                        'J' => 'J',
                                        'G' => 'G',
                                        'R' => 'R',
                                        'F' => 'F',
                                        'I' => 'I',
                                    ])
                                    ->native(false)
                                    ->placeholder('Seleccione'),

                                TextInput::make('documento')
                                    ->label('Documento')
                                    ->maxLength(50),

                                TextInput::make('beneficiario_nombre_apellido')
                                    ->label('Nombre y Apellido Beneficiario')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}