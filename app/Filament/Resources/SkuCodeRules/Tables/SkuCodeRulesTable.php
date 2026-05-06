<?php

namespace App\Filament\Resources\SkuCodeRules\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SkuCodeRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('category.name')
                    ->toggleable()
                    ->label('Categoria')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('prefix')
                    ->toggleable()
                    ->label('Prefijo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('next_correlative')
                    ->toggleable()
                    ->label('Siguiente')
                    ->sortable(),

                TextColumn::make('number_length')
                    ->toggleable()
                    ->label('Longitud')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->toggleable()
                    ->label('Activa')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->toggleable()
                    ->label('Actualizada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }
}
