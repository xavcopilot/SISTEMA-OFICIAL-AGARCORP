<?php

namespace App\Livewire;

use App\Support\Filament\DatabaseNotificationSender;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

class PersistentFilamentNotifications extends Notifications
{
    #[On('notificationsSent')]
    public function pullNotificationsFromSession(): void
    {
        $user = $this->getUser();

        foreach (session()->pull('filament.notifications') ?? [] as $notificationData) {
            $notification = Notification::fromArray($notificationData);

            if ($user && $this->shouldStoreInHistory($notificationData)) {
                DatabaseNotificationSender::sendNow($notification, $user, dispatchEvent: true);
            }

            $this->pushNotification($notification);
        }
    }

    /**
     * @param array<string, mixed> $notificationData
     */
    protected function shouldStoreInHistory(array $notificationData): bool
    {
        if (! (bool) config('filament-notification-history.enabled', true)) {
            return false;
        }

        $title = mb_strtolower(trim((string) ($notificationData['title'] ?? '')));
        $body = mb_strtolower(trim((string) ($notificationData['body'] ?? '')));
        $status = mb_strtolower(trim((string) ($notificationData['status'] ?? '')));

        $excludedTitles = array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) config('filament-notification-history.exclude_titles', [])
        );

        if (in_array($title, $excludedTitles, true)) {
            return false;
        }

        $excludedStatuses = array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) config('filament-notification-history.exclude_statuses', [])
        );

        if (in_array($status, $excludedStatuses, true)) {
            return false;
        }

        $excludedContains = array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) config('filament-notification-history.exclude_contains', [])
        );

        foreach ($excludedContains as $needle) {
            if ($needle === '') {
                continue;
            }

            if (str_contains($title, $needle) || str_contains($body, $needle)) {
                return false;
            }
        }

        return true;
    }
}
