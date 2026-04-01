<?php

namespace App\Filament\Resources\DailyWithdrawals\Tables;

use App\Models\DailyWithdrawal;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DailyWithdrawalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['user', 'product'])
                ->orderByDesc('requested_at')
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('requested_at')
                    ->label('Fecha solicitud')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Solicitante')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('product.descripcion')
                    ->label('Material')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('destination')
                    ->label('Destino')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('requires_return')
                    ->label('Retorno')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Si' : 'No')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'aprobado' => 'aprobado',
                        'rechazado' => 'rechazado',
                        default => 'pendiente',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'aprobado' => 'success',
                        'rechazado' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'aprobado' => 'Aprobado',
                        'rechazado' => 'Rechazado',
                    ]),

                Filter::make('requested_at')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('desde')
                            ->label('Desde'),
                        DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('requested_at', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('requested_at', '<=', $date));
                    }),

                SelectFilter::make('user_id')
                    ->label('Solicitante')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('approveWithdrawal')
                    ->label('Aprobar Retiro')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar retiro diario')
                    ->modalDescription('Se validara stock disponible y se descontara del inventario.')
                    ->action(function (DailyWithdrawal $record): void {
                        DB::transaction(function () use ($record): void {
                            $withdrawal = DailyWithdrawal::query()
                                ->lockForUpdate()
                                ->find($record->id);

                            if (! $withdrawal || $withdrawal->status !== 'pendiente') {
                                Notification::make()
                                    ->title('La solicitud ya no esta pendiente.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $product = Product::query()
                                ->lockForUpdate()
                                ->find($withdrawal->product_id);

                            if (! $product) {
                                Notification::make()
                                    ->title('No se encontro el material asociado.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $requestedQty = (float) $withdrawal->quantity;

                            if ($requestedQty <= 0) {
                                Notification::make()
                                    ->title('Cantidad solicitada invalida.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            if (floor($requestedQty) !== $requestedQty) {
                                Notification::make()
                                    ->title('La cantidad para descontar stock debe ser entera.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $availableStock = (int) ($product->stock_actual ?? 0);
                            $requestedUnits = (int) $requestedQty;

                            if ($availableStock < $requestedUnits) {
                                Notification::make()
                                    ->title('Stock insuficiente para aprobar este retiro.')
                                    ->body('Disponible: ' . $availableStock . ' / Solicitado: ' . $requestedUnits)
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $product->update([
                                'stock_actual' => $availableStock - $requestedUnits,
                                'fecha_ultima_salida' => now()->toDateString(),
                            ]);

                            $withdrawal->update([
                                'status' => 'aprobado',
                                'warehouse_user_id' => auth()->id(),
                                'rejection_reason' => null,
                            ]);

                            Notification::make()
                                ->title('Retiro aprobado y stock actualizado.')
                                ->success()
                                ->send();
                        });
                    })
                    ->visible(fn (DailyWithdrawal $record): bool => $record->status === 'pendiente'),

                Action::make('rejectWithdrawal')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Motivo breve')
                            ->required()
                            ->minLength(5)
                            ->maxLength(255),
                    ])
                    ->action(function (DailyWithdrawal $record, array $data): void {
                        $record->update([
                            'status' => 'rechazado',
                            'warehouse_user_id' => auth()->id(),
                            'rejection_reason' => trim((string) ($data['rejection_reason'] ?? '')),
                        ]);

                        Notification::make()
                            ->title('Retiro rechazado.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DailyWithdrawal $record): bool => $record->status === 'pendiente'),
            ])
            ->recordUrl(null);
    }
}
