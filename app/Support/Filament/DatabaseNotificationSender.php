<?php

namespace App\Support\Filament;

use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class DatabaseNotificationSender
{
    /**
     * @param  Model|Authenticatable|Collection|array<Model|Authenticatable>  $users
     */
    public static function sendNow(FilamentNotification $notification, Model | Authenticatable | Collection | array $users, bool $dispatchEvent = false): void
    {
        if (! is_iterable($users)) {
            $users = [$users];
        }

        $databasePayload = $notification->toDatabase();

        Notification::sendNow($users, $databasePayload);

        if (! $dispatchEvent) {
            return;
        }

        foreach ($users as $user) {
            DatabaseNotificationsSent::dispatch($user);
        }
    }
}
