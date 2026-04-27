<?php

namespace App\Filament\Resources\OrdenesCompra\Pages;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Hash;

class EditOrdenCompra extends EditRecord
{
    protected static string $resource = OrdenCompraResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected Width | string | null $maxWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enviarGerenciaFinanzas')
                ->label('Enviar a Gerencia Finanzas')
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
                ->visible(fn (): bool => (string) ($this->record->workflow_post_compra ?? '') === 'BORRADOR_ODC')
                ->action(function (array $data): void {
                    $this->submitToGerenciaFinanzas($data);
                }),
        ];
    }

    protected function getFormActions(): array
    {
        if ((string) ($this->record->workflow_post_compra ?? '') === 'BORRADOR_ODC') {
            return [
                Action::make('submitToGerenciaFinanzas')
                    ->label('Enviar ODC a Gerencia Finanzas')
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
                        $this->submitToGerenciaFinanzas($data);
                    }),
                $this->getCancelFormAction(),
            ];
        }

        if ((string) ($this->record->workflow_post_compra ?? '') === 'PENDIENTE_APROBACION_GERENCIA_FINANZAS'
            && $this->canSendToPagoFinanzas()) {
            return [
                Action::make('submitToPagoFinanzas')
                    ->label('Enviar a Pago Finanzas')
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
                        $this->submitToPagoFinanzas($data);
                    }),
                $this->getCancelFormAction(),
            ];
        }

        return parent::getFormActions();
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

    private function submitToGerenciaFinanzas(array $data): void
    {
        if (! $this->validateSignaturePassword($data)) {
            return;
        }

        $this->save(false, false);

        $this->record->forceFill([
            'elaborado_por_user_id' => $this->record->elaborado_por_user_id ?: auth()->id(),
            'elaborado_firmado_at' => now(),
            'estado' => 'PENDIENTE_APROBACION',
            'workflow_post_compra' => 'PENDIENTE_APROBACION_GERENCIA_FINANZAS',
        ])->save();

        Notification::make()
            ->title('ODC enviada a Gerencia Finanzas')
            ->body('La orden quedo en espera de aprobacion de Gerencia de Finanzas.')
            ->success()
            ->send();

        $this->refreshFormData(['elaborado_por_user_id', 'elaborado_firmado_at', 'estado', 'workflow_post_compra']);
    }

    private function submitToPagoFinanzas(array $data): void
    {
        if (! $this->canSendToPagoFinanzas()) {
            Notification::make()
                ->title('Accion no permitida')
                ->body('Solo Gerencia de Finanzas puede enviar esta ODC a pago.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->validateSignaturePassword($data)) {
            return;
        }

        $this->save(false, false);

        $this->record->forceFill([
            'estado' => 'APROBADA',
            'workflow_post_compra' => 'PENDIENTE_PAGO_FINANZAS',
            'aprobado_por_user_id' => $this->record->aprobado_por_user_id ?: auth()->id(),
            'aprobado_firmado_at' => now(),
        ])->save();

        Notification::make()
            ->title('ODC enviada a Pago Finanzas')
            ->body('La ODC quedo en la bandeja de pago de Finanzas.')
            ->success()
            ->send();

        $this->refreshFormData(['aprobado_por_user_id', 'aprobado_firmado_at', 'estado', 'workflow_post_compra']);
    }

    private function canSendToPagoFinanzas(): bool
    {
        return (bool) auth()->user()?->hasRole('Gerencia de Finanzas');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $subTotal = round((float) ($this->record->items()->sum('precio_total') ?? 0), 2);
        $iva = round($subTotal * 0.16, 2);
        $montoExento = round((float) ($data['monto_exento'] ?? 0), 2);
        $gastosAdicionales = round((float) ($data['gastos_adicionales'] ?? 0), 2);

        $data['sub_total'] = $subTotal;
        $data['iva_16'] = $iva;
        $data['total_general'] = round($subTotal + $iva + $montoExento + $gastosAdicionales, 2);

        return $data;
    }
}
