<?php

namespace App\Filament\Resources\Sumarios\Pages;

use App\Filament\Resources\Sumarios\SumarioResource;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Models\Sumario;
use App\Models\SumarioItem;
use App\Models\SumarioItemOpcion;
use App\Support\SolicitudItemTrackingService;
use App\Support\ControlCodeGenerator;
use App\Support\SumarioProviderGrouping;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateSumario extends CreateRecord
{
    protected static string $resource = SumarioResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    private bool $isSubmittingForValidation = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            \Filament\Actions\Action::make('saveDraft')
                ->label('Guardar como borrador')
                ->color('warning')
                ->action(function () {
                    $data = $this->prepareDraftData($this->form->getRawState());

                    if (blank($data['solicitud_compra_id'] ?? null)) {
                        Notification::make()
                            ->title('No se pudo guardar borrador')
                            ->body('Selecciona una solicitud base para guardar este borrador en la lista de borradores.')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $record = $this->handleRecordCreation($this->mutateFormDataBeforeCreate($data));

                        $this->record = $record;

                        Notification::make()
                            ->title('Borrador de sumario guardado')
                            ->body('Tu sumario ha sido guardado exitosamente como BORRADOR en la lista.')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('index'));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('No se pudo guardar borrador')
                            ->body('Revisa que el correlativo no este repetido y vuelve a intentar.')
                            ->danger()
                            ->send();
                    }
                }),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreateFormAction(): Action
    {
        return Action::make('create')
            ->label('Enviar')
            ->color('primary')
            ->keyBindings(['mod+s'])
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
                if (! auth()->user()?->can('SubmitValidation:Sumario')) {
                    Notification::make()
                        ->title('Sin permisos')
                        ->body('No tienes permisos para enviar el sumario a validacion.')
                        ->danger()
                        ->send();

                    return;
                }

                if (! $this->validateSignaturePassword($data)) {
                    return;
                }

                $this->isSubmittingForValidation = true;

                try {
                    $this->create();
                } finally {
                    $this->isSubmittingForValidation = false;
                }
            });
    }

    protected function getRedirectUrl(): string
    {
        if ($this->isSubmittingForValidation) {
            return SumarioResource::getUrl('index');
        }

        return parent::getRedirectUrl();
    }

    protected Width | string | null $maxWidth = Width::Full;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $rows = self::normalizeRows($data['comparativo_items'] ?? []);
        $totals = SumarioProviderGrouping::groupedTotalsFromRows([
            1 => (string) ($data['proveedor_a_nombre'] ?? ''),
            2 => (string) ($data['proveedor_b_nombre'] ?? ''),
            3 => (string) ($data['proveedor_c_nombre'] ?? ''),
        ], $rows);

        $data['correlativo_sdc'] = filled($data['correlativo_sdc'] ?? null)
            ? trim((string) $data['correlativo_sdc'])
            : ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc');

        $data['total_compra_prov1'] = $totals[1];
        $data['total_compra_prov2'] = $totals[2];
        $data['total_compra_prov3'] = $totals[3];

        if ($this->isSubmittingForValidation) {
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
        } else {
            $data['estado'] = 'BORRADOR';
            $data['workflow_estado'] = 'BORRADOR';
        }

        $data['elaborado_por_user_id'] = auth()->id();
        $data['proveedor_ganador_id'] = null;

        if (blank($data['departamento_solicitante'] ?? null) && filled($data['solicitud_compra_id'] ?? null)) {
            $data['departamento_solicitante'] = SolicitudCompra::query()
                ->whereKey($data['solicitud_compra_id'])
                ->value('departamento_solicitante');
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
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

        return DB::transaction(function () use ($data, $rows, $proveedorA, $proveedorB, $proveedorC): Sumario {
            /** @var Sumario $sumario */
            $sumario = Sumario::query()->create($data);

            $itemIds = [];

            foreach ($rows as $row) {
                $sumarioItem = SumarioItem::query()->create([
                    'sumario_id' => $sumario->id,
                    'solicitud_compra_item_id' => $row['solicitud_compra_item_id'],
                    'item' => $row['item'] ?? null,
                    'descripcion' => $row['descripcion'] ?? '',
                    'unidad_medida' => $row['unidad_medida'] ?? 'UND',
                    'cantidad' => (float) ($row['cantidad'] ?? 0),
                ]);

                $itemIds[] = (int) $row['solicitud_compra_item_id'];

                $selectedColumn = strtoupper(trim((string) ($row['proveedor_seleccionado'] ?? '')));

                $this->createOption($sumarioItem, 1, $proveedorA, $row['marca_prov1'] ?? null, (float) ($row['precio_unitario_prov1'] ?? 0), (float) ($row['precio_total_prov1'] ?? 0), $selectedColumn === 'A');
                $this->createOption($sumarioItem, 2, $proveedorB, $row['marca_prov2'] ?? null, (float) ($row['precio_unitario_prov2'] ?? 0), (float) ($row['precio_total_prov2'] ?? 0), $selectedColumn === 'B');
                $this->createOption($sumarioItem, 3, $proveedorC, $row['marca_prov3'] ?? null, (float) ($row['precio_unitario_prov3'] ?? 0), (float) ($row['precio_total_prov3'] ?? 0), $selectedColumn === 'C');
            }

            if ($itemIds !== []) {
                SolicitudItemTrackingService::syncByItemIds($itemIds);
            }

            if (filled($sumario->solicitud_compra_id) && (string) $sumario->workflow_estado !== 'BORRADOR') {
                SolicitudCompra::query()
                    ->whereKey($sumario->solicitud_compra_id)
                    ->update(['estado' => SolicitudCompra::ESTADO_RECIBIDO_POR_PROCURA]);
            }

            return $sumario;
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

    private function prepareDraftData(array $data): array
    {
        $rows = self::normalizeRows($data['comparativo_items'] ?? []);

        $data['solicitud_compra_id'] = $data['solicitud_compra_id']
            ?? $this->resolveSolicitudCompraIdFromRows($rows);
        $data['correlativo_sdc'] = filled($data['correlativo_sdc'] ?? null)
            ? trim((string) $data['correlativo_sdc'])
            : $this->generateDraftCorrelativo();
        $data['fecha'] = $data['fecha'] ?? now()->toDateString();
        $data['procedencia'] = $data['procedencia'] ?? 'LOCAL';
        $data['tipo_orden'] = $data['tipo_orden'] ?? 'COMPRA';
        $data['departamento_solicitante'] = filled($data['departamento_solicitante'] ?? null)
            ? trim((string) $data['departamento_solicitante'])
            : $this->resolveDepartamentoSolicitante($data['solicitud_compra_id'] ?? null);
        $data['estado'] = 'BORRADOR';
        $data['workflow_estado'] = 'BORRADOR';
        $data['elaborado_por_user_id'] = $data['elaborado_por_user_id'] ?? auth()->id();

        return $data;
    }

    private function resolveSolicitudCompraIdFromRows(array $rows): ?int
    {
        $firstItemId = collect($rows)
            ->pluck('solicitud_compra_item_id')
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->first();

        if (! $firstItemId) {
            return null;
        }

        $solicitudId = SolicitudCompraItem::query()
            ->whereKey($firstItemId)
            ->value('solicitud_compra_id');

        return $solicitudId ? (int) $solicitudId : null;
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
        return ControlCodeGenerator::generate('SUM', Sumario::class, 'correlativo_sdc');
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
