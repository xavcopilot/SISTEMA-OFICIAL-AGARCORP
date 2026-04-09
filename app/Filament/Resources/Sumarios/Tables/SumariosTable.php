<?php

namespace App\Filament\Resources\Sumarios\Tables;

use App\Support\SumarioFinanceApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class SumariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('correlativo_sdc')
                    ->label('Correlativo SDC')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('solicitudCompra.codigo_control')
                    ->label('Solicitud')
                    ->state(fn ($record) => $record->solicitudCompra?->codigo_control ?: $record->solicitud_compra_id)
                    ->searchable(),

                TextColumn::make('procedencia')
                    ->label('Procedencia')
                    ->badge(),

                TextColumn::make('tipo_orden')
                    ->label('Tipo orden')
                    ->badge(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', (string) $state))
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'PENDIENTE_REVISION_FINANZAS' => 'warning',
                        'REVISADO_FINANZAS' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('proveedorGanador.nombre')
                    ->label('Proveedor ganador')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('total_compra_prov1')
                    ->label('Total Prov. A')
                    ->money('VES')
                    ->sortable(),

                TextColumn::make('total_compra_prov2')
                    ->label('Total Prov. B')
                    ->money('VES')
                    ->sortable(),

                TextColumn::make('total_compra_prov3')
                    ->label('Total Prov. C')
                    ->money('VES')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('verComparativo')
                    ->label('Ver comparativo')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn ($record): string => 'Sumario ' . (string) $record->correlativo_sdc)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record) => new HtmlString(self::renderComparativeTable($record))),

                Action::make('aprobarFinanzas')
                    ->label('Aprobar (Finanzas)')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar sumario y generar ODC')
                    ->modalDescription('Se generara automaticamente la Orden de Compra con los items del proveedor ganador.')
                    ->visible(fn ($record): bool => self::canApproveByFinance($record))
                    ->action(function ($record): void {
                        $ordenCompra = app(SumarioFinanceApprovalService::class)
                            ->approveByFinance($record, auth()->user());

                        Notification::make()
                            ->title('Sumario aprobado')
                            ->body('Se genero la ODC ' . $ordenCompra->correlativo_odc . ' y ahora puedes completar sus campos manuales.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function canApproveByFinance(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        $isFinance = $user->hasRole(['Finanzas', 'Gerencia de Finanzas', 'Alta Gerencia', 'A.I.T', 'admin']);

        if (! $isFinance) {
            return false;
        }

        return (string) ($record->estado ?? '') === 'PENDIENTE_REVISION_FINANZAS'
            && filled($record->proveedor_ganador_id);
    }

    private static function renderComparativeTable(mixed $record): string
    {
        $sumario = $record->loadMissing(['items.opciones', 'proveedorGanador']);

        $rows = '';

        foreach ($sumario->items as $sumarioItem) {
            $opciones = $sumarioItem->opciones->keyBy('opcion_numero');

            $rows .= '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $sumarioItem->descripcion) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) $sumarioItem->unidad_medida) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) $sumarioItem->cantidad, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($opciones->get(1)?->proveedor_nombre ?? '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($opciones->get(1)?->marca ?? '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($opciones->get(1)?->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($opciones->get(1)?->precio_total ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($opciones->get(2)?->marca ?? '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($opciones->get(2)?->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($opciones->get(2)?->precio_total ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($opciones->get(3)?->marca ?? '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($opciones->get(3)?->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($opciones->get(3)?->precio_total ?? 0), 2, ',', '.') . '</td>'
                . '</tr>';
        }

        $headerInfo = '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:12px;">'
            . '<div><strong>Sumario:</strong> ' . e((string) $sumario->correlativo_sdc) . '</div>'
            . '<div><strong>Fecha:</strong> ' . e(optional($sumario->fecha)->format('d/m/Y')) . '</div>'
            . '<div><strong>Estado:</strong> ' . e(str_replace('_', ' ', (string) $sumario->estado)) . '</div>'
            . '<div><strong>Ganador:</strong> ' . e((string) ($sumario->proveedorGanador?->nombre ?? '-')) . '</div>'
            . '</div>';

        $table = '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead>'
            . '<tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">UND</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cantidad</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Proveedor A</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Marca A</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Unit A</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Total A</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Marca B</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Unit B</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Total B</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Marca C</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Unit C</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Total C</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot>'
            . '<tr style="background:#f9fafb;font-weight:600;">'
            . '<td colspan="6" style="border:1px solid #d1d5db;padding:8px;text-align:right;">Total compra Proveedor A</td>'
            . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) $sumario->total_compra_prov1, 2, ',', '.') . '</td>'
            . '<td colspan="2" style="border:1px solid #d1d5db;padding:8px;text-align:right;">Total compra Proveedor B</td>'
            . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) $sumario->total_compra_prov2, 2, ',', '.') . '</td>'
            . '<td colspan="2" style="border:1px solid #d1d5db;padding:8px;text-align:right;">Total compra Proveedor C</td>'
            . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) $sumario->total_compra_prov3, 2, ',', '.') . '</td>'
            . '</tr>'
            . '</tfoot>'
            . '</table>'
            . '</div>';

        return $headerInfo . $table;
    }
}
