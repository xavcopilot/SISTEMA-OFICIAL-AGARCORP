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
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rif')
                    ->label('Rif')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ciudad')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('contacto')
                    ->label('Contacto')
                    ->searchable(),
                TextColumn::make('telefono')
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