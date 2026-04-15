<?php

namespace App\Filament\Resources\SolicitudesCompra\Pages;

use App\Filament\Resources\SolicitudesCompra\SolicitudCompraResource;
use App\Support\SolicitudCompraFlow;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateSolicitudCompra extends CreateRecord
{
    protected static string $resource = SolicitudCompraResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            \Filament\Actions\Action::make('saveDraft')
                ->label('Guardar como borrador')
                ->color('warning')
                ->action(function () {
                    $data = $this->form->getRawState();
                    $data['estado'] = 'BORRADOR';
                    
                    // Remove unsupported or computed fields before create if needed, 
                    // though Eloquent ignores them if guarded handling is proper.
                    $data['solicitado_por_user_id'] = auth()->id();
                    
                    $record = static::getModel()::create($data);
                    
                    if (!empty($data['items'])) {
                        $record->items()->createMany(array_values($data['items']));
                    }
                    
                    $this->record = $record;
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Borrador guardado')
                        ->body('Tu solicitud ahora aparece en la lista principal como BORRADOR.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            $this->getCancelFormAction(),
        ];
    }

    protected static bool $canCreateAnother = false;

    private ?array $signatureSubmissionData = null;

    protected function getCreateFormAction(): Action
    {
        return Action::make('create')
            ->label('Enviar solicitud')
            ->color('primary')
            ->keyBindings(['mod+s'])
            ->schema($this->getSignatureSchema())
            ->modalHeading('Enviar solicitud')
            ->modalSubmitActionLabel('Enviar solicitud')
            ->action(function (array $data): void {
                if (! $this->validateSignatureSubmission($data)) {
                    $this->halt();

                    return;
                }

                $this->signatureSubmissionData = $data;

                try {
                    $this->create();
                } finally {
                    $this->signatureSubmissionData = null;
                }
            });
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['solicitado_por_user_id'] = $data['solicitado_por_user_id'] ?? auth()->id();
        $data['estado'] = 'EN_ESPERA_DE_COTIZACION';
        $data['cargo_solicitante'] = $data['cargo_solicitante'] ?? auth()->user()?->cargo?->nombre;
        $data['fecha_solicitante'] = now()->toDateString();
        $data['firma_solicitante'] = '__ENVIADA__';

        $data['rechazo_etapa'] = null;
        $data['rechazo_comentario'] = null;
        $data['rechazo_por_user_id'] = null;
        $data['rechazo_destinatario_user_id'] = null;
        $data['rechazo_en'] = null;

        $data['por_almacen_user_id'] = $data['por_almacen_user_id'] ?? SolicitudCompraFlow::defaultAlmacenUserId();
        $data['cargo_almacen'] = $data['cargo_almacen'] ?? SolicitudCompraFlow::cargoForUserId($data['por_almacen_user_id'] ?? null);

        $data['cargo_aprobador'] = $data['cargo_aprobador'] ?? SolicitudCompraFlow::cargoForUserId($data['aprobado_por_user_id'] ?? null);

        $data['recibido_por_user_id'] = $data['recibido_por_user_id'] ?? SolicitudCompraFlow::defaultProcuraUserId();
        $data['cargo_receptor'] = $data['cargo_receptor'] ?? SolicitudCompraFlow::cargoForUserId($data['recibido_por_user_id'] ?? null);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (blank($this->record->codigo_control)) {
            $this->record->forceFill([
                'codigo_control' => (string) $this->record->id,
            ])->save();
        }
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
}
