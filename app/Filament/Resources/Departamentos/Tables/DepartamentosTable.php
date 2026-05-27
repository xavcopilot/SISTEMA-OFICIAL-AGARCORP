<?php

namespace App\Filament\Resources\Departamentos\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class DepartamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('nombre')
                    ->toggleable()
                    ->label('Nombre')
                    ->searchable(),
            ])
            ->defaultSort('id','asc')
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }
}
