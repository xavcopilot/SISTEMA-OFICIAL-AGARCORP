<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo'),
                Tables\Columns\TextColumn::make('departamento.nombre')
                    ->label('Departamento')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cargo.nombre')
                    ->label('Cargo')
                    ->searchable(),
            ])
            ->actions([
                // ESTA ES LA RUTA EXACTA EN V5 (Se movieron al namespace de Actions del Panel)
                \Filament\Actions\EditAction::make(),
            ]);
    }
}