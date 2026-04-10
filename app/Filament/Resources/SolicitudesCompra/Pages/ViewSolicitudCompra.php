<?php

namespace App\Filament\Resources\SolicitudesCompra\Pages;

use App\Filament\Resources\SolicitudesCompra\SolicitudCompraResource;
use App\Models\SolicitudCompra;
use App\Models\User;
use App\Support\ActivityNotification;
use App\Support\SolicitudCompraFlow;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Hash;

class ViewSolicitudCompra extends ViewRecord
{
    protected static string $resource = SolicitudCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('firmarSolicitud')
                ->label('Enviar solicitud')
                ->color('primary')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignRequester(auth()->user(), $this->getRecord()))
                ->schema($this->getSignatureSchema())
                ->action(function (array $data): void {
                    $this->signRequester($data);
                }),

            Action::make('firmarAlmacen')
                ->label('Firmar almacén')
                ->color('warning')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $this->getRecord()))
                ->schema($this->getSignatureSchema())
                ->action(function (array $data): void {
                    $this->signAlmacen($data);
                }),

            Action::make('rechazarAlmacen')
                ->label('Rechazar (almacén)')
                ->color('danger')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignAlmacen(auth()->user(), $this->getRecord()))
                ->schema($this->getRejectionSchema())
                ->action(function (array $data): void {
                    $this->rejectFromAlmacen($data);
                }),

            Action::make('firmarAprobacion')
                ->label('Firmar aprobación')
                ->color('success')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignApprover(auth()->user(), $this->getRecord()))
                ->schema($this->getSignatureSchema())
                ->action(function (array $data): void {
                    $this->signApprover($data);
                }),

            Action::make('rechazarAprobacion')
                ->label('Rechazar (aprobación)')
                ->color('danger')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignApprover(auth()->user(), $this->getRecord()))
                ->schema($this->getRejectionSchema())
                ->action(function (array $data): void {
                    $this->rejectFromApprover($data);
                }),

            Action::make('firmarRecepcion')
                ->label('Firmar recepción procura')
                ->color('info')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignProcura(auth()->user(), $this->getRecord()))
                ->schema($this->getSignatureSchema())
                ->action(function (array $data): void {
                    $this->signProcura($data);
                }),

            Action::make('rechazarRecepcion')
                ->label('Rechazar (procura)')
                ->color('danger')
                ->visible(fn (): bool => SolicitudCompraFlow::canSignProcura(auth()->user(), $this->getRecord()))
                ->schema($this->getRejectionSchema())
                ->action(function (array $data): void {
                    $this->rejectFromProcura($data);
                }),
        ];
    }

    private function getSignatureSchema(): array
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

    private function getRejectionSchema(): array
    {
        return [
            Textarea::make('comentario_rechazo')
                ->label('Comentario de rechazo')
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
        ];
    }

    private function signRequester(array $data): void
    {
        $record = $this->getRecord()->fresh();

        if (! SolicitudCompraFlow::canSignRequester(auth()->user(), $record) || ! $this->validatePassword($data)) {
            return;
        }

        $latestRejectedVersion = $this->getLatestRejectedVersion($record);
        $wasRejected = $latestRejectedVersion !== null;
        $previousRejection = [
            'etapa' => $latestRejectedVersion?->rechazo_etapa,
            'comentario' => $latestRejectedVersion?->rechazo_comentario,
            'rechazado_por_user_id' => $latestRejectedVersion?->rechazo_por_user_id,
        ];

        $record->forceFill([
            'solicitado_por_user_id' => $record->solicitado_por_user_id ?: auth()->id(),
            'por_almacen_user_id' => $record->por_almacen_user_id ?: SolicitudCompraFlow::defaultAlmacenUserId(),
            'cargo_solicitante' => auth()->user()?->cargo?->nombre,
            'cargo_almacen' => SolicitudCompraFlow::cargoForUserId($record->por_almacen_user_id ?: SolicitudCompraFlow::defaultAlmacenUserId()),
            'firma_solicitante' => $record->firma_solicitante ?: '__ENVIADA__',
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
            'estado' => 'EN_ESPERA_DE_COTIZACION',
        ])->save();

        $this->syncSignedRecord();

        if ($wasRejected) {
            $this->notifyReprocessingUsers($record->fresh(), $previousRejection);
        }

        Notification::make()
            ->title('Solicitud enviada')
            ->body($wasRejected
                ? 'La solicitud corregida fue reenviada al flujo de revision.'
                : 'La solicitud fue enviada al usuario de almacen seleccionado.')
            ->success()
            ->send();

        ActivityNotification::record(
            auth()->user(),
            'Solicitud enviada',
            'Se envio la solicitud #' . (string) $record->id . ' al flujo de revision.',
            'success'
        );
    }

    private function getLatestRejectedVersion(SolicitudCompra $record): ?SolicitudCompra
    {
        if ($record->estado === 'RECHAZADA' && filled($record->rechazo_comentario)) {
            return $record;
        }

        $sharedCode = $record->codigo_control ?: null;

        if (blank($sharedCode)) {
            return null;
        }

        return SolicitudCompra::query()
            ->where('codigo_control', $sharedCode)
            ->where('estado', 'RECHAZADA')
            ->whereNotNull('rechazo_comentario')
            ->whereKeyNot($record->id)
            ->latest('rechazo_en')
            ->first();
    }

    private function signAlmacen(array $data): void
    {
        $record = $this->getRecord()->fresh();

        if (! SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record) || ! $this->validatePassword($data)) {
            return;
        }

        $record->forceFill([
            'por_almacen_user_id' => $record->por_almacen_user_id ?: auth()->id(),
            'cargo_almacen' => auth()->user()?->cargo?->nombre,
            'firma_almacen' => $record->firma_almacen,
            'fecha_almacen' => now()->toDateString(),
        ])->save();

        $this->syncSignedRecord();

        Notification::make()
            ->title('Firma de almacén registrada')
            ->success()
            ->send();

        ActivityNotification::record(
            auth()->user(),
            'Firma de almacen registrada',
            'Se firmo en etapa almacen la solicitud #' . (string) $record->id . '.',
            'success'
        );
    }

    private function signApprover(array $data): void
    {
        $record = $this->getRecord()->fresh();

        if (! SolicitudCompraFlow::canSignApprover(auth()->user(), $record) || ! $this->validatePassword($data)) {
            return;
        }

        $record->forceFill([
            'aprobado_por_user_id' => $record->aprobado_por_user_id ?: auth()->id(),
            'cargo_aprobador' => auth()->user()?->cargo?->nombre,
            'firma_aprobador' => $record->firma_aprobador,
            'fecha_aprobador' => now()->toDateString(),
        ])->save();

        $this->syncSignedRecord();

        Notification::make()
            ->title('Aprobación registrada')
            ->success()
            ->send();

        ActivityNotification::record(
            auth()->user(),
            'Aprobacion registrada',
            'Se firmo en etapa aprobacion la solicitud #' . (string) $record->id . '.',
            'success'
        );
    }

    private function signProcura(array $data): void
    {
        $record = $this->getRecord()->fresh();

        if (! SolicitudCompraFlow::canSignProcura(auth()->user(), $record) || ! $this->validatePassword($data)) {
            return;
        }

        $record->forceFill([
            'recibido_por_user_id' => $record->recibido_por_user_id ?: auth()->id(),
            'cargo_receptor' => auth()->user()?->cargo?->nombre,
            'firma_receptor' => $record->firma_receptor,
            'fecha_receptor' => now()->toDateString(),
            'hora_receptor' => now()->format('H:i:s'),
        ])->save();

        $this->syncSignedRecord();

        Notification::make()
            ->title('Recepción de procura registrada')
            ->success()
            ->send();

        ActivityNotification::record(
            auth()->user(),
            'Recepcion de procura registrada',
            'Se firmo en etapa procura la solicitud #' . (string) $record->id . '.',
            'success'
        );
    }

    private function validatePassword(array $data): bool
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

    private function rejectFromAlmacen(array $data): void
    {
        $record = $this->getRecord()->fresh();

        if (! SolicitudCompraFlow::canSignAlmacen(auth()->user(), $record) || ! $this->validatePassword($data)) {
            return;
        }

        $comentario = trim((string) ($data['comentario_rechazo'] ?? ''));
        if ($comentario === '') {
            return;
        }

        $record->forceFill([
        ])->save();

        $this->registerRejection($record, 'almacen', $comentario);
    }

    private function rejectFromApprover(array $data): void
    {
        $record = $this->getRecord()->fresh();

        if (! SolicitudCompraFlow::canSignApprover(auth()->user(), $record) || ! $this->validatePassword($data)) {
            return;
        }

        $comentario = trim((string) ($data['comentario_rechazo'] ?? ''));
        if ($comentario === '') {
            return;
        }

        $record->forceFill([
        ])->save();

        $this->registerRejection($record, 'aprobador', $comentario);
    }

    private function rejectFromProcura(array $data): void
    {
        $record = $this->getRecord()->fresh();

        if (! SolicitudCompraFlow::canSignProcura(auth()->user(), $record) || ! $this->validatePassword($data)) {
            return;
        }

        $comentario = trim((string) ($data['comentario_rechazo'] ?? ''));
        if ($comentario === '') {
            return;
        }

        $record->forceFill([
        ])->save();

        $this->registerRejection($record, 'procura', $comentario);
    }

    private function registerRejection(SolicitudCompra $record, string $etapa, string $comentario): void
    {
        $destinatarioUserId = $record->solicitado_por_user_id;

        $record->forceFill([
            'estado' => 'RECHAZADA',
            'rechazo_etapa' => $etapa,
            'rechazo_comentario' => $comentario,
            'rechazo_por_user_id' => auth()->id(),
            'rechazo_destinatario_user_id' => $destinatarioUserId,
            'rechazo_en' => now(),
        ])->save();

        $destinatario = $destinatarioUserId ? User::query()->find($destinatarioUserId) : null;

        if ($destinatario) {
            $rechazadoPor = auth()->user()?->name ?? 'Usuario';

            Notification::make()
                ->title('Solicitud rechazada en etapa ' . strtoupper($etapa))
                ->body('Solicitud #' . $record->id . ' rechazada por ' . $rechazadoPor . '. Motivo: ' . $comentario)
                ->danger()
                ->sendToDatabase($destinatario);
        }

        $this->syncSignedRecord();

        Notification::make()
            ->title('Rechazo registrado')
            ->body('Se notifico al solicitante con el comentario de rechazo.')
            ->success()
            ->send();

        ActivityNotification::record(
            auth()->user(),
            'Rechazo registrado',
            'Se rechazo la solicitud #' . (string) $record->id . ' en etapa ' . strtoupper($etapa) . '.',
            'warning'
        );
    }

    private function notifyReprocessingUsers(SolicitudCompra $record, array $previousRejection): void
    {
        $userIds = array_unique(array_filter([
            $record->por_almacen_user_id,
            $record->aprobado_por_user_id,
            $record->recibido_por_user_id,
        ]));

        if ($userIds === []) {
            return;
        }

        $rechazadoPor = filled($previousRejection['rechazado_por_user_id'] ?? null)
            ? User::query()->find($previousRejection['rechazado_por_user_id'])?->name
            : null;

        $detalle = 'Solicitud #' . $record->id . ' reenviada por el solicitante tras rechazo.';
        if (filled($previousRejection['etapa'] ?? null)) {
            $detalle .= ' Etapa previa: ' . strtoupper((string) $previousRejection['etapa']) . '.';
        }
        if (filled($rechazadoPor)) {
            $detalle .= ' Rechazada por: ' . $rechazadoPor . '.';
        }
        if (filled($previousRejection['comentario'] ?? null)) {
            $detalle .= ' Motivo: ' . $previousRejection['comentario'];
        }

        User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->each(function (User $user) use ($detalle): void {
                Notification::make()
                    ->title('Solicitud reenviada para nueva revision')
                    ->body($detalle)
                    ->warning()
                    ->sendToDatabase($user);
            });
    }

    private function syncSignedRecord(): void
    {
        $this->record = $this->getRecord()->fresh();

        $this->refreshFormData([
            'solicitado_por_user_id',
            'por_almacen_user_id',
            'aprobado_por_user_id',
            'recibido_por_user_id',
            'cargo_solicitante',
            'cargo_almacen',
            'cargo_aprobador',
            'cargo_receptor',
            'firma_solicitante',
            'firma_almacen',
            'firma_aprobador',
            'firma_receptor',
            'fecha_solicitante',
            'fecha_almacen',
            'fecha_aprobador',
            'fecha_receptor',
            'hora_receptor',
            'rechazo_etapa',
            'rechazo_comentario',
            'rechazo_por_user_id',
            'rechazo_destinatario_user_id',
            'rechazo_en',
        ]);
    }
}
