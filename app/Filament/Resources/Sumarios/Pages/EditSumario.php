<?php

namespace App\Filament\Resources\Sumarios\Pages;

use App\Filament\Resources\Sumarios\SumarioResource;
use App\Models\OrdenCompraItem;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use App\Support\SolicitudItemTrackingService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class EditSumario extends EditRecord
{
    protected static string $resource = SumarioResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected Width | string | null $maxWidth = Width::Full;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getSaveFormAction(): Action
    {
        if ((string) ($this->record->workflow_estado ?? '') === 'BORRADOR') {
            return Action::make('saveDraft')
                ->label('Guardar borrador')
                ->color('primary')
                ->keyBindings(['mod+s'])
                ->action(function (): void {
                    $this->saveDraft();
                });
        }

        return parent::getSaveFormAction();
    }

    protected function getHeaderActions(): array
    {
        $workflow = (string) ($this->record->workflow_estado ?? '');

        $actions = [];

        if ($this->isGerenciaRejectedWorkflow($workflow)) {
            $actions[] = $this->makeViewGerenciaCorrectionsAction();
        }

        if ($this->isRejectedWorkflow($workflow)) {
            $actions[] = $this->makeArchiveRejectedToHistoryAction();
        }

        return $actions;
    }

    protected function getFormActions(): array
    {
        $workflow = (string) ($this->record->workflow_estado ?? '');

        if ($workflow === 'BORRADOR') {
            return [
                $this->getSaveFormAction(),
                Action::make('submitForFinanceValidation')
                    ->label('Enviar a Validacion Finanzas')
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
                    ->action(function (array $data): void {
                        $this->submitForFinanceValidation($data);
                    }),
            ];
        }

        if ($this->isGerenciaRejectedWorkflow($workflow)) {
            return [
                Action::make('submitForFinanceValidation')
                    ->label('Enviar a Validacion Finanzas')
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
                    ->action(function (array $data): void {
                        $this->submitForFinanceValidation($data);
                    }),
                Action::make('submitForGerenciaValidation')
                    ->label('Enviar a Gerencia Finanzas')
                    ->color('info')
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
                    ->action(function (array $data): void {
                        $this->submitForGerenciaValidation($data);
                    }),
            ];
        }

        if ($workflow === 'RECHAZADO_VALIDACION_FINANZAS') {
            return [
                Action::make('submitForFinanceValidation')
                    ->label('Enviar a Validacion Finanzas')
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
                    ->action(function (array $data): void {
                        $this->submitForFinanceValidation($data);
                    }),
            ];
        }

        return parent::getFormActions();
    }

    private function makeViewGerenciaCorrectionsAction(): Action
    {
        return Action::make('viewGerenciaCorrections')
            ->label('Ver Correcciones de Gerencia')
            ->color('warning')
            ->modalHeading(fn (): string => 'Correcciones de Gerencia | Sumario ' . (string) ($this->record->correlativo_sdc ?? ''))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalWidth('7xl')
            ->modalContent(fn (): HtmlString => new HtmlString($this->renderGerenciaCorrectionsPreview()));
    }

    private function makeArchiveRejectedToHistoryAction(): Action
    {
        return Action::make('archiveRejectedToHistory')
            ->label('Eliminar para Historial')
            ->icon('heroicon-o-archive-box-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Enviar rechazo definitivo al historial')
            ->modalDescription('Este sumario quedara como RECHAZADO en historial y no seguira en correccion.')
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
            ->action(function (array $data): void {
                if (! $this->validateSignaturePassword($data)) {
                    return;
                }

                /** @var Sumario $sumario */
                $sumario = $this->record->fresh();

                if (! $this->isRejectedWorkflow((string) ($sumario->workflow_estado ?? ''))) {
                    Notification::make()
                        ->title('Accion no disponible')
                        ->body('Solo aplica para sumarios rechazados por Validacion Finanzas o Gerencia Finanzas.')
                        ->warning()
                        ->send();

                    return;
                }

                $sumario->forceFill([
                    'workflow_estado' => 'RECHAZADO',
                    'estado' => 'RECHAZADO',
                ])->save();

                Notification::make()
                    ->title('Enviado a historial')
                    ->body('El sumario fue marcado como RECHAZADO definitivo y movido al historial.')
                    ->success()
                    ->send();

                $this->redirect(SumarioResource::getUrl('index', ['activeTab' => 'sumarios']));
            });
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Sumario $sumario */
        $sumario = $this->record->load([
            'items.opciones',
            'elaboradoPor.cargo',
            'revisadoPor.cargo',
        ]);
        $workflow = (string) ($sumario->workflow_estado ?? '');
        $isRejectedWorkflow = $this->isRejectedWorkflow($workflow);

        $rows = [];

        foreach ($sumario->items as $sumarioItem) {
            $opciones = $sumarioItem->opciones->keyBy('opcion_numero');

            $rows[] = [
                'solicitud_compra_item_id' => $sumarioItem->solicitud_compra_item_id,
                'item' => $sumarioItem->item,
                'descripcion' => $sumarioItem->descripcion,
                'unidad_medida' => $sumarioItem->unidad_medida,
                'cantidad' => (float) $sumarioItem->cantidad,
                'marca_prov1' => $opciones->get(1)?->marca,
                'precio_unitario_prov1' => (float) ($opciones->get(1)?->precio_unitario ?? 0),
                'precio_total_prov1' => (float) ($opciones->get(1)?->precio_total ?? 0),
                'marca_prov2' => $opciones->get(2)?->marca,
                'precio_unitario_prov2' => (float) ($opciones->get(2)?->precio_unitario ?? 0),
                'precio_total_prov2' => (float) ($opciones->get(2)?->precio_total ?? 0),
                'marca_prov3' => $opciones->get(3)?->marca,
                'precio_unitario_prov3' => (float) ($opciones->get(3)?->precio_unitario ?? 0),
                'precio_total_prov3' => (float) ($opciones->get(3)?->precio_total ?? 0),
                'proveedor_seleccionado' => $this->resolveSelectedColumn($opciones->all()),
            ];
        }

        $data['comparativo_items'] = $rows;
        $data['selected_item_ids'] = collect($rows)
            ->pluck('solicitud_compra_item_id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();

        $data['proveedor_a_nombre'] = $sumario->items->first()?->opciones->firstWhere('opcion_numero', 1)?->proveedor_nombre;
        $data['proveedor_b_nombre'] = $sumario->items->first()?->opciones->firstWhere('opcion_numero', 2)?->proveedor_nombre;
        $data['proveedor_c_nombre'] = $sumario->items->first()?->opciones->firstWhere('opcion_numero', 3)?->proveedor_nombre;
        $data['elaborado_por_preview'] = (string) ($sumario->elaboradoPor?->name ?? auth()->user()?->name ?? 'N/A');
        $data['elaborado_cargo_preview'] = (string) ($sumario->elaboradoPor?->cargo?->nombre ?? auth()->user()?->cargo?->nombre ?? 'Sin cargo');
        $data['firma_procura_preview'] = ! $isRejectedWorkflow && filled($sumario->enviado_validacion_finanzas_at)
            ? 'Registrada'
            : 'Se registra al enviar';
        $data['fecha_elaborado_preview'] = (string) ($sumario->fecha?->format('d/m/Y') ?? now()->format('d/m/Y'));
        $data['revisado_cargo_preview'] = (string) ($sumario->revisadoPor?->cargo?->nombre ?? 'Sin cargo');
        $data['firma_revisado_preview'] = ! $isRejectedWorkflow && filled($sumario->validado_finanzas_at)
            ? 'Registrada'
            : 'Se registra al validar en Finanzas';
        $data['fecha_revisado_preview'] = (string) (! $isRejectedWorkflow && $sumario->validado_finanzas_at
            ? $sumario->validado_finanzas_at->format('d/m/Y')
            : '-');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $currentWorkflow = (string) ($this->record->workflow_estado ?? 'BORRADOR');
        $currentEstado = (string) ($this->record->estado ?? 'BORRADOR');
        $rows = self::normalizeRows($data['comparativo_items'] ?? []);

        $totals = [
            'A' => 0.0,
            'B' => 0.0,
            'C' => 0.0,
        ];

        foreach ($rows as $row) {
            $selected = strtoupper(trim((string) ($row['proveedor_seleccionado'] ?? '')));

            if (! in_array($selected, ['A', 'B', 'C'], true)) {
                continue;
            }

            $key = 'precio_total_prov' . match ($selected) {
                'A' => '1',
                'B' => '2',
                'C' => '3',
            };

            $totals[$selected] += filled($row[$key] ?? null) ? (float) $row[$key] : 0.0;
        }

        $data['total_compra_prov1'] = round($totals['A'], 2);
        $data['total_compra_prov2'] = round($totals['B'], 2);
        $data['total_compra_prov3'] = round($totals['C'], 2);

        if ($currentWorkflow === 'BORRADOR') {
            $data['estado'] = 'BORRADOR';
            $data['workflow_estado'] = 'BORRADOR';
        } else {
            $data['estado'] = $currentEstado;
            $data['workflow_estado'] = $currentWorkflow;
        }
        $data['elaborado_por_user_id'] = $data['elaborado_por_user_id'] ?? auth()->id();
        $data['proveedor_ganador_id'] = null;

        if (blank($data['departamento_solicitante'] ?? null) && filled($data['solicitud_compra_id'] ?? null)) {
            $data['departamento_solicitante'] = SolicitudCompra::query()
                ->whereKey($data['solicitud_compra_id'])
                ->value('departamento_solicitante');
        }

        return $data;
    }

    private function saveDraft(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Sumario || (string) $record->workflow_estado !== 'BORRADOR') {
            return;
        }

        $rawState = $this->form->getRawState();
        $data = $this->prepareDraftData($rawState, $record);

        if (blank($data['solicitud_compra_id'] ?? null)) {
            Notification::make()
                ->title('No se pudo guardar borrador')
                ->body('Selecciona una solicitud base para mantener este borrador en la lista.')
                ->danger()
                ->send();

            return;
        }

        $updated = $this->handleRecordUpdate($record, $data);

        $this->record = $updated instanceof Sumario ? $updated : $record->fresh();
        $this->fillForm();

        Notification::make()
            ->title((string) $record->workflow_estado === 'BORRADOR' ? 'Borrador guardado' : 'Cambios guardados')
            ->body((string) $record->workflow_estado === 'BORRADOR'
                ? 'Borrador guardado exitosamente.'
                : 'El sumario se mantuvo en correccion. Usa "Enviar a Validacion Finanzas" cuando este listo.')
            ->success()
            ->send();
    }

    private function submitForFinanceValidation(array $authData): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Sumario || ! $this->isSubmittableWorkflow((string) $record->workflow_estado)) {
            return;
        }

        if (! auth()->user()?->can('SubmitValidation:Sumario')) {
            Notification::make()
                ->title('Sin permisos')
                ->body('No tienes permisos para enviar el sumario a validacion.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->validateSignaturePassword($authData)) {
            return;
        }

        $validatedState = $this->form->getState();
        $data = $this->prepareDraftData($validatedState, $record);

        if (blank($data['solicitud_compra_id'] ?? null)) {
            Notification::make()
                ->title('No se pudo enviar')
                ->body('Selecciona una solicitud base antes de enviar a validacion.')
                ->danger()
                ->send();

            return;
        }

        $data['estado'] = 'PENDIENTE_REVISION_FINANZAS';
        $data['workflow_estado'] = 'PENDIENTE_VALIDACION_FINANZAS';
        $data['enviado_validacion_finanzas_at'] = now();
        $data['enviado_por_user_id'] = auth()->id();
        $data['validado_finanzas_at'] = null;
        $data['validado_por_user_id'] = null;
        $data['validacion_finanzas_resultado'] = null;
        $data['validacion_finanzas_comentario'] = null;
        $data['decision_gerencia_finanzas_at'] = null;
        $data['decision_gerencia_por_user_id'] = null;
        $data['decision_gerencia_resultado'] = null;
        $data['decision_gerencia_comentario'] = null;

        $updated = $this->handleRecordUpdate($record, $data);

        $this->record = $updated instanceof Sumario ? $updated : $record->fresh();
        $this->fillForm();

        Notification::make()
            ->title('Sumario enviado')
            ->body('El sumario fue enviado a validacion de Finanzas.')
            ->success()
            ->send();

        $this->redirect(SumarioResource::getUrl('index'));
    }

    private function submitForGerenciaValidation(array $authData): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Sumario || ! $this->isGerenciaRejectedWorkflow((string) $record->workflow_estado)) {
            return;
        }

        if (! auth()->user()?->can('SubmitValidation:Sumario')) {
            Notification::make()
                ->title('Sin permisos')
                ->body('No tienes permisos para enviar el sumario a Gerencia Finanzas.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->validateSignaturePassword($authData)) {
            return;
        }

        $validatedState = $this->form->getState();
        $data = $this->prepareDraftData($validatedState, $record);

        if (blank($data['solicitud_compra_id'] ?? null)) {
            Notification::make()
                ->title('No se pudo enviar')
                ->body('Selecciona una solicitud base antes de enviar a Gerencia Finanzas.')
                ->danger()
                ->send();

            return;
        }

        $data['estado'] = 'EN_ESPERA_APROBACION_GERENCIA';
        $data['workflow_estado'] = 'VALIDADO_FINANZAS';
        $data['validado_finanzas_at'] = $record->validado_finanzas_at ?: now();
        $data['validado_por_user_id'] = $record->validado_por_user_id ?: auth()->id();
        $data['validacion_finanzas_resultado'] = 'APROBADO';
        $data['validacion_finanzas_comentario'] = null;
        $data['decision_gerencia_finanzas_at'] = null;
        $data['decision_gerencia_por_user_id'] = null;
        $data['decision_gerencia_resultado'] = null;
        $data['decision_gerencia_comentario'] = null;

        $updated = $this->handleRecordUpdate($record, $data);

        $this->record = $updated instanceof Sumario ? $updated : $record->fresh();
        $this->fillForm();

        Notification::make()
            ->title('Sumario enviado')
            ->body('El sumario fue enviado directo a Gerencia Finanzas para nueva decision.')
            ->success()
            ->send();

        $this->redirect(SumarioResource::getUrl('index'));
    }

    private function validateSignaturePassword(array $data): bool
    {
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

        if ($password === '' || $password !== $passwordConfirmation) {
            Notification::make()
                ->title('No se pudo firmar')
                ->body('Debes escribir la misma clave de firma dos veces antes de enviar.')
                ->danger()
                ->send();

            return false;
        }

        $signatureHash = auth()->user()?->firma_password ?: auth()->user()?->password ?: '';

        if (Hash::check($password, $signatureHash)) {
            return true;
        }

        Notification::make()
            ->title('No se pudo firmar')
            ->body('La firma no se registro porque la clave de firma no coincide.')
            ->danger()
            ->send();

        return false;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Sumario $sumario */
        $sumario = $record;

        $rows = self::normalizeRows($data['comparativo_items'] ?? []);

        $proveedorA = trim((string) ($data['proveedor_a_nombre'] ?? ''));
        $proveedorB = trim((string) ($data['proveedor_b_nombre'] ?? ''));
        $proveedorC = trim((string) ($data['proveedor_c_nombre'] ?? ''));

        unset(
            $data['selected_item_ids'],
            $data['comparativo_items'],
            $data['proveedor_a_nombre'],
            $data['proveedor_b_nombre'],
            $data['proveedor_c_nombre']
        );

        return DB::transaction(function () use ($sumario, $data, $rows, $proveedorA, $proveedorB, $proveedorC): Sumario {
            $previousItemIds = $sumario->items()->pluck('solicitud_compra_item_id')->map(fn ($id) => (int) $id)->all();

            $sumario->update($data);

            $sumario->items()->delete();

            $newItemIds = [];

            foreach ($rows as $row) {
                $sumarioItem = SumarioItem::query()->create([
                    'sumario_id' => $sumario->id,
                    'solicitud_compra_item_id' => $row['solicitud_compra_item_id'],
                    'item' => $row['item'] ?? null,
                    'descripcion' => $row['descripcion'] ?? '',
                    'unidad_medida' => $row['unidad_medida'] ?? 'UND',
                    'cantidad' => (float) ($row['cantidad'] ?? 0),
                ]);

                $itemId = (int) $row['solicitud_compra_item_id'];
                $newItemIds[] = $itemId;

                $selectedColumn = strtoupper(trim((string) ($row['proveedor_seleccionado'] ?? '')));

                $this->createOption($sumarioItem, 1, $proveedorA, $row['marca_prov1'] ?? null, (float) ($row['precio_unitario_prov1'] ?? 0), (float) ($row['precio_total_prov1'] ?? 0), $selectedColumn === 'A');
                $this->createOption($sumarioItem, 2, $proveedorB, $row['marca_prov2'] ?? null, (float) ($row['precio_unitario_prov2'] ?? 0), (float) ($row['precio_total_prov2'] ?? 0), $selectedColumn === 'B');
                $this->createOption($sumarioItem, 3, $proveedorC, $row['marca_prov3'] ?? null, (float) ($row['precio_unitario_prov3'] ?? 0), (float) ($row['precio_total_prov3'] ?? 0), $selectedColumn === 'C');
            }

            $affectedItemIds = collect(array_merge($previousItemIds, $newItemIds))
                ->unique()
                ->values()
                ->all();

            foreach ($affectedItemIds as $itemId) {
                SolicitudItemTrackingService::syncByItemIds([(int) $itemId]);
            }

            if (filled($sumario->solicitud_compra_id) && (string) $sumario->workflow_estado !== 'BORRADOR') {
                SolicitudCompra::query()
                    ->whereKey($sumario->solicitud_compra_id)
                    ->update(['estado' => SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA]);
            }

            return $sumario->fresh();
        });
    }

    private function createOption(SumarioItem $sumarioItem, int $numero, string $proveedorNombre, ?string $marca, float $precioUnitario, float $precioTotal, bool $selected): void
    {
        SumarioItemOpcion::query()->create([
            'sumario_item_id' => $sumarioItem->id,
            'opcion_numero' => $numero,
            'proveedor_id' => $this->resolveProveedorIdByName($proveedorNombre),
            'proveedor_nombre' => $proveedorNombre,
            'marca' => $marca,
            'precio_unitario' => round($precioUnitario, 2),
            'precio_total' => round($precioTotal, 2),
            'seleccionada' => $selected,
        ]);
    }

    private function resolveSelectedColumn(array $options): string
    {
        foreach ([1 => 'A', 2 => 'B', 3 => 'C'] as $number => $column) {
            $option = $options[$number] ?? null;
            if ((bool) ($option?->seleccionada ?? false)) {
                return $column;
            }
        }

        return '';
    }

    private function syncSolicitudItemStatus(int $solicitudCompraItemId): void
    {
        SolicitudItemTrackingService::syncByItemIds([$solicitudCompraItemId]);
    }

    private function resolveProveedorIdByName(string $nombre): ?int
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return null;
        }

        return Proveedor::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->value('id');
    }

    private function prepareDraftData(array $data, Sumario $record): array
    {
        $rows = self::normalizeRows($data['comparativo_items'] ?? []);

        $data['solicitud_compra_id'] = $data['solicitud_compra_id'] ?? $record->solicitud_compra_id;
        $data['correlativo_sdc'] = filled($data['correlativo_sdc'] ?? null)
            ? trim((string) $data['correlativo_sdc'])
            : ((string) ($record->correlativo_sdc ?: $this->generateDraftCorrelativo()));
        $data['fecha'] = $data['fecha'] ?? optional($record->fecha)->toDateString() ?? now()->toDateString();
        $data['procedencia'] = $data['procedencia'] ?? $record->procedencia ?? 'LOCAL';
        $data['tipo_orden'] = $data['tipo_orden'] ?? $record->tipo_orden ?? 'COMPRA';
        $data['departamento_solicitante'] = filled($data['departamento_solicitante'] ?? null)
            ? trim((string) $data['departamento_solicitante'])
            : ($record->departamento_solicitante ?: $this->resolveDepartamentoSolicitante($data['solicitud_compra_id'] ?? null));
        $data['estado'] = 'BORRADOR';
        $data['workflow_estado'] = 'BORRADOR';
        $data['elaborado_por_user_id'] = $data['elaborado_por_user_id'] ?? $record->elaborado_por_user_id ?? auth()->id();
        $data['comparativo_items'] = $rows;

        return $data;
    }

    private function isSubmittableWorkflow(string $workflow): bool
    {
        return $workflow === 'BORRADOR' || $this->isRejectedWorkflow($workflow);
    }

    private function isRejectedWorkflow(string $workflow): bool
    {
        return in_array($workflow, [
            'RECHAZADO_VALIDACION_FINANZAS',
            'RECHAZADO_GERENCIA_FINANZAS',
        ], true);
    }

    private function isGerenciaRejectedWorkflow(string $workflow): bool
    {
        return in_array($workflow, [
            'RECHAZADO_GERENCIA_FINANZAS',
        ], true);
    }

    private function renderGerenciaCorrectionsPreview(): string
    {
        /** @var Sumario $sumario */
        $sumario = $this->record->loadMissing(['items.opciones']);

        $items = $sumario->items
            ->sortBy(function (SumarioItem $item): int {
                return (int) ($item->item ?: $item->id);
            })
            ->values();

        if ($items->isEmpty()) {
            return '<div style="padding:12px;color:#6b7280;">No hay items para mostrar en correcciones de Gerencia.</div>';
        }

        $rows = $items->map(function (SumarioItem $item): string {
            $isRejected = (string) ($item->validacion_gerencia_resultado ?? '') === 'RECHAZADO';
            $decisionText = $isRejected ? '❌ Incorrecto' : '✅ Correcto';
            $decisionBg = $isRejected ? '#fef2f2' : '#f0fdf4';
            $decisionBorder = $isRejected ? '#fecaca' : '#bbf7d0';

            return '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-bottom:10px;">'
                . '<div style="display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:12px;align-items:start;">'
                . '<div>'
                . '<div style="font-weight:700;margin-bottom:8px;">Cuadro comparativo del item</div>'
                . $this->renderGerenciaCorrectionItemTable($item)
                . '</div>'
                . '<div>'
                . '<div style="font-weight:700;margin-bottom:6px;">Decision Gerencia</div>'
                . '<div style="padding:10px;border:1px solid ' . $decisionBorder . ';background:' . $decisionBg . ';border-radius:8px;">' . e($decisionText) . '</div>'
                . '</div>'
                . '</div>'
                . '</div>';
        })->implode('');

        $comment = trim((string) ($sumario->decision_gerencia_comentario ?? ''));

        return $rows
            . '<div style="margin-top:10px;border:1px solid #d1d5db;border-radius:10px;overflow:hidden;">'
            . '<div style="padding:10px 12px;background:#eef2ff;font-weight:700;">Comentario general de Gerencia</div>'
            . '<div style="padding:12px;white-space:pre-wrap;">' . nl2br(e($comment !== '' ? $comment : 'Sin comentario general registrado.')) . '</div>'
            . '</div>';
    }

    private function renderGerenciaCorrectionItemTable(SumarioItem $item): string
    {
        $opciones = $item->opciones->keyBy('opcion_numero');
        $selectedOption = $item->opciones->firstWhere('seleccionada', true);
        $selectedOptionNumber = (int) ($selectedOption?->opcion_numero ?? 0);

        $renderOption = function (int $optionNumber) use ($opciones, $selectedOptionNumber): string {
            $option = $opciones->get($optionNumber);
            $isSelected = $selectedOptionNumber === $optionNumber;
            $cellStyle = $isSelected
                ? 'border:1px solid #86efac;padding:4px;background:#dcfce7;font-size:10px;line-height:1.2;'
                : 'border:1px solid #d1d5db;padding:4px;font-size:10px;line-height:1.2;';

            return '<td style="' . $cellStyle . '">' . e((string) ($option?->proveedor_nombre ?? '-')) . '</td>'
                . '<td style="' . $cellStyle . '">' . e((string) ($option?->marca ?? '-')) . '</td>'
                . '<td style="' . $cellStyle . 'text-align:right;">' . number_format((float) ($option?->precio_unitario ?? 0), 2, ',', '.') . '</td>'
                . '<td style="' . $cellStyle . 'text-align:right;">' . number_format((float) ($option?->precio_total ?? 0), 2, ',', '.') . '</td>';
        };

        return '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:10px;table-layout:auto;">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Item</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Descripcion</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">UND</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Cant</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Prov 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Marca 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/U 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/T 1</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Prov 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Marca 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/U 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/T 2</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Prov 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">Marca 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/U 3</th>'
            . '<th style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">P/T 3</th>'
            . '</tr></thead>'
            . '<tbody><tr>'
            . '<td style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">' . e((string) ($item->item ?: $item->id)) . '</td>'
            . '<td style="border:1px solid #d1d5db;padding:3px;white-space:nowrap;">' . e((string) $item->descripcion) . '</td>'
            . '<td style="border:1px solid #d1d5db;padding:3px;text-align:center;white-space:nowrap;">' . e((string) ($item->unidad_medida ?? 'UND')) . '</td>'
            . '<td style="border:1px solid #d1d5db;padding:3px;text-align:right;white-space:nowrap;">' . number_format((float) $item->cantidad, 2, ',', '.') . '</td>'
            . $renderOption(1)
            . $renderOption(2)
            . $renderOption(3)
            . '</tr></tbody></table>'
            . '</div>';
    }

    private function resolveDepartamentoSolicitante(mixed $solicitudCompraId): string
    {
        if (filled($solicitudCompraId)) {
            $departamento = SolicitudCompra::query()
                ->whereKey($solicitudCompraId)
                ->value('departamento_solicitante');

            if (filled($departamento)) {
                return (string) $departamento;
            }
        }

        return 'PENDIENTE';
    }

    private function generateDraftCorrelativo(): string
    {
        do {
            $candidate = 'BOR-' . now()->format('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        } while (Sumario::query()->where('correlativo_sdc', $candidate)->exists());

        return $candidate;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row): bool => is_array($row) && filled($row['solicitud_compra_item_id'] ?? null))
            ->map(function (array $row): array {
                $cantidad = (float) ($row['cantidad'] ?? 0);

                $precioUnitario1 = (float) ($row['precio_unitario_prov1'] ?? 0);
                $precioUnitario2 = (float) ($row['precio_unitario_prov2'] ?? 0);
                $precioUnitario3 = (float) ($row['precio_unitario_prov3'] ?? 0);

                return [
                    ...$row,
                    'cantidad' => $cantidad,
                    'precio_unitario_prov1' => $precioUnitario1,
                    'precio_total_prov1' => round($cantidad * $precioUnitario1, 2),
                    'precio_unitario_prov2' => $precioUnitario2,
                    'precio_total_prov2' => round($cantidad * $precioUnitario2, 2),
                    'precio_unitario_prov3' => $precioUnitario3,
                    'precio_total_prov3' => round($cantidad * $precioUnitario3, 2),
                    'proveedor_seleccionado' => strtoupper((string) ($row['proveedor_seleccionado'] ?? 'A')),
                ];
            })
            ->values()
            ->all();
    }
}
