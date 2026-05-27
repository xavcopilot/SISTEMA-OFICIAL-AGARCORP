<?php

namespace App\Filament\Resources\InformacionAgarcorp\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InformacionAgarcorpTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('razon_social')
                    ->toggleable()
                    ->label('Razon social')
                    ->searchable(),

                TextColumn::make('rif')
                    ->toggleable()
                    ->label('RIF')
                    ->searchable(),

                TextColumn::make('direccion_fiscal')
                    ->toggleable()
                    ->label('Direccion fiscal')
                    ->wrap()
                    ->limit(80),

                TextColumn::make('telefono_principal')
                    ->toggleable()
                    ->label('Telefono principal'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
