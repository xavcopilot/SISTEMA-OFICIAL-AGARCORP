<?php

namespace App\Filament\Resources\SolicitudesCompra\Pages;

use App\Filament\Resources\SolicitudesCompra\SolicitudCompraResource;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Support\ActivityNotification;
use App\Support\SolicitudCompraFlow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class EditSolicitudCompra extends EditRecord
{
    protected static string $resource = SolicitudCompraResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected ?SolicitudCompra $redirectToRecord = null;

    private ?array $revisionSubmissionData = null;

    private bool $isSubmittingDraft = false;

    private bool $draftValidatedAndSaved = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! SolicitudCompraFlow::canEditRequest(auth()->user(), $this->record)) {
            throw new AuthorizationException('No puedes editar esta solicitud.');
        }
    }

    protected function getSaveFormAction(): Action
    {
        if ($this->record instanceof SolicitudCompra && SolicitudCompraFlow::canManageDraft(auth()->user(), $this->record)) {
            return Action::make('saveDraft')
                ->label('Guardar borrador')
                ->color('primary')
                ->keyBindings(['mod+s'])
                ->action(function (): void {
                    $this->saveDraft();
                });
        }

        return Action::make('save')
            ->label('Guardar')
            ->color('primary')
            ->keyBindings(['mod+s'])
            ->schema($this->getRevisionValidationSchema())
            ->modalHeading('Guardar correccion')
            ->modalSubmitActionLabel('Guardar')
            ->action(function (array $data): void {
                if (! $this->validateRejectedRevisionSubmission($data)) {
                    $this->halt();

                    return;
                }

                $this->revisionSubmissionData = $data;

                try {
                    $this->save();
                } finally {
                    $this->revisionSubmissionData = null;
                }
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitDraft')
                ->label('Terminar y enviar')
                ->color('success')
                ->visible(fn (): bool => $this->record instanceof SolicitudCompra && SolicitudCompraFlow::canManageDraft(auth()->user(), $this->record))
                ->schema($this->getDraftSubmissionSchema())
                ->action(function (array $data): void {
                    $this->submitDraft($data);
                }),
            DeleteAction::make()
                ->visible(fn (): bool => $this->record instanceof SolicitudCompra && SolicitudCompraFlow::canDeleteRequest(auth()->user(), $this->record)),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record instanceof SolicitudCompra && (string) $this->record->estado === 'RECHAZADA') {
            if (! $this->validateRejectedRevisionSubmission($this->revisionSubmissionData ?? [])) {
                $this->halt();
            }
        }

        $data['cargo_solicitante'] = $data['cargo_solicitante'] ?? $this->record->cargo_solicitante ?? auth()->user()?->cargo?->nombre;
        $data['por_almacen_user_id'] = $data['por_almacen_user_id'] ?? $this->record->por_almacen_user_id ?? SolicitudCompraFlow::defaultAlmacenUserId();
        $data['cargo_almacen'] = SolicitudCompraFlow::cargoForUserId($data['por_almacen_user_id'] ?? null);
        $data['cargo_aprobador'] = SolicitudCompraFlow::cargoForUserId($data['aprobado_por_user_id'] ?? $this->record->aprobado_por_user_id);
        $data['recibido_por_user_id'] = $data['recibido_por_user_id'] ?? $this->record->recibido_por_user_id ?? SolicitudCompraFlow::defaultProcuraUserId();
        $data['cargo_receptor'] = SolicitudCompraFlow::cargoForUserId($data['recibido_por_user_id'] ?? null);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record instanceof SolicitudCompra && (string) $record->estado === 'RECHAZADA') {
            $newRecord = $this->createRevisionFromRejectedRecord($record, $data);
            $this->record = $newRecord;
            $this->redirectToRecord = $newRecord;

            return $newRecord;
        }

        return parent::handleRecordUpdate($record, $data);
    }

    protected function getRedirectUrl(): ?string
    {
        if ($this->redirectToRecord) {
            return static::getResource()::getUrl('view', ['record' => $this->redirectToRecord]);
        }

        return parent::getRedirectUrl();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        if ($this->redirectToRecord) {
            return 'Se creo una nueva version de la solicitud';
        }

        return parent::getSavedNotificationTitle();
    }

    protected function afterSave(): void
    {
        if ($this->isSubmittingDraft) {
            $this->draftValidatedAndSaved = true;
        }
    }

    private function getRevisionValidationSchema(): array
    {
        return [
            TextInput::make('revision_password')
                ->label('Clave de firma')
                ->password()
                ->required(),

            TextInput::make('revision_password_confirmation')
                ->label('Repetir clave de firma')
                ->password()
                ->required(),
        ];
    }

    private function getDraftSubmissionSchema(): array
    {
        return [
            TextInput::make('password')
                ->label('Clave de firma')
                ->password()
                ->required(),

            TextInput::make('password_confirmation')
                ->label('Repetir clave de firma')
                ->password()
                ->required(),
        ];
    }


    private function createRevisionFromRejectedRecord(SolicitudCompra $record, array $data): SolicitudCompra
    {
        $sharedCode = (string) ($record->codigo_control ?: $record->id);

        if (blank($record->codigo_control)) {
            $record->forceFill([
                'codigo_control' => $sharedCode,
            ])->save();
        }

        $items = $this->normalizeItemsForRevision($record, $data['items'] ?? []);
        unset($data['items']);

        $newRecord = SolicitudCompra::query()->create([
            ...$data,
            'codigo_control' => $sharedCode,
            'codigo_control_procura' => $record->codigo_control_procura,
            'estado' => 'EN_ESPERA_DE_COTIZACION',
            // La correccion de una solicitud rechazada se considera reenviada.
            'firma_solicitante' => '__ENVIADA__',
            'firma_almacen' => null,
            'firma_aprobador' => null,
            'firma_receptor' => null,
            'fecha_solicitante' => now()->toDateString(),
            'fecha_almacen' => null,
            'fecha_aprobador' => null,
            'fecha_receptor' => null,
            'hora_receptor' => null,
            'rechazo_etapa' => null,
            'rechazo_comentario' => null,
            'rechazo_por_user_id' => null,
            'rechazo_destinatario_user_id' => null,
            'rechazo_en' => null,
        ]);

        collect($items)
            ->values()
            ->each(function (array $item, int $index) use ($newRecord): void {
                SolicitudCompraItem::query()->create([
                    'solicitud_compra_id' => $newRecord->id,
                    'item' => $index + 1,
                    'descripcion' => $item['descripcion'] ?? null,
                    'unidad_medida' => $item['unidad_medida'] ?? null,
                    'cantidad_solicitada' => $item['cantidad_solicitada'] ?? 0,
                    'cantidad_existencia' => $item['cantidad_existencia'] ?? 0,
                    'cantidad_a_comprar' => $item['cantidad_a_comprar'] ?? 0,
                ]);
            });

        return $newRecord;
    }

    private function normalizeItemsForRevision(SolicitudCompra $record, array $items): array
    {
        $normalized = collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item): array => [
                'descripcion' => $item['descripcion'] ?? null,
                'unidad_medida' => $item['unidad_medida'] ?? null,
                'cantidad_solicitada' => $item['cantidad_solicitada'] ?? 0,
                'cantidad_existencia' => $item['cantidad_existencia'] ?? 0,
                'cantidad_a_comprar' => $item['cantidad_a_comprar'] ?? 0,
            ])
            ->filter(fn (array $item): bool => filled($item['descripcion']) || (float) $item['cantidad_solicitada'] > 0)
            ->values()
            ->all();

        if ($normalized !== []) {
            return $normalized;
        }

        return $record->items()
            ->orderBy('item')
            ->get(['descripcion', 'unidad_medida', 'cantidad_solicitada', 'cantidad_existencia', 'cantidad_a_comprar'])
            ->map(fn ($item): array => [
                'descripcion' => $item->descripcion,
                'unidad_medida' => $item->unidad_medida,
                'cantidad_solicitada' => $item->cantidad_solicitada,
                'cantidad_existencia' => $item->cantidad_existencia,
                'cantidad_a_comprar' => $item->cantidad_a_comprar,
            ])
            ->all();
    }

    private function validateRejectedRevisionSubmission(array $data): bool
    {
        $password = (string) ($data['revision_password'] ?? '');
        $passwordConfirmation = (string) ($data['revision_password_confirmation'] ?? '');

        if ($password === '' || $password !== $passwordConfirmation) {
            Notification::make()
                ->title('Verificacion fallida')
                ->body('Debes escribir la misma clave de firma dos veces antes de guardar la correccion.')
                ->danger()
                ->send();

            return false;
        }

        $signatureHash = auth()->user()?->firma_password ?: auth()->user()?->password ?: '';

        if (Hash::check($password, $signatureHash)) {
            return true;
        }

        Notification::make()
            ->title('Clave incorrecta')
            ->body('La correccion no se guardo porque la clave de firma no coincide.')
            ->danger()
            ->send();

        return false;
    }

    private function validateSignatureSubmission(array $data): bool
    {
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

        if ($password === '' || $password !== $passwordConfirmation) {
            Notification::make()
                ->title('Verificacion fallida')
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
            ->title('Clave incorrecta')
            ->body('La firma no se registro porque la clave de firma no coincide.')
            ->danger()
            ->send();

        return false;
    }

    private function saveDraft(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof SolicitudCompra || ! SolicitudCompraFlow::canManageDraft(auth()->user(), $record)) {
            return;
        }

        $rawState = $this->form->getRawState();
        $data = $this->prepareDraftData($rawState);

        $record->fill(Arr::only($data, $record->getFillable()));
        $record->save();

        $this->syncDraftItems($record, $rawState['items'] ?? []);
        $this->record = $record->fresh();
        $this->fillForm();

        Notification::make()
            ->title('Borrador guardado')
            ->body('Borrador guardado exitosamente.')
            ->success()
            ->send();
    }

    private function syncDraftItems(SolicitudCompra $record, mixed $rawItems): void
    {
        if (! is_array($rawItems)) {
            return;
        }

        $rows = collect($rawItems)
            ->filter(fn ($row): bool => is_array($row))
            ->values()
            ->map(function (array $row, int $index): array {
                $cantidadSolicitada = $this->toNullableDecimal($row['cantidad_solicitada'] ?? null);
                $cantidadExistencia = $this->toNullableDecimal($row['cantidad_existencia'] ?? null);

                return [
                    'item' => $index + 1,
                    'descripcion' => filled($row['descripcion'] ?? null) ? trim((string) $row['descripcion']) : null,
                    'unidad_medida' => filled($row['unidad_medida'] ?? null) ? trim((string) $row['unidad_medida']) : null,
                    'cantidad_solicitada' => $cantidadSolicitada,
                    'cantidad_existencia' => $cantidadExistencia,
                    'cantidad_a_comprar' => $this->calculateDraftCantidadAComprar($cantidadSolicitada, $cantidadExistencia),
                ];
            })
            ->all();

        $record->items()->delete();

        if ($rows === []) {
            return;
        }

        $record->items()->createMany($rows);
    }

    private function toNullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function calculateDraftCantidadAComprar(?float $cantidadSolicitada, ?float $cantidadExistencia): ?float
    {
        if ($cantidadSolicitada === null && $cantidadExistencia === null) {
            return null;
        }

        return max(($cantidadSolicitada ?? 0) - ($cantidadExistencia ?? 0), 0);
    }

    private function prepareDraftData(array $data): array
    {
        $record = $this->getRecord();

        $data['codigo_control'] = $record->codigo_control ?: (string) $record->id;
        $data['estado'] = 'BORRADOR';
        $data['solicitado_por_user_id'] = $data['solicitado_por_user_id'] ?? $record->solicitado_por_user_id ?? auth()->id();
        $data['cargo_solicitante'] = $data['cargo_solicitante'] ?? $record->cargo_solicitante ?? auth()->user()?->cargo?->nombre;

        $porAlmacenUserId = $data['por_almacen_user_id'] ?? $record->por_almacen_user_id;
        $aprobadoPorUserId = $data['aprobado_por_user_id'] ?? $record->aprobado_por_user_id;
        $recibidoPorUserId = $data['recibido_por_user_id'] ?? $record->recibido_por_user_id;

        $data['cargo_almacen'] = SolicitudCompraFlow::cargoForUserId($porAlmacenUserId);
        $data['cargo_aprobador'] = SolicitudCompraFlow::cargoForUserId($aprobadoPorUserId);
        $data['cargo_receptor'] = SolicitudCompraFlow::cargoForUserId($recibidoPorUserId);

        return $data;
    }

    private function submitDraft(array $data): void
    {
        $user = auth()->user();

        if (! $this->getRecord() instanceof SolicitudCompra || ! $user) {
            return;
        }

        if (! SolicitudCompraFlow::canManageDraft($user, $this->getRecord()) || ! $this->validateSignatureSubmission($data)) {
            return;
        }

        $this->isSubmittingDraft = true;
        $this->draftValidatedAndSaved = false;

        try {
            $this->save(false, false);
        } finally {
            $this->isSubmittingDraft = false;
        }

        if (! $this->draftValidatedAndSaved) {
            return;
        }

        $record = $this->getRecord()->fresh();

        if (! $record instanceof SolicitudCompra || ! SolicitudCompraFlow::canManageDraft($user, $record)) {
            return;
        }

        $submittedRecord = SolicitudCompraFlow::submitDraft($record, $user);
        $this->record = $submittedRecord;

        Notification::make()
            ->title('Solicitud enviada')
            ->body('La solicitud fue enviada al flujo de revision.')
            ->success()
            ->send();

        ActivityNotification::record(
            $user,
            'Solicitud enviada',
            'Se envio la solicitud #' . (string) $submittedRecord->id . ' al flujo de revision.',
            'success'
        );

        $this->redirect(static::getResource()::getUrl('view', ['record' => $submittedRecord]));
    }
}
