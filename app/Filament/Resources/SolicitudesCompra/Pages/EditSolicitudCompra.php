<?php

namespace App\Filament\Resources\SolicitudesCompra\Pages;

use App\Filament\Resources\SolicitudesCompra\SolicitudCompraResource;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraItem;
use App\Support\SolicitudCompraFlow;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class EditSolicitudCompra extends EditRecord
{
    protected static string $resource = SolicitudCompraResource::class;

    protected ?SolicitudCompra $redirectToRecord = null;

    private ?array $revisionSubmissionData = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! SolicitudCompraFlow::canEditRequest(auth()->user(), $this->record)) {
            throw new AuthorizationException('No puedes editar esta solicitud.');
        }
    }

    protected function getSaveFormAction(): Action
    {
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
}
