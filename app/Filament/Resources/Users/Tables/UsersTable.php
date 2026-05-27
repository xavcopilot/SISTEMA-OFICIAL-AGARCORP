<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->toggleable()
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->toggleable()
                    ->label('Correo'),
                Tables\Columns\TextColumn::make('departamento.nombre')
                    ->toggleable()
                    ->label('Departamento')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cargo.nombre')
                    ->toggleable()
                    ->label('Cargo')
                    ->searchable(),
            ])
            ->actions([
                // ESTA ES LA RUTA EXACTA EN V5 (Se movieron al namespace de Actions del Panel)
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
