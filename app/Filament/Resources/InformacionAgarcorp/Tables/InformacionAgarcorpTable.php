<?php

namespace App\Filament\Resources\InformacionAgarcorp\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InformacionAgarcorpTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('razon_social')
                    ->label('Razon social')
                    ->searchable(),

                TextColumn::make('rif')
                    ->label('RIF')
                    ->searchable(),

                TextColumn::make('direccion_fiscal')
                    ->label('Direccion fiscal')
                    ->wrap()
                    ->limit(80),

                TextColumn::make('telefono_principal')
                    ->label('Telefono principal'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
