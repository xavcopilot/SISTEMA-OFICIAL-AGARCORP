<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

trait HandlesSignatureValidationFailure
{
    protected function handleSignatureValidationFailure(
        ValidationException $exception,
        string $title = 'No se pudo enviar'
    ): void {
        $this->setErrorBag($exception->validator->getMessageBag());

        Notification::make()
            ->title($title)
            ->body('Hay datos obligatorios pendientes en el formulario. Revisa los campos marcados en rojo e intenta nuevamente.')
            ->danger()
            ->send();

        if (method_exists($this, 'unmountAction')) {
            $this->unmountAction();
        }
    }
}
