<?php

namespace App\Filament\Resources\Sumarios\Tables;

use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Support\ActivityNotification;
use App\Support\SumarioFinanceApprovalService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;

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
                        'RECHAZADO_GERENCIA_FINANZAS_PARCIAL' => 'warning',
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

                Action::make('gerenciaFinanzasValidarItems')
                    ->label('Gerencia Finanzas: Validar items')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->form([
                        Repeater::make('items_revision')
                            ->label('Revision por item (Correcto / X)')
                            ->default([])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                Hidden::make('sumario_item_id'),

                                TextInput::make('item')
                                    ->label('Item')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('descripcion')
                                    ->label('Descripcion')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->disabled()
                                    ->dehydrated(false),

                                Select::make('resultado')
                                    ->label('Decision Gerencia')
                                    ->options([
                                        'CORRECTO' => 'Correcto',
                                        'RECHAZADO' => 'X',
                                    ])
                                    ->required()
                                    ->live(),

                                Textarea::make('comentario')
                                    ->label('Motivo (si marco X)')
                                    ->rows(2)
                                    ->required(fn (callable $get): bool => (string) ($get('resultado') ?? '') === 'RECHAZADO')
                                    ->visible(fn (callable $get): bool => (string) ($get('resultado') ?? '') === 'RECHAZADO'),
                            ])
                            ->columns(5),

                        Textarea::make('comentario_gerencia')
                            ->label('Comentario general de Gerencia (opcional)')
                            ->rows(3),
                    ])
                    ->visible(fn ($record): bool => self::canGerenciaFinanceDecision($record) && (string) ($record->workflow_estado ?? '') === 'VALIDADO_FINANZAS')
                    ->fillForm(fn ($record): array => [
                        'items_revision' => self::buildGerenciaItemRevisionPayload($record),
                    ])
                    ->action(function (array $data, $record): void {
                        $rows = collect($data['items_revision'] ?? [])
                            ->filter(fn ($row): bool => is_array($row) && filled($row['sumario_item_id'] ?? null))
                            ->values();

                        if ($rows->isEmpty()) {
                            Notification::make()
                                ->title('Sin items para validar')
                                ->danger()
                                ->send();

                            return;
                        }

                        $rejectedRows = $rows->filter(function (array $row): bool {
                            return (string) ($row['resultado'] ?? '') === 'RECHAZADO'
                                && blank(trim((string) ($row['comentario'] ?? '')));
                        });

                        if ($rejectedRows->isNotEmpty()) {
                            Notification::make()
                                ->title('Motivo requerido')
                                ->body('Cada item marcado con X debe llevar un motivo de rechazo.')
                                ->danger()
                                ->send();

                            return;
                        }

                        DB::transaction(function () use ($record, $rows, $data): void {
                            $sumario = Sumario::query()->lockForUpdate()->findOrFail($record->id);

                            foreach ($rows as $row) {
                                SumarioItem::query()
                                    ->where('sumario_id', $sumario->id)
                                    ->whereKey((int) $row['sumario_item_id'])
                                    ->update([
                                        'validacion_gerencia_resultado' => (string) ($row['resultado'] ?? 'CORRECTO'),
                                        'validacion_gerencia_comentario' => (string) ($row['resultado'] ?? '') === 'RECHAZADO'
                                            ? trim((string) ($row['comentario'] ?? ''))
                                            : null,
                                    ]);
                            }

                            $correctCount = $rows->where('resultado', 'CORRECTO')->count();
                            $rejectedCount = $rows->where('resultado', 'RECHAZADO')->count();

                            if ($correctCount === 0) {
                                $workflow = 'RECHAZADO_GERENCIA_FINANZAS';
                                $resultado = 'RECHAZADO';
                            } elseif ($rejectedCount > 0) {
                                $workflow = 'RECHAZADO_GERENCIA_FINANZAS_PARCIAL';
                                $resultado = 'PARCIAL';
                            } else {
                                $workflow = 'APROBADO_GERENCIA_FINANZAS';
                                $resultado = 'APROBADO';
                            }

                            $sumario->forceFill([
                                'workflow_estado' => $workflow,
                                'decision_gerencia_finanzas_at' => now(),
                                'decision_gerencia_por_user_id' => auth()->id(),
                                'decision_gerencia_resultado' => $resultado,
                                'decision_gerencia_comentario' => trim((string) ($data['comentario_gerencia'] ?? '')),
                            ])->save();
                        });

                        Notification::make()
                            ->title('Validacion de Gerencia registrada')
                            ->body('La revision por item fue guardada y el sumario regreso a Procura para generar ODC solo con items Correctos.')
                            ->success()
                            ->send();

                        ActivityNotification::record(
                            auth()->user(),
                            'Validacion por item de Gerencia',
                            'Gerencia de Finanzas valido por item el sumario ' . (string) $record->correlativo_sdc . '.',
                            'success'
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

                EditAction::make()
                    ->visible(fn ($record): bool => self::canEditDraftOrRejected($record)),

                DeleteAction::make()
                    ->visible(fn ($record): bool => self::canDeleteDraft($record)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function canEditDraftOrRejected(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || ! $user->can('Update:Sumario')) {
            return false;
        }

        return in_array((string) ($record->workflow_estado ?? 'BORRADOR'), [
            'BORRADOR',
            'RECHAZADO_VALIDACION_FINANZAS',
            'RECHAZADO_GERENCIA_FINANZAS',
            'RECHAZADO_GERENCIA_FINANZAS_PARCIAL',
        ], true);
    }

    private static function canDeleteDraft(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record || ! $user->can('Delete:Sumario')) {
            return false;
        }

        return (string) ($record->workflow_estado ?? 'BORRADOR') === 'BORRADOR'
            && blank($record->ordenesCompra()->first());
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

        return in_array((string) ($record->workflow_estado ?? 'BORRADOR'), [
            'BORRADOR',
            'ODC_GENERADA',
            'RECHAZADO_VALIDACION_FINANZAS',
            'RECHAZADO_GERENCIA_FINANZAS',
            'RECHAZADO_GERENCIA_FINANZAS_PARCIAL',
        ], true)
            && blank($record->ordenesCompra()->first());
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

        $workflow = (string) ($record->workflow_estado ?? '');

        if (! in_array($workflow, ['APROBADO_GERENCIA_FINANZAS', 'RECHAZADO_GERENCIA_FINANZAS_PARCIAL'], true)) {
            return false;
        }

        if (! blank($record->ordenesCompra()->first())) {
            return false;
        }

        return $record->items()
            ->where(function ($query): void {
                $query
                    ->whereNull('validacion_gerencia_resultado')
                    ->orWhere('validacion_gerencia_resultado', 'CORRECTO');
            })
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function buildGerenciaItemRevisionPayload(mixed $record): array
    {
        $sumario = $record->loadMissing('items');

        return $sumario->items
            ->map(function ($item): array {
                return [
                    'sumario_item_id' => $item->id,
                    'item' => (string) ($item->item ?: $item->id),
                    'descripcion' => (string) $item->descripcion,
                    'cantidad' => number_format((float) $item->cantidad, 2, ',', '.'),
                    'resultado' => (string) ($item->validacion_gerencia_resultado ?: 'CORRECTO'),
                    'comentario' => (string) ($item->validacion_gerencia_comentario ?? ''),
                ];
            })
            ->values()
            ->all();
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
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($sumarioItem->validacion_gerencia_resultado === 'RECHAZADO' ? 'X' : ($sumarioItem->validacion_gerencia_resultado === 'CORRECTO' ? 'Correcto' : '-'))) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($sumarioItem->validacion_gerencia_comentario ?? '-')) . '</td>'
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
            . '<th style="border:1px solid #d1d5db;padding:8px;">Gerencia</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Motivo X</th>'
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
