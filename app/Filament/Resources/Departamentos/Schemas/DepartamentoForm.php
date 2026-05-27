<?php

namespace App\Filament\Resources\Departamentos\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class DepartamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre del departamento')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
            ]);
    }
}
