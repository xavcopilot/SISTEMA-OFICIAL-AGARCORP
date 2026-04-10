<?php

namespace App\Filament\Resources\Tickets\Widgets;

use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Notifications\Notification;

class TicketStatsOverview extends BaseWidget
{
   protected function getStats(): array
{
    $user = auth()->user();
    $esGestor = (bool) $user?->can('Manage:Ticket');

    // Definimos la base de la consulta: si no es gestor, filtramos por su ID
    $query = $esGestor ? Ticket::query() : Ticket::where('user_id', $user->id);

    // Si tiene permiso de gestion, notificar en pantalla cuando haya nuevos tickets.
    if ($esGestor) {
        $cacheKey = "tickets:last_notified:{$user->id}";
        $lastNotified = cache()->get($cacheKey, now()->subMinutes(60));

        $newCount = Ticket::where('estado', 'Abierto')
            ->where('created_at', '>', $lastNotified)
            ->count();

        if ($newCount > 0) {
            Notification::make()
                ->title("Nuevos tickets: {$newCount}")
                ->body("Hay {$newCount} nuevas solicitudes pendientes. Revisa el listado.")
                ->warning()
                ->send();

            // Actualizamos el 'last_notified' para no repetir la notificación
            cache()->put($cacheKey, now());
        }
    }

    return [
        Stat::make($esGestor ? 'Total Solicitudes' : 'Mis Solicitudes', $query->count())
            ->description($esGestor ? 'Global de la empresa' : 'Total de tus tickets enviados')
            ->descriptionIcon('heroicon-m-clipboard-document-list')
            ->color('info'),

        Stat::make($esGestor ? 'Pendientes' : 'Mis Pendientes', (clone $query)->where('estado', 'Abierto')->count())
            ->description('En espera de revisión')
            ->color('danger'),

        Stat::make($esGestor ? 'Resueltos' : 'Mis Resueltos', (clone $query)->where('estado', 'Resuelto')->count())
            ->description('Atendidos por A.I.T')
            ->color('success'),
    ];
}
}