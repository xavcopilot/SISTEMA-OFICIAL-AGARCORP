<?php

namespace App\Filament\Resources\Cargos\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CargosTable
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
            ->defaultSort('id', 'asc')
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }
}
