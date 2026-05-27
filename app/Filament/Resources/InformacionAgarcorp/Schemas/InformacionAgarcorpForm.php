<?php

namespace App\Filament\Resources\InformacionAgarcorp\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InformacionAgarcorpForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('razon_social')
                    ->label('Razon social')
                    ->required()
                    ->maxLength(255),

                TextInput::make('rif')
                    ->label('RIF')
                    ->maxLength(60),

                Textarea::make('direccion_fiscal')
                    ->label('Direccion fiscal')
                    ->rows(3)
                    ->maxLength(500),

                TextInput::make('telefono_principal')
                    ->label('Telefono principal')
                    ->maxLength(120),
            ]);
    }
}
