<?php

namespace App\Filament\Resources\Sumarios\Tables;

use App\Support\ActivityNotification;
use App\Support\SumarioFinanceApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
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
                    ->state(fn ($record): string => (string) ($record->workflow_estado ?: $record->estado))
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', (string) $state))
                    ->color(fn (?string $state): string => match ((string) $state) {
                        'BORRADOR' => 'gray',
                        'PENDIENTE_VALIDACION_FINANZAS' => 'warning',
                        'VALIDADO_FINANZAS' => 'info',
                        'APROBADO_GERENCIA_FINANZAS' => 'success',
                        'ODC_GENERADA' => 'success',
                        'RECHAZADO_VALIDACION_FINANZAS', 'RECHAZADO_GERENCIA_FINANZAS' => 'danger',
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

                Action::make('enviarValidacionFinanzas')
                    ->label('Enviar a Validacion Finanzas')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canSubmitForFinanceValidation($record))
                    ->action(function ($record): void {
                        $record->forceFill([
                            'workflow_estado' => 'PENDIENTE_VALIDACION_FINANZAS',
                            'enviado_validacion_finanzas_at' => now(),
                            'enviado_por_user_id' => auth()->id(),
                            'validado_finanzas_at' => null,
                            'validado_por_user_id' => null,
                            'validacion_finanzas_resultado' => null,
                            'validacion_finanzas_comentario' => null,
                            'decision_gerencia_finanzas_at' => null,
                            'decision_gerencia_por_user_id' => null,
                            'decision_gerencia_resultado' => null,
                            'decision_gerencia_comentario' => null,
                        ])->save();

                        Notification::make()
                            ->title('Sumario enviado')
                            ->body('Se envio al analista/validador de Finanzas para revision.')
                            ->success()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Sumario enviado a validacion',
                            'El sumario ' . (string) $record->correlativo_sdc . ' fue enviado a Validacion Finanzas.',
                            'success'
                        );
                    }),

                Action::make('validarFinanzasAprobar')
                    ->label('Validar Finanzas: Aceptar')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canValidateFinance($record) && (string) ($record->workflow_estado ?? '') === 'PENDIENTE_VALIDACION_FINANZAS')
                    ->action(function ($record): void {
                        $record->forceFill([
                            'workflow_estado' => 'VALIDADO_FINANZAS',
                            'validado_finanzas_at' => now(),
                            'validado_por_user_id' => auth()->id(),
                            'validacion_finanzas_resultado' => 'APROBADO',
                            'validacion_finanzas_comentario' => null,
                        ])->save();

                        Notification::make()
                            ->title('Validacion registrada')
                            ->body('El sumario fue aceptado por Finanzas y pasa a Gerencia de Finanzas.')
                            ->success()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Validacion de Finanzas aprobada',
                            'El sumario ' . (string) $record->correlativo_sdc . ' fue aprobado en Validacion Finanzas.',
                            'success'
                        );
                    }),

                Action::make('validarFinanzasRechazar')
                    ->label('Validar Finanzas: Rechazar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->form([
                        Textarea::make('comentario')
                            ->label('Motivo de rechazo')
                            ->required()
                            ->rows(4),
                    ])
                    ->visible(fn ($record): bool => self::canValidateFinance($record) && (string) ($record->workflow_estado ?? '') === 'PENDIENTE_VALIDACION_FINANZAS')
                    ->action(function (array $data, $record): void {
                        $record->forceFill([
                            'workflow_estado' => 'RECHAZADO_VALIDACION_FINANZAS',
                            'validado_finanzas_at' => now(),
                            'validado_por_user_id' => auth()->id(),
                            'validacion_finanzas_resultado' => 'RECHAZADO',
                            'validacion_finanzas_comentario' => (string) ($data['comentario'] ?? ''),
                        ])->save();

                        Notification::make()
                            ->title('Rechazo registrado')
                            ->body('Finanzas rechazo el sumario con comentario para Procura.')
                            ->warning()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Validacion de Finanzas rechazada',
                            'El sumario ' . (string) $record->correlativo_sdc . ' fue rechazado en Validacion Finanzas.',
                            'warning'
                        );
                    }),

                Action::make('gerenciaFinanzasAprobarPago')
                    ->label('Gerencia Finanzas: Aprobar Pago')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => self::canGerenciaFinanceDecision($record) && (string) ($record->workflow_estado ?? '') === 'VALIDADO_FINANZAS')
                    ->action(function ($record): void {
                        $record->forceFill([
                            'workflow_estado' => 'APROBADO_GERENCIA_FINANZAS',
                            'decision_gerencia_finanzas_at' => now(),
                            'decision_gerencia_por_user_id' => auth()->id(),
                            'decision_gerencia_resultado' => 'APROBADO',
                            'decision_gerencia_comentario' => null,
                        ])->save();

                        Notification::make()
                            ->title('Decision registrada')
                            ->body('Gerencia de Finanzas aprobo el pago. Procura ya puede generar ODC.')
                            ->success()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Pago aprobado por Gerencia',
                            'Gerencia de Finanzas aprobo el pago del sumario ' . (string) $record->correlativo_sdc . '.',
                            'success'
                        );
                    }),

                Action::make('gerenciaFinanzasRechazarPago')
                    ->label('Gerencia Finanzas: Rechazar Pago')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->form([
                        Textarea::make('comentario')
                            ->label('Motivo de rechazo')
                            ->required()
                            ->rows(4),
                    ])
                    ->visible(fn ($record): bool => self::canGerenciaFinanceDecision($record) && (string) ($record->workflow_estado ?? '') === 'VALIDADO_FINANZAS')
                    ->action(function (array $data, $record): void {
                        $record->forceFill([
                            'workflow_estado' => 'RECHAZADO_GERENCIA_FINANZAS',
                            'decision_gerencia_finanzas_at' => now(),
                            'decision_gerencia_por_user_id' => auth()->id(),
                            'decision_gerencia_resultado' => 'RECHAZADO',
                            'decision_gerencia_comentario' => (string) ($data['comentario'] ?? ''),
                        ])->save();

                        Notification::make()
                            ->title('Decision registrada')
                            ->body('Gerencia de Finanzas rechazo el pago y devolvio el proceso a Procura.')
                            ->warning()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Pago rechazado por Gerencia',
                            'Gerencia de Finanzas rechazo el pago del sumario ' . (string) $record->correlativo_sdc . '.',
                            'warning'
                        );
                    }),

                Action::make('generarOdcs')
                    ->label('Generar ODC por proveedor')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generar ODC por proveedor seleccionado')
                    ->modalDescription('Se creara una ODC por proveedor segun la seleccion por item en el comparativo.')
                    ->visible(fn ($record): bool => self::canGenerateOdcs($record))
                    ->action(function ($record): void {
                        $orders = app(SumarioFinanceApprovalService::class)
                            ->generateOrdersFromSelections($record, auth()->user());

                        Notification::make()
                            ->title('ODC generadas')
                            ->body('Se generaron ' . count($orders) . ' orden(es) de compra por proveedor.')
                            ->success()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'ODC generadas',
                            'Se generaron ' . count($orders) . ' ODC desde el sumario ' . (string) $record->correlativo_sdc . '.',
                            'success'
                        );
                    }),

                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function canSubmitForFinanceValidation(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! $user->can('SubmitValidation:Sumario')) {
            return false;
        }

        return in_array((string) ($record->workflow_estado ?? 'BORRADOR'), ['ODC_GENERADA', 'RECHAZADO_VALIDACION_FINANZAS', 'RECHAZADO_GERENCIA_FINANZAS'], true)
            && filled($record->ordenesCompra()->first());
    }

    private static function canValidateFinance(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        return $user->can('ValidateFinance:Sumario');
    }

    private static function canGerenciaFinanceDecision(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        return $user->can('ApprovePayment:Sumario');
    }

    private static function canGenerateOdcs(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! $user->can('GenerateOdcs:Sumario')) {
            return false;
        }

        return in_array((string) ($record->workflow_estado ?? 'BORRADOR'), ['BORRADOR', 'RECHAZADO_VALIDACION_FINANZAS', 'RECHAZADO_GERENCIA_FINANZAS'], true)
            && blank($record->ordenesCompra()->first());
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
