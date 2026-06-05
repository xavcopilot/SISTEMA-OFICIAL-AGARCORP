<?php

namespace App\Filament\Resources\InventoryMovements\Tables;

use App\Models\InventoryMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistColumnsInSession(true)
            ->columns([
                TextColumn::make('nro_control')
                    ->label('N Control')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('almacenista')
                    ->label('Almacenista')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('dpto_responsable')
                    ->label('Dpto Responsable')
                    ->state(fn (InventoryMovement $record): string => (string) ($record->dpto_responsable_unificado ?? ''))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('orden_compra')
                    ->label('Orden Compra')
                    ->toggleable(),

                TextColumn::make('nro_solicitud')
                    ->label('N Solicitud')
                    ->toggleable(),

                TextColumn::make('proveedor')
                    ->label('Proveedor')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('comentarios')
                    ->label('Observaciones')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('total_items')
                    ->label('Total Items')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('createdBy.name')
                    ->label('Creado por')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('updatedBy.name')
                    ->label('Modificado por')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('formato_entrada')
                    ->label('Formato Entrada')
                    ->state(fn (?InventoryMovement $record): string => ($record && (string) $record->tipo === 'entrada') ? 'Ver formato' : '')
                    ->url(fn (?InventoryMovement $record): string => ($record && (string) $record->tipo === 'entrada') ? route('inventario.movimientos.formato-entrada', ['inventoryMovement' => $record, 'download' => 1]) : '')
                    ->openUrlInNewTab()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'ingreso' => 'Ingreso',
                        'entrada' => 'Entrada',
                        'salida' => 'Salida',
                    ]),

                Filter::make('fecha')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('fecha', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('fecha', '<=', $date));
                    }),

                Filter::make('nro_control')
                    ->label('N Control')
                    ->schema([
                        TextInput::make('nro_control')->label('N Control'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $control = trim((string) ($data['nro_control'] ?? ''));

                        if ($control === '') {
                            return $query;
                        }

                        return $query->where('nro_control', 'like', '%' . $control . '%');
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
