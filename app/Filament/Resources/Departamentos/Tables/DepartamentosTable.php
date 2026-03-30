<?php

namespace App\Filament\Resources\Departamentos\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class DepartamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
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
