<?php

namespace App\Filament\Resources\Impresoras\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ImpresorasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('codigo')
                    ->toggleable()
                    ->label('Código de Equipo')
                    ->searchable(),

                TextColumn::make('nombre')
                    ->toggleable()
                    ->label('Nombre / Ubicación')
                    ->searchable(),
            ])
            ->actions([
                // Llamada directa al núcleo para evitar el error de "Class not found"
                 \Filament\Actions\EditAction::make(),
                 \Filament\Actions\DeleteAction::make(),
            ]);
    }
}
