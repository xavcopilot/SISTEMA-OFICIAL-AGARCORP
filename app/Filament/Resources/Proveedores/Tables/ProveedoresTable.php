<?php

namespace App\Filament\Resources\Proveedores\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProveedoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('nombre')
                    ->toggleable()
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rif')
                    ->toggleable()
                    ->label('Rif')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ciudad')
                    ->toggleable()
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->toggleable()
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('contacto')
                    ->toggleable()
                    ->label('Contacto')
                    ->searchable(),
                TextColumn::make('telefono')
                    ->toggleable()
                    ->label('Telefono')
                    ->searchable(),
            ])
            ->defaultSort('nombre', 'asc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
