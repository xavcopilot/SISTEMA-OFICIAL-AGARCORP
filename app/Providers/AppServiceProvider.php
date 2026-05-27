<?php

namespace App\Providers;

use App\Livewire\PersistentFilamentNotifications;
use App\Support\Filament\DatabaseNotificationSender;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Observers\UserObserver;
use Filament\Facades\Filament;
use App\Models\Ticket;
use App\Support\Filament\FlowInboxNotificationService;
use Filament\Notifications\Notification;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::component('notifications', PersistentFilamentNotifications::class);

        Filament::serving(function (): void {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();
            $throttleKey = 'flow-inbox:sync-throttle:' . (string) $user->id;

            if (cache()->has($throttleKey)) {
                return;
            }

            cache()->put($throttleKey, true, now()->addSeconds(20));

            app(FlowInboxNotificationService::class)->syncUnreadNotificationsForUser($user);
        });

        User::observe(UserObserver::class); // Usamos el nombre corto porque ya lo importaste arriba
        Schema::defaultStringLength(125);

        // cada vez que Filament sirve una petición mostramos un toast
        // si hay tickets nuevos desde la última notificación.
        Filament::serving(function () {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();
            if (! $user->hasRole(['admin', 'Alta Gerencia', 'A.I.T'])) {
                return;
            }

            // Avoid expensive checks on every single navigation click.
            $throttleKey = "tickets:check_throttle:{$user->id}";
            if (cache()->has($throttleKey)) {
                return;
            }
            cache()->put($throttleKey, true, now()->addSeconds(20));

            $cacheKey = "tickets:last_notified:{$user->id}";
            $lastNotified = cache()->get($cacheKey, now()->subMinutes(60));

            $countCacheKey = "tickets:new_count:{$user->id}";
            $newCount = cache()->remember($countCacheKey, now()->addSeconds(30), function () use ($lastNotified) {
                return Ticket::where('estado', 'Abierto')
                    ->where('created_at', '>', $lastNotified)
                    ->count();
            });

            if ($newCount > 0) {
                Notification::make()
                    ->title("Nuevos tickets: {$newCount}")
                    ->body("Hay {$newCount} nuevas solicitudes pendientes. Revisa el listado.")
                    ->warning()
                    ->send();

                cache()->put($cacheKey, now());
            }
        });
    }
}
