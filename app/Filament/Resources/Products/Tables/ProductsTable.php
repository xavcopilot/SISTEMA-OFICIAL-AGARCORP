<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use App\Models\Subcategory;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['subcategory.category'])
                ->withSum([
                    'movementItems as entradas_acumuladas' => fn (Builder $movementItems): Builder => $movementItems
                        ->whereHas('movement', fn (Builder $movement): Builder => $movement->whereIn('tipo', ['ingreso', 'entrada'])),
                ], 'cantidad')
                ->withSum([
                    'movementItems as salidas_acumuladas' => fn (Builder $movementItems): Builder => $movementItems
                        ->whereHas('movement', fn (Builder $movement): Builder => $movement->where('tipo', 'salida')),
                ], 'cantidad'))
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('descripcion')
                    ->label('Producto')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('marca')
                    ->label('Marca')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('subcategory.category.name')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('subcategory.name')
                    ->label('Subcatg')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('medida')
                    ->label('Medida')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('serial')
                    ->label('Serial')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('almacen')
                    ->label('Almacen')
                    ->state('ALMACEN')
                    ->toggleable(),

                TextColumn::make('ubicacion')
                    ->label('Ubicacion')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('dpto_responsable')
                    ->label('Dpto Responsable')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('stock_minimo')
                    ->label('Min')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(function ($record): string {
                        $stockActual = (int) $record->stock_actual;
                        $stockMinimo = (int) $record->stock_minimo;

                        if ($stockActual < $stockMinimo) {
                            return 'Critico';
                        }

                        if ($stockActual === $stockMinimo) {
                            return 'Precaucion';
                        }

                        return 'Optimo';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Optimo' => 'success',
                        'Precaucion' => 'warning',
                        'Critico' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('stock_actual')
                    ->label('Cant. Total')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('entradas_acumuladas')
                    ->label('Entradas')
                    ->state(fn ($record): int => (int) ($record->entradas_acumuladas ?? 0))
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('salidas_acumuladas')
                    ->label('Salidas')
                    ->state(fn ($record): int => (int) ($record->salidas_acumuladas ?? 0))
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('precio_unitario')
                    ->label('P.Unitario')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('precio_total')
                    ->label('P.Total')
                    ->state(fn ($record): float => (float) $record->stock_actual * (float) $record->precio_unitario)
                    ->money('USD')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('(stock_actual * precio_unitario) ' . $direction))
                    ->toggleable(),

                TextColumn::make('fecha_adquisicion')
                    ->label('Fecha de Adquisicion')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('fecha_ultima_entrada')
                    ->label('Fecha de Ultima Entrada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('fecha_ultima_salida')
                    ->label('Fecha de Ultima Salida')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                BadgeColumn::make('is_archived')
                    ->label('Estado')
                    ->getStateUsing(fn (\App\Models\Product $record): string => $record->is_archived ? 'Archivado' : 'Activo')
                    ->color(fn (\App\Models\Product $record): string => $record->is_archived ? 'danger' : 'success')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('categoria')
                    ->label('Categoria')
                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        $categoryId = $data['value'] ?? null;

                        if (blank($categoryId)) {
                            return $query;
                        }

                        return $query->whereHas('subcategory.category', fn (Builder $category): Builder => $category->whereKey($categoryId));
                    }),

                SelectFilter::make('subcategory_id')
                    ->label('Subcategoria')
                    ->options(fn (): array => Subcategory::query()->orderBy('name')->pluck('name', 'id')->toArray()),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(function (): array {
                        return \App\Models\Product::query()
                            ->whereNotNull('estado')
                            ->orderBy('estado')
                            ->pluck('estado', 'estado')
                            ->toArray();
                    }),

                SelectFilter::make('dpto_responsable')
                    ->label('Responsable')
                    ->options(function (): array {
                        return \App\Models\Product::query()
                            ->whereNotNull('dpto_responsable')
                            ->orderBy('dpto_responsable')
                            ->pluck('dpto_responsable', 'dpto_responsable')
                            ->toArray();
                    }),

                SelectFilter::make('ubicacion')
                    ->label('Ubicacion')
                    ->options(function (): array {
                        return \App\Models\Product::query()
                            ->whereNotNull('ubicacion')
                            ->orderBy('ubicacion')
                            ->pluck('ubicacion', 'ubicacion')
                            ->toArray();
                    }),

                SelectFilter::make('status_stock')
                    ->label('Status')
                    ->options([
                        'optimo' => 'Optimo',
                        'precaucion' => 'Precaucion',
                        'critico' => 'Critico',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'optimo' => $query->whereColumn('stock_actual', '>', 'stock_minimo'),
                            'precaucion' => $query->whereColumn('stock_actual', '=', 'stock_minimo'),
                            'critico' => $query->whereColumn('stock_actual', '<', 'stock_minimo'),
                            default => $query,
                        };
                    }),

                SelectFilter::make('is_archived')
                    ->label('Estado del Producto')
                    ->options([
                        0 => 'Activos',
                        1 => 'Archivados',
                    ])
                    ->default(0),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),

                Action::make('toggleArchive')
                    ->label(fn (\App\Models\Product $record): string => $record->is_archived ? 'Reactivar' : 'Archivar')
                    ->icon(fn (\App\Models\Product $record): string => $record->is_archived ? 'heroicon-o-arrow-uturn-up' : 'heroicon-o-archive-box')
                    ->color(fn (\App\Models\Product $record): string => $record->is_archived ? 'success' : 'warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (\App\Models\Product $record): string => $record->is_archived ? 'Reactivar producto' : 'Archivar producto')
                    ->modalDescription(fn (\App\Models\Product $record): string => $record->is_archived
                        ? '¿Deseas reactivar el producto ' . $record->sku . '?'
                        : '¿Deseas archivar el producto ' . $record->sku . '? Los productos archivados no aparecerán en las opciones de entrada/salida.')
                    ->modalSubmitActionLabel(fn (\App\Models\Product $record): string => $record->is_archived ? 'Sí, reactivar' : 'Sí, archivar')
                    ->action(function (\App\Models\Product $record): void {
                        $record->is_archived = ! $record->is_archived;
                        $record->save();

                        $message = $record->is_archived ? 'Producto archivado correctamente.' : 'Producto reactivado correctamente.';

                        Notification::make()
                            ->title($message)
                            ->success()
                            ->send();
                    }),

                Action::make('deletePermanently')
                    ->label('Eliminar permanentemente')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (\App\Models\Product $record): bool => (bool) $record->is_archived)
                    ->requiresConfirmation()
                    ->modalHeading('⚠️ Eliminar permanentemente')
                    ->modalDescription(fn (\App\Models\Product $record): string => 'Esta acción no se puede deshacer. Solo se permite si el producto tiene historial exclusivamente de ingreso. Producto: ' . $record->sku . '.')
                    ->modalSubmitActionLabel('Sí, eliminar permanentemente')
                    ->action(function (\App\Models\Product $record): void {
                        $hasEntradaSalida = $record->movementItems()
                            ->whereHas('movement', fn (Builder $query): Builder => $query->whereIn('tipo', ['entrada', 'salida']))
                            ->exists();

                        if ($hasEntradaSalida) {
                            Notification::make()
                                ->title('No se puede eliminar: el producto tiene movimientos de entrada o salida.')
                                ->warning()
                                ->send();

                            return;
                        }

                        DB::transaction(function () use ($record): void {
                            $ingresoMovementIds = $record->movementItems()
                                ->whereHas('movement', fn (Builder $query): Builder => $query->where('tipo', 'ingreso'))
                                ->pluck('movement_id')
                                ->unique()
                                ->values()
                                ->all();

                            // Primero se eliminan lineas ingreso del producto para respetar FK restrictOnDelete.
                            $record->movementItems()
                                ->whereHas('movement', fn (Builder $query): Builder => $query->where('tipo', 'ingreso'))
                                ->delete();

                            // Si un encabezado ingreso queda sin lineas, se elimina.
                            if ($ingresoMovementIds !== []) {
                                \App\Models\InventoryMovement::query()
                                    ->whereIn('id', $ingresoMovementIds)
                                    ->where('tipo', 'ingreso')
                                    ->doesntHave('items')
                                    ->delete();
                            }

                            $record->delete();
                        });

                        $sku = $record->sku;

                        Notification::make()
                            ->title('Producto ' . $sku . ' eliminado permanentemente.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                Action::make('emptyArchivedBin')
                    ->label('Vaciar archivados')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Vaciar productos archivados')
                    ->modalDescription('Se eliminarán permanentemente solo los archivados con historial exclusivamente de ingreso. Si tienen entrada o salida quedarán bloqueados.')
                    ->modalSubmitActionLabel('Sí, vaciar archivados')
                    ->action(function (): void {
                        $archivedProducts = \App\Models\Product::query()
                            ->where('is_archived', true)
                            ->with(['movementItems.movement'])
                            ->get();

                        $totalArchived = $archivedProducts->count();

                        if ($totalArchived === 0) {
                            Notification::make()
                                ->title('No hay productos archivados para eliminar.')
                                ->info()
                                ->send();

                            return;
                        }

                        $deleted = 0;
                        $blocked = 0;

                        foreach ($archivedProducts as $product) {
                            $hasEntradaSalida = $product->movementItems->contains(function ($item): bool {
                                $tipo = (string) ($item->movement?->tipo ?? '');

                                return in_array($tipo, ['entrada', 'salida'], true);
                            });

                            if ($hasEntradaSalida) {
                                $blocked++;

                                continue;
                            }

                            DB::transaction(function () use ($product): void {
                                $ingresoMovementIds = $product->movementItems()
                                    ->whereHas('movement', fn (Builder $query): Builder => $query->where('tipo', 'ingreso'))
                                    ->pluck('movement_id')
                                    ->unique()
                                    ->values()
                                    ->all();

                                $product->movementItems()
                                    ->whereHas('movement', fn (Builder $query): Builder => $query->where('tipo', 'ingreso'))
                                    ->delete();

                                if ($ingresoMovementIds !== []) {
                                    \App\Models\InventoryMovement::query()
                                        ->whereIn('id', $ingresoMovementIds)
                                        ->where('tipo', 'ingreso')
                                        ->doesntHave('items')
                                        ->delete();
                                }

                                $product->delete();
                            });

                            $deleted++;
                        }

                        Notification::make()
                            ->title('Papelera procesada: eliminados ' . $deleted . ', bloqueados por historial ' . $blocked . '.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('descripcion');
    }
}
