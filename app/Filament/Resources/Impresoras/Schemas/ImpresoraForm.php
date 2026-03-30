<?php

namespace App\Filament\Resources\Impresoras\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ImpresoraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo')
                    ->label('Código de Equipo')
                    ->placeholder('Ej: ADV-HPCOLOR-1FC5')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('nombre')
                    ->label('Nombre / Ubicación')
                    ->placeholder('Ej: ADV-HPCOLOR-1FC5 (THSIHO)')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}