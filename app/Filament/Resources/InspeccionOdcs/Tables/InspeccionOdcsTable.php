<?php

namespace App\Filament\Resources\InspeccionOdcs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InspeccionOdcsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('correlativo_odc')
                    ->label('Correlativo ODC')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sumario.correlativo_sdc')
                    ->label('Sumario')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('departamento_solicitante')
                    ->label('Departamento')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('total_general')
                    ->label('Total general')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->sortable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (): string => 'PENDIENTE VALIDACION FINANZAS'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}