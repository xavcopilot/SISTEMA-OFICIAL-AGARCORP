<?php

namespace App\Support;

use App\Models\User;
use App\Support\Filament\DatabaseNotificationSender;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ActivityNotification
{
    public static function record(?User $user, string $title, string $body = '', string $status = 'info', ?string $url = null): void
    {
        if (! $user) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body);

        if ($url) {
            $notification->actions([
                Action::make('abrir')
                    ->label('Abrir')
                    ->url($url)
                    ->button(),
            ]);
        }

        $notification = match ($status) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            default => $notification->info(),
        };

        DatabaseNotificationSender::sendNow($notification, $user, dispatchEvent: true);
    }
}
