<?php

namespace App\Filament\Resources\Sumarios\Tables;

use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use App\Support\ActivityNotification;
use App\Support\SolicitudItemTrackingService;
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
    private const SUBESTADO_PENDIENTE_REVALIDACION = 'PENDIENTE_REVALIDACION_GERENCIA';

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

                TextColumn::make('mensaje_pago_transito')
                    ->label('Mensaje dinamico')
                    ->state(fn ($record): string => self::buildPaidTransitMessage($record))
                    ->wrap()
                    ->toggleable(),

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
                    ->label('Total Prov. 1')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('total_compra_prov2')
                    ->label('Total Prov. 2')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('total_compra_prov3')
                    ->label('Total Prov. 3')
                    ->money('USD')
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

                Action::make('sumarioCorreccion')
                    ->label('Sumario en correccion')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('warning')
                    ->modalHeading(fn ($record): string => 'Correccion inteligente | Sumario ' . (string) $record->correlativo_sdc)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(self::renderCorrectionBoard($record)))
                    ->visible(fn ($record): bool => self::canUseCorrectionBoard($record)),

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
                    ->visible(fn ($record): bool => self::canGerenciaFinanceDecision($record)
                        && in_array((string) ($record->workflow_estado ?? ''), ['VALIDADO_FINANZAS', 'RECHAZADO_GERENCIA_FINANZAS', 'RECHAZADO_GERENCIA_FINANZAS_PARCIAL'], true))
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
                                        'sub_estado' => (string) ($row['resultado'] ?? '') === 'RECHAZADO'
                                            ? 'RECHAZADO_GERENCIA'
                                            : 'PENDIENTE_OC',
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

                Action::make('editarItemRechazado')
                    ->label('Editar item rechazado')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('info')
                    ->form([
                        Select::make('sumario_item_id')
                            ->label('Item rechazado')
                            ->options(fn ($record): array => self::rejectedItemOptions($record))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, $record): void {
                                self::hydrateRejectedItemOption($record, (int) ($state ?? 0), 1, $set);
                            }),

                        Select::make('opcion_numero')
                            ->label('Proveedor a corregir (columna)')
                            ->options([
                                1 => 'Proveedor 1',
                                2 => 'Proveedor 2',
                                3 => 'Proveedor 3',
                            ])
                            ->default(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get, $record): void {
                                self::hydrateRejectedItemOption(
                                    $record,
                                    (int) ($get('sumario_item_id') ?? 0),
                                    (int) ($state ?? 1),
                                    $set
                                );
                            }),

                        TextInput::make('proveedor_nombre')
                            ->label('Proveedor')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('marca')
                            ->label('Marca')
                            ->maxLength(255),

                        TextInput::make('precio_unitario')
                            ->label('Precio unitario')
                            ->numeric()
                            ->required(),

                        Select::make('marcar_seleccionada')
                            ->label('Usar esta opcion para ODC')
                            ->options([
                                '1' => 'Si',
                                '0' => 'No',
                            ])
                            ->default('1')
                            ->required(),
                    ])
                    ->fillForm(fn ($record): array => self::defaultCorrectionEditData($record))
                    ->visible(fn ($record): bool => self::canEditRejectedItems($record))
                    ->action(function (array $data, $record): void {
                        DB::transaction(function () use ($data, $record): void {
                            $sumario = Sumario::query()
                                ->with('items.opciones')
                                ->lockForUpdate()
                                ->findOrFail($record->id);

                            $sumarioItem = SumarioItem::query()
                                ->where('sumario_id', $sumario->id)
                                ->whereKey((int) ($data['sumario_item_id'] ?? 0))
                                ->firstOrFail();

                            $opcionNumero = (int) ($data['opcion_numero'] ?? 1);
                            if (! in_array($opcionNumero, [1, 2, 3], true)) {
                                $opcionNumero = 1;
                            }

                            $opcion = SumarioItemOpcion::query()->firstOrNew([
                                'sumario_item_id' => $sumarioItem->id,
                                'opcion_numero' => $opcionNumero,
                            ]);

                            $precioUnitario = round((float) ($data['precio_unitario'] ?? 0), 2);
                            $cantidad = round((float) ($sumarioItem->cantidad ?? 0), 2);

                            $opcion->fill([
                                'proveedor_nombre' => trim((string) ($data['proveedor_nombre'] ?? '')),
                                'marca' => trim((string) ($data['marca'] ?? '')),
                                'precio_unitario' => $precioUnitario,
                                'precio_total' => round($cantidad * $precioUnitario, 2),
                            ]);
                            $opcion->save();

                            if ((string) ($data['marcar_seleccionada'] ?? '1') === '1') {
                                SumarioItemOpcion::query()
                                    ->where('sumario_item_id', $sumarioItem->id)
                                    ->update(['seleccionada' => false]);

                                $opcion->forceFill(['seleccionada' => true])->save();
                            }

                            $sumarioItem->forceFill([
                                'sub_estado' => self::SUBESTADO_PENDIENTE_REVALIDACION,
                            ])->save();

                            self::refreshWorkflowAfterCorrection($sumario);
                        });

                        Notification::make()
                            ->title('Item en correccion actualizado')
                            ->body('El item quedo en estado Pendiente de Validacion para una nueva revision de Gerencia.')
                            ->success()
                            ->send();
                    }),

                Action::make('retornarItemRechazado')
                    ->label('Eliminar/Retornar item')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Select::make('sumario_item_id')
                            ->label('Item rechazado a retornar')
                            ->options(fn ($record): array => self::rejectedItemOptions($record))
                            ->searchable()
                            ->required(),
                        Textarea::make('motivo_retorno')
                            ->label('Motivo de retorno (opcional)')
                            ->rows(2),
                    ])
                    ->visible(fn ($record): bool => self::canEditRejectedItems($record))
                    ->action(function (array $data, $record): void {
                        DB::transaction(function () use ($data, $record): void {
                            $sumario = Sumario::query()
                                ->with('items')
                                ->lockForUpdate()
                                ->findOrFail($record->id);

                            $sumarioItem = SumarioItem::query()
                                ->where('sumario_id', $sumario->id)
                                ->whereKey((int) ($data['sumario_item_id'] ?? 0))
                                ->firstOrFail();

                            $solicitudCompraItemId = (int) $sumarioItem->solicitud_compra_item_id;

                            $sumarioItem->opciones()->delete();
                            $sumarioItem->delete();

                            SolicitudItemTrackingService::syncByItemIds([$solicitudCompraItemId]);
                            self::refreshWorkflowAfterCorrection($sumario);
                        });

                        Notification::make()
                            ->title('Item retornado a bandeja pendiente')
                            ->body('El item fue removido del sumario y su cantidad quedo liberada para nuevo sumario.')
                            ->success()
                            ->send();
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
            ->where(function ($query): void {
                $query
                    ->whereNull('sub_estado')
                    ->orWhere('sub_estado', '!=', self::SUBESTADO_PENDIENTE_REVALIDACION);
            })
            ->exists();
    }

    private static function canUseCorrectionBoard(mixed $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! $user->can('Update:Sumario')) {
            return false;
        }

        return in_array((string) ($record->workflow_estado ?? ''), [
            'RECHAZADO_GERENCIA_FINANZAS',
            'RECHAZADO_GERENCIA_FINANZAS_PARCIAL',
            'APROBADO_GERENCIA_FINANZAS',
        ], true);
    }

    private static function canEditRejectedItems(mixed $record): bool
    {
        if (! self::canUseCorrectionBoard($record)) {
            return false;
        }

        return SumarioItem::query()
            ->where('sumario_id', (int) $record->id)
            ->where(function ($query): void {
                $query
                    ->where('validacion_gerencia_resultado', 'RECHAZADO')
                    ->orWhere('sub_estado', self::SUBESTADO_PENDIENTE_REVALIDACION);
            })
            ->exists();
    }

    private static function renderCorrectionBoard(mixed $record): string
    {
        $sumario = $record->loadMissing(['items.opciones']);

        $validos = $sumario->items->filter(function ($item): bool {
            return (string) ($item->validacion_gerencia_resultado ?? '') === 'CORRECTO'
                && (string) ($item->sub_estado ?? '') !== self::SUBESTADO_PENDIENTE_REVALIDACION;
        })->values();

        $rechazados = $sumario->items->filter(function ($item): bool {
            $resultado = (string) ($item->validacion_gerencia_resultado ?? '');
            $subEstado = (string) ($item->sub_estado ?? '');

            return $resultado === 'RECHAZADO' || $subEstado === self::SUBESTADO_PENDIENTE_REVALIDACION;
        })->values();

        $head = '<div style="margin-bottom:12px;padding:10px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">'
            . '<strong>Regla activa:</strong> Los items CORRECTOS estan habilitados para generar ODC de inmediato. '
            . 'Los items del grupo rechazado/correccion se gestionan con las acciones "Editar item rechazado" y "Eliminar/Retornar item" sin bloquear el Grupo A.'
            . '</div>';

        $rowsA = $validos->map(function ($item): string {
            return '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item->item ?: $item->id)) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $item->descripcion) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) $item->cantidad, 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">Correcto</td>'
                . '</tr>';
        })->implode('');

        if ($rowsA === '') {
            $rowsA = '<tr><td colspan="4" style="border:1px solid #d1d5db;padding:8px;">No hay items validados actualmente.</td></tr>';
        }

        $rowsB = $rechazados->map(function ($item): string {
            $estadoCorreccion = (string) ($item->sub_estado ?? '') === self::SUBESTADO_PENDIENTE_REVALIDACION
                ? 'Pendiente de revalidacion'
                : 'X (Rechazado)';

            return '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item->item ?: $item->id)) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) $item->descripcion) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e($estadoCorreccion) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item->validacion_gerencia_comentario ?: 'Sin comentario registrado')) . '</td>'
                . '</tr>';
        })->implode('');

        if ($rowsB === '') {
            $rowsB = '<tr><td colspan="4" style="border:1px solid #d1d5db;padding:8px;">No hay items rechazados o en correccion.</td></tr>';
        }

        return $head
            . '<div style="display:grid;grid-template-columns:1fr;gap:12px;">'
            . '<div style="overflow:auto;">'
            . '<div style="font-weight:700;margin-bottom:6px;">Grupo A | Items Validados</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#ecfdf5;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cantidad</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Estado</th>'
            . '</tr></thead><tbody>' . $rowsA . '</tbody></table>'
            . '</div>'
            . '<div style="overflow:auto;">'
            . '<div style="font-weight:700;margin-bottom:6px;">Grupo B | Rechazados y en Correccion</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#fff7ed;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Estado</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Motivo de rechazo / historial visible</th>'
            . '</tr></thead><tbody>' . $rowsB . '</tbody></table>'
            . '</div>'
            . '</div>';
    }

    private static function rejectedItemOptions(mixed $record): array
    {
        return SumarioItem::query()
            ->where('sumario_id', (int) $record->id)
            ->where(function ($query): void {
                $query
                    ->where('validacion_gerencia_resultado', 'RECHAZADO')
                    ->orWhere('sub_estado', self::SUBESTADO_PENDIENTE_REVALIDACION);
            })
            ->orderBy('item')
            ->orderBy('id')
            ->get(['id', 'item', 'descripcion', 'validacion_gerencia_comentario'])
            ->mapWithKeys(function (SumarioItem $item): array {
                $label = '#' . (string) ($item->item ?: $item->id)
                    . ' | ' . (string) $item->descripcion
                    . ' | Motivo: ' . (string) ($item->validacion_gerencia_comentario ?: 'Sin comentario');

                return [$item->id => $label];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultCorrectionEditData(mixed $record): array
    {
        $firstRejectedItemId = SumarioItem::query()
            ->where('sumario_id', (int) $record->id)
            ->where(function ($query): void {
                $query
                    ->where('validacion_gerencia_resultado', 'RECHAZADO')
                    ->orWhere('sub_estado', self::SUBESTADO_PENDIENTE_REVALIDACION);
            })
            ->orderBy('id')
            ->value('id');

        if (! $firstRejectedItemId) {
            return [
                'opcion_numero' => 1,
                'marcar_seleccionada' => '1',
            ];
        }

        $result = [
            'sumario_item_id' => (int) $firstRejectedItemId,
            'opcion_numero' => 1,
            'marcar_seleccionada' => '1',
        ];

        $item = SumarioItem::query()
            ->with(['opciones' => fn ($query) => $query->orderBy('opcion_numero')])
            ->find((int) $firstRejectedItemId);

        $option = $item?->opciones->firstWhere('seleccionada', true) ?: $item?->opciones->first();

        if ($option) {
            $result['opcion_numero'] = (int) ($option->opcion_numero ?: 1);
            $result['proveedor_nombre'] = (string) ($option->proveedor_nombre ?? '');
            $result['marca'] = (string) ($option->marca ?? '');
            $result['precio_unitario'] = round((float) ($option->precio_unitario ?? 0), 2);
            $result['marcar_seleccionada'] = (bool) ($option->seleccionada ?? false) ? '1' : '0';
        }

        return $result;
    }

    private static function hydrateRejectedItemOption(mixed $record, int $sumarioItemId, int $optionNumber, callable $set): void
    {
        if ($sumarioItemId <= 0) {
            return;
        }

        $item = SumarioItem::query()
            ->with('opciones')
            ->where('sumario_id', (int) $record->id)
            ->find($sumarioItemId);

        if (! $item) {
            return;
        }

        if (! in_array($optionNumber, [1, 2, 3], true)) {
            $optionNumber = 1;
        }

        $option = $item->opciones->firstWhere('opcion_numero', $optionNumber)
            ?: $item->opciones->firstWhere('seleccionada', true)
            ?: $item->opciones->first();

        if (! $option) {
            return;
        }

        $set('opcion_numero', (int) ($option->opcion_numero ?? $optionNumber));
        $set('proveedor_nombre', (string) ($option->proveedor_nombre ?? ''));
        $set('marca', (string) ($option->marca ?? ''));
        $set('precio_unitario', round((float) ($option->precio_unitario ?? 0), 2));
        $set('marcar_seleccionada', (bool) ($option->seleccionada ?? false) ? '1' : '0');
    }

    private static function refreshWorkflowAfterCorrection(Sumario $sumario): void
    {
        $sumario = $sumario->fresh()->load('items');

        $items = $sumario->items;

        $hasCorrect = $items->contains(fn ($item): bool => (string) ($item->validacion_gerencia_resultado ?? '') === 'CORRECTO');
        $hasRejected = $items->contains(fn ($item): bool => (string) ($item->validacion_gerencia_resultado ?? '') === 'RECHAZADO');
        $hasPendingRevalidation = $items->contains(fn ($item): bool => (string) ($item->sub_estado ?? '') === self::SUBESTADO_PENDIENTE_REVALIDACION);

        $workflow = (string) ($sumario->workflow_estado ?? '');
        $decision = (string) ($sumario->decision_gerencia_resultado ?? '');

        if ($hasRejected) {
            if ($hasCorrect || $hasPendingRevalidation) {
                $workflow = 'RECHAZADO_GERENCIA_FINANZAS_PARCIAL';
                $decision = 'PARCIAL';
            } else {
                $workflow = 'RECHAZADO_GERENCIA_FINANZAS';
                $decision = 'RECHAZADO';
            }
        } elseif ($hasPendingRevalidation) {
            if ($hasCorrect) {
                $workflow = 'RECHAZADO_GERENCIA_FINANZAS_PARCIAL';
                $decision = 'PARCIAL';
            } else {
                $workflow = 'VALIDADO_FINANZAS';
                $decision = 'PENDIENTE_REVALIDACION';
            }
        } elseif ($hasCorrect) {
            $workflow = 'APROBADO_GERENCIA_FINANZAS';
            $decision = 'APROBADO';
        }

        $sumario->forceFill([
            'workflow_estado' => $workflow,
            'decision_gerencia_resultado' => $decision,
        ])->save();
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
        $sumario = $record->loadMissing(['items.opciones']);

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
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($opciones->get(2)?->proveedor_nombre ?? '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($opciones->get(2)?->marca ?? '-')) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($opciones->get(2)?->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . number_format((float) ($opciones->get(2)?->precio_total ?? 0), 2, ',', '.') . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($opciones->get(3)?->proveedor_nombre ?? '-')) . '</td>'
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
            . '<div><strong>Moneda:</strong> $ USD</div>'
            . '</div>';

        $table = '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead>'
            . '<tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">UND</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Cantidad</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Proveedor 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Marca 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/U 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/T 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Proveedor 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Marca 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/U 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/T 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Proveedor 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Marca 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/U 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">P/T 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Gerencia</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Motivo X</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot>'
            . '<tr style="background:#f9fafb;font-weight:600;">'
            . '<td colspan="6" style="border:1px solid #d1d5db;padding:8px;text-align:right;">Total compra Proveedor 1</td>'
            . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">$ ' . number_format((float) $sumario->total_compra_prov1, 2, ',', '.') . '</td>'
            . '<td colspan="3" style="border:1px solid #d1d5db;padding:8px;text-align:right;">Total compra Proveedor 2</td>'
            . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">$ ' . number_format((float) $sumario->total_compra_prov2, 2, ',', '.') . '</td>'
            . '<td colspan="3" style="border:1px solid #d1d5db;padding:8px;text-align:right;">Total compra Proveedor 3</td>'
            . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">$ ' . number_format((float) $sumario->total_compra_prov3, 2, ',', '.') . '</td>'
            . '<td colspan="2" style="border:1px solid #d1d5db;padding:8px;"></td>'
            . '</tr>'
            . '</tfoot>'
            . '</table>'
            . '</div>';

        return $headerInfo . $table;
    }

    private static function buildPaidTransitMessage(mixed $record): string
    {
        $sumario = $record->loadMissing(['ordenesCompra.items']);

        $segments = [];

        foreach ($sumario->ordenesCompra as $ordenCompra) {
            if ((string) ($ordenCompra->workflow_post_compra ?? '') !== 'PAGADO_Y_EN_TRANSITO') {
                continue;
            }

            $itemsText = $ordenCompra->items
                ->map(function ($item): string {
                    $itemNumero = (string) ($item->item ?: $item->id);

                    return '#' . $itemNumero . ' ' . (string) $item->descripcion;
                })
                ->implode(', ');

            if ($itemsText === '') {
                continue;
            }

            $segments[] = 'Los siguientes items de la OC ' . (string) $ordenCompra->id . ' fueron pagados y estan en transito: ' . $itemsText;
        }

        return $segments === [] ? 'Sin items pagados y en transito.' : implode(' | ', $segments);
    }
}
