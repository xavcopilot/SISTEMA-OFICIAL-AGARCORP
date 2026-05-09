<?php

namespace App\Filament\Resources\OrdenesCompra\Pages;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Models\Sumario;
use App\Support\BcvRateService;
use App\Support\SumarioFinanceApprovalService;
use App\Support\UserSignaturePath;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
        $actions = [];

        $rechazoEtapa = (string) ($this->record->rechazo_etapa ?? '');

        if ((string) ($this->record->estado ?? '') === 'RECHAZADA'
            && in_array($rechazoEtapa, ['gerencia_finanzas', 'validacion_finanzas'], true)) {
            $actions[] = Action::make('verMotivoRechazo')
                ->label('Motivo de rechazo')
                ->color('warning')
                ->modalHeading('Motivo de rechazo - ' . $this->rejectionStageLabel($rechazoEtapa))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalWidth('2xl')
                ->fillForm(fn (): array => [
                    'rechazo_etapa' => strtoupper(str_replace('_', ' ', (string) $this->record->rechazo_etapa)),
                    'rechazo_por_nombre' => (string) ($this->record->rechazoPor?->name ?? '-'),
                    'rechazo_en' => filled($this->record->rechazo_en)
                        ? (string) \Illuminate\Support\Carbon::parse($this->record->rechazo_en)->format('d/m/Y H:i')
                        : '-',
                    'rechazo_comentario' => (string) ($this->record->rechazo_comentario ?? ''),
                ])
                ->schema([
                    \Filament\Schemas\Components\Grid::make(3)
                        ->schema([
                            TextInput::make('rechazo_etapa')->label('Etapa')->disabled(),
                            TextInput::make('rechazo_por_nombre')->label('Rechazada por')->disabled(),
                            TextInput::make('rechazo_en')->label('Fecha rechazo')->disabled(),
                        ]),
                    Textarea::make('rechazo_comentario')->label('Comentario')->rows(3)->disabled(),
                ]);

            $actions[] = Action::make('eliminarParaHistorial')
                ->label('Eliminar para Historial')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Enviar rechazo a historial')
                ->modalDescription('La ODC quedara como rechazada definitiva en historial y ya no podra corregirse.')
                ->action(function (): void {
                    $this->record->forceFill([
                        'rechazo_etapa' => 'historial',
                    ])->save();

                    Notification::make()
                        ->title('ODC enviada a historial')
                        ->body('La ODC rechazada se marco como historica y ya no permitira correcciones.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['rechazo_etapa']);
                });
        }

        return $actions;
    }

    protected function getFormActions(): array
    {
        if ((string) ($this->record->workflow_post_compra ?? '') === 'BORRADOR_ODC'
            && ((string) ($this->record->estado ?? '') !== 'RECHAZADA'
                || in_array((string) ($this->record->rechazo_etapa ?? ''), ['gerencia_finanzas', 'validacion_finanzas'], true))) {
            return [
                Action::make('submitToValidacionFinanzas')
                    ->label('Enviar ODC')
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
                        $this->submitToValidacionFinanzas($data);
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

    private function validateSignaturePassword(array $data, ?string $requiredRole = null, bool $requirePng = false): bool
    {
        $user = auth()->user();

        if (! $user) {
            Notification::make()
                ->title('No se pudo firmar')
                ->body('Debes iniciar sesion para registrar esta firma.')
                ->danger()
                ->send();

            return false;
        }

        if ($requiredRole !== null && ! $user->hasRole($requiredRole)) {
            Notification::make()
                ->title('No se pudo firmar')
                ->body('La firma solo puede registrarla un usuario con el rol ' . $requiredRole . '.')
                ->danger()
                ->send();

            return false;
        }

        if ($requirePng && UserSignaturePath::findByUserId((int) $user->id) === null) {
            Notification::make()
                ->title('No se pudo firmar')
                ->body('Tu usuario no tiene una firma PNG registrada. Carga primero la firma asociada a tu ID.')
                ->danger()
                ->send();

            return false;
        }

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

        $signatureHash = $user->firma_password ?: $user->password ?: '';

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

    private function submitToValidacionFinanzas(array $data): void
    {
        if (! $this->validateSignaturePassword($data, 'Procura', true)) {
            return;
        }

        $this->save(false, false);

        $isRejectedCorrection = (string) ($this->record->estado ?? '') === 'RECHAZADA'
            && (string) ($this->record->rechazo_etapa ?? '') === 'gerencia_finanzas';

        $this->record->forceFill([
            'elaborado_por_user_id' => auth()->id(),
            'elaborado_firmado_at' => now(),
            'estado' => 'PENDIENTE_VALIDACION_FINANZAS',
            'workflow_post_compra' => 'PENDIENTE_VALIDACION_FINANZAS',
            'rechazo_etapa' => null,
            'rechazo_comentario' => null,
            'rechazo_por_user_id' => null,
            'rechazo_en' => null,
        ])->save();

        if ($isRejectedCorrection) {
            $sumarioId = (int) ($this->record->sumario_id ?? 0);

            if ($sumarioId > 0) {
                Sumario::query()->whereKey($sumarioId)->update([
                    'estado' => 'REVISADO_FINANZAS',
                    'workflow_estado' => 'APROBADO_GERENCIA_FINANZAS',
                ]);
            }
        } else {
            $this->syncSumarioWorkflowAfterSignatureSend();
        }

        Notification::make()
            ->title($isRejectedCorrection ? 'ODC corregida y reenviada' : 'ODC enviada a Validacion Finanzas')
            ->body($isRejectedCorrection
                ? 'La ODC corregida fue reenviada a Validacion Finanzas para nueva revision.'
                : 'La orden quedo en espera de revision por Validacion Finanzas.')
            ->success()
            ->send();

        $this->redirect(OrdenCompraResource::getUrl('index'));
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

        if (! $this->validateSignaturePassword($data, 'Gerencia de Finanzas', true)) {
            return;
        }

        $this->save(false, false);

        $currentRate = app(BcvRateService::class)->rateForOrderCreation();

        $this->record->forceFill([
            'tasa_bcv' => $currentRate !== null
                ? round((float) $currentRate, 4)
                : $this->record->tasa_bcv,
            'estado' => 'APROBADA',
            'workflow_post_compra' => 'PENDIENTE_PAGO_FINANZAS',
            'aprobado_por_user_id' => auth()->id(),
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

    private function rejectionStageLabel(string $stage): string
    {
        return match ($stage) {
            'validacion_finanzas' => 'Validacion Finanzas',
            'gerencia_finanzas' => 'Gerencia de Finanzas',
            default => strtoupper(str_replace('_', ' ', $stage ?: '-')),
        };
    }

    private function syncSumarioWorkflowAfterSignatureSend(): void
    {
        $sumarioId = (int) ($this->record->sumario_id ?? 0);

        if ($sumarioId <= 0) {
            return;
        }

        $sumario = Sumario::query()->find($sumarioId);

        if (! $sumario) {
            return;
        }

        $sumario->loadMissing(['ordenesCompra', 'items.opciones', 'items.solicitudCompraItem.solicitudCompra']);

        $service = app(SumarioFinanceApprovalService::class);

        $hasPendingGroups = $service->pendingProviderGroups($sumario)
            ->filter(fn (array $group): bool => ! $service->hasExistingGeneratedOrderForGroup($sumario, $group))
            ->isNotEmpty();

        $sumario->forceFill([
            'estado' => $hasPendingGroups ? 'PENDIENTE_CREACION_ODC' : 'REVISADO_FINANZAS',
            'workflow_estado' => $hasPendingGroups ? 'APROBADO_GERENCIA_FINANZAS' : 'ODC_GENERADA',
        ])->save();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $subTotal = round((float) ($this->record->items()->sum('precio_total') ?? 0), 2);
        $iva = round($subTotal * 0.16, 2);
        $montoExento = round((float) ($data['monto_exento'] ?? 0), 2);
        $gastosAdicionales = round((float) ($data['gastos_adicionales'] ?? 0), 2);

        $data['monto_exento'] = $montoExento;
        $data['sub_total'] = $subTotal;
        $data['iva_16'] = $iva;
        $data['gastos_adicionales'] = $gastosAdicionales;
        $data['total_general'] = round($subTotal + $iva + $montoExento + $gastosAdicionales, 2);

        return $data;
    }
}
