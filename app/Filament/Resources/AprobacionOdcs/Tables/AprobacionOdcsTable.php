<?php

namespace App\Filament\Resources\AprobacionOdcs\Tables;

use App\Filament\Resources\AprobacionOdcs\AprobacionOdcResource;
use App\Models\Sumario;
use App\Models\SolicitudCompra;
use App\Support\OdcModalSummaryRenderer;
use App\Support\SumarioModalSummaryRenderer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

class AprobacionOdcsTable
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

                TextColumn::make('solicitud_codigo_control')
                    ->label('Solicitud')
                    ->state(fn ($record): string => (string) ($record->sumario?->solicitudCompra?->codigo_control ?: '-'))
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
                    ->state(fn (): string => 'PENDIENTE APROBACION GERENCIA FINANZAS'),
            ])
            ->recordActions([
                Action::make('verSolicitudAsociada')
                    ->label('Ver solicitud asociada')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->visible(fn ($record): bool => (bool) $record->sumario?->solicitudCompra)
                    ->modalHeading(fn ($record): string => 'Solicitud asociada | #' . (string) ($record->sumario?->solicitudCompra?->numero_solicitud_usuario ?: $record->sumario?->solicitud_compra_id))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->fillForm(fn ($record): array => self::getSolicitudViewFormData($record))
                    ->schema(self::getSolicitudViewSchema()),

                Action::make('verSumario')
                    ->label('Ver sumario')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->visible(fn ($record): bool => filled($record->sumario_id))
                    ->modalHeading(fn ($record): string => 'Resumen | Sumario ' . (string) ($record->sumario?->correlativo_sdc ?? '-'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(fn ($record): HtmlString => new HtmlString(self::renderSumarioSummaryModal($record))),

                Action::make('aprobacionOdc')
                    ->label('Aprobacion de ODC')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('success')
                    ->modalHeading(fn ($record): string => 'Resumen ODC | ' . (string) ($record->correlativo_odc ?? ('#' . $record->id)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->extraModalFooterActions(fn ($record): array => [
                        Action::make('rechazarOdcDesdeResumen')
                            ->label('Rechazar ODC')
                            ->icon(Heroicon::OutlinedXCircle)
                            ->color('danger')
                            ->requiresConfirmation()
                            ->form([
                                Textarea::make('rechazo_comentario')
                                    ->label('Motivo de rechazo')
                                    ->rows(4)
                                    ->required()
                                    ->maxLength(2000),
                                TextInput::make('password')
                                    ->label('Clave de firma')
                                    ->password()
                                    ->required(),
                                TextInput::make('password_confirmation')
                                    ->label('Repetir clave de firma')
                                    ->password()
                                    ->required(),
                            ])
                            ->action(function (array $data) use ($record): void {
                                $password = (string) ($data['password'] ?? '');
                                $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

                                if ($password === '' || $password !== $passwordConfirmation) {
                                    Notification::make()
                                        ->title('No se pudo firmar')
                                        ->body('Debes escribir la misma clave de firma dos veces antes de rechazar.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $signatureHash = auth()->user()?->firma_password ?: auth()->user()?->password ?: '';

                                if (! Hash::check($password, $signatureHash)) {
                                    Notification::make()
                                        ->title('No se pudo firmar')
                                        ->body('La firma no se registro porque la clave de firma no coincide.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $record->forceFill([
                                    'estado' => 'RECHAZADA',
                                    'workflow_post_compra' => 'BORRADOR_ODC',
                                    'aprobado_por_user_id' => null,
                                    'aprobado_firmado_at' => null,
                                    'rechazo_etapa' => 'gerencia_finanzas',
                                    'rechazo_comentario' => trim((string) ($data['rechazo_comentario'] ?? '')),
                                    'rechazo_por_user_id' => auth()->id(),
                                    'rechazo_en' => now(),
                                ])->save();

                                $sumarioId = (int) ($record->sumario_id ?? 0);

                                if ($sumarioId > 0) {
                                    $sumario = Sumario::query()->find($sumarioId);

                                    if ($sumario) {
                                        $sumario->forceFill([
                                            'estado' => 'RECHAZADO',
                                            'workflow_estado' => 'RECHAZADO_GERENCIA_FINANZAS',
                                        ])->save();
                                    }
                                }

                                Notification::make()
                                    ->title('ODC rechazada por Gerencia de Finanzas')
                                    ->body('La orden fue rechazada y el sumario asociado paso a correcciones.')
                                    ->success()
                                    ->send();
                            }),

                        Action::make('enviarPagoFinanzasDesdeResumen')
                            ->label('Enviar a Pago Finanzas')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->color('success')
                            ->requiresConfirmation()
                            ->form([
                                TextInput::make('password')
                                    ->label('Clave de firma')
                                    ->password()
                                    ->required(),
                                TextInput::make('password_confirmation')
                                    ->label('Repetir clave de firma')
                                    ->password()
                                    ->required(),
                            ])
                            ->action(function (array $data) use ($record): void {
                                $password = (string) ($data['password'] ?? '');
                                $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

                                if ($password === '' || $password !== $passwordConfirmation) {
                                    Notification::make()
                                        ->title('No se pudo firmar')
                                        ->body('Debes escribir la misma clave de firma dos veces antes de enviar.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $signatureHash = auth()->user()?->firma_password ?: auth()->user()?->password ?: '';

                                if (! Hash::check($password, $signatureHash)) {
                                    Notification::make()
                                        ->title('No se pudo firmar')
                                        ->body('La firma no se registro porque la clave de firma no coincide.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $record->forceFill([
                                    'estado' => 'APROBADA',
                                    'workflow_post_compra' => 'PENDIENTE_PAGO_FINANZAS',
                                    'aprobado_por_user_id' => $record->aprobado_por_user_id ?: auth()->id(),
                                    'aprobado_firmado_at' => now(),
                                ])->save();

                                Notification::make()
                                    ->title('ODC enviada a Pago Finanzas')
                                    ->body('La ODC fue aprobada y enviada al modulo Administracion de Pagos ODC.')
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->modalContent(fn ($record): HtmlString => new HtmlString(
                        OdcModalSummaryRenderer::render($record)
                    )),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function getSolicitudViewSchema(): array
    {
        return [
            Section::make('Resumen de solicitud')
                ->schema([
                    Grid::make(6)
                        ->schema([
                            TextInput::make('codigo_control')->label('N° control')->disabled()->columnSpan(1),
                            TextInput::make('estado')->label('Estado')->disabled()->columnSpan(1),
                            TextInput::make('fecha_solicitud')->label('Fecha')->disabled()->columnSpan(1),
                            TextInput::make('tipo_solicitud')->label('Tipo')->disabled()->columnSpan(1),
                            TextInput::make('prioridad')->label('Prioridad')->disabled()->columnSpan(1),
                            TextInput::make('departamento_solicitante')->label('Departamento')->disabled()->columnSpan(1),
                            TextInput::make('solicitado_por_nombre')->label('Solicitado por')->disabled()->columnSpan(2),
                            TextInput::make('por_almacen_nombre')->label('Almacén')->disabled()->columnSpan(2),
                            TextInput::make('aprobado_por_nombre')->label('Aprobador')->disabled()->columnSpan(1),
                            TextInput::make('recibido_por_nombre')->label('Procura')->disabled()->columnSpan(1),
                        ]),
                    Textarea::make('para_ser_usado_en')
                        ->label('Para ser usado en')
                        ->rows(2)
                        ->disabled(),
                    Grid::make(4)
                        ->schema([
                            TextInput::make('fecha_almacen')->label('Fecha almacén')->disabled(),
                            TextInput::make('fecha_aprobador')->label('Fecha aprobador')->disabled(),
                            TextInput::make('fecha_receptor')->label('Fecha procura')->disabled(),
                            TextInput::make('hora_receptor')->label('Hora procura')->disabled(),
                        ]),
                ]),

            Section::make('Materiales / servicios solicitados')
                ->schema([
                    Placeholder::make('items_detalle')
                        ->label('Items')
                        ->content(fn (callable $get): HtmlString => new HtmlString(self::renderSolicitudItemsTable($get('items') ?? [])))
                        ->dehydrated(false),
                ]),

            Section::make('Motivo de rechazo')
                ->visible(fn (callable $get): bool => filled($get('rechazo_comentario')))
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('rechazo_etapa')->label('Etapa')->disabled(),
                            TextInput::make('rechazo_por_nombre')->label('Rechazada por')->disabled(),
                            TextInput::make('rechazo_en')->label('Fecha rechazo')->disabled(),
                        ]),
                    Textarea::make('rechazo_comentario')->label('Comentario')->rows(3)->disabled(),
                ]),
        ];
    }

    private static function getSolicitudViewFormData(mixed $record): array
    {
        $solicitud = self::resolveSolicitudFromRecord($record);

        if (! $solicitud) {
            return [];
        }

        return [
            'id' => $solicitud->id,
            'codigo_control' => $solicitud->codigo_control ?: $solicitud->id,
            'fecha_solicitud' => $solicitud->fecha_solicitud?->format('d/m/Y'),
            'estado' => str_replace('_', ' ', (string) $solicitud->estado),
            'departamento_solicitante' => $solicitud->departamento_solicitante,
            'tipo_solicitud' => $solicitud->tipo_solicitud,
            'prioridad' => $solicitud->prioridad,
            'para_ser_usado_en' => $solicitud->para_ser_usado_en,
            'items' => $solicitud->items
                ->map(fn ($item) => [
                    'item' => $item->item,
                    'descripcion' => $item->descripcion,
                    'unidad_medida' => $item->unidad_medida,
                    'cantidad_solicitada' => $item->cantidad_solicitada,
                    'cantidad_existencia' => $item->cantidad_existencia,
                    'cantidad_a_comprar' => $item->cantidad_a_comprar,
                ])
                ->values()
                ->all(),
            'solicitado_por_nombre' => $solicitud->solicitadoPor?->name,
            'por_almacen_nombre' => $solicitud->porAlmacen?->name,
            'aprobado_por_nombre' => $solicitud->aprobadoPor?->name,
            'recibido_por_nombre' => $solicitud->recibidoPor?->name,
            'fecha_almacen' => $solicitud->fecha_almacen?->format('d/m/Y'),
            'fecha_aprobador' => $solicitud->fecha_aprobador?->format('d/m/Y'),
            'fecha_receptor' => $solicitud->fecha_receptor?->format('d/m/Y'),
            'hora_receptor' => $solicitud->hora_receptor,
            'rechazo_etapa' => $solicitud->rechazo_etapa ? strtoupper((string) $solicitud->rechazo_etapa) : null,
            'rechazo_por_nombre' => $solicitud->rechazoPor?->name,
            'rechazo_en' => $solicitud->rechazo_en?->format('d/m/Y H:i'),
            'rechazo_comentario' => $solicitud->rechazo_comentario,
        ];
    }

    private static function renderSolicitudItemsTable(array $items): string
    {
        if ($items === []) {
            return '<div style="padding:12px 0;color:#6b7280;">Sin items registrados.</div>';
        }

        $rows = collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item, int $index): string {
                return '<tr>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item['item'] ?? $index + 1)) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;">' . e((string) ($item['descripcion'] ?? '')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:center;">' . e((string) ($item['unidad_medida'] ?? '')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . e((string) ($item['cantidad_solicitada'] ?? '')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . e((string) ($item['cantidad_existencia'] ?? '')) . '</td>'
                    . '<td style="border:1px solid #d1d5db;padding:8px;text-align:right;">' . e((string) ($item['cantidad_a_comprar'] ?? '')) . '</td>'
                    . '</tr>';
            })
            ->implode('');

        return '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">UND</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Solicitada</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">Existencia</th>'
            . '<th style="border:1px solid #d1d5db;padding:8px;">A comprar</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '</div>';
    }

    private static function resolveSolicitudFromRecord(mixed $record): ?SolicitudCompra
    {
        $solicitudId = (int) ($record->sumario?->solicitud_compra_id ?? 0);

        if ($solicitudId <= 0) {
            return null;
        }

        return SolicitudCompra::query()->find($solicitudId);
    }

    private static function renderSumarioSummaryModal(mixed $record): string
    {
        $sumario = $record->sumario;

        if (! $sumario) {
            return '<div style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;">No se encontro el sumario.</div>';
        }

        return SumarioModalSummaryRenderer::render($sumario);
    }
}
