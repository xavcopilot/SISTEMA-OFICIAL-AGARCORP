<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AprobacionesCompra\AprobacionesCompraResource;
use App\Filament\Resources\DailyWithdrawals\DailyWithdrawalResource;
use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Filament\Resources\SolicitudesCompra\SolicitudCompraResource;
use App\Filament\Resources\Sumarios\SumarioResource;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;
use Illuminate\Support\Facades\Hash;

class NotificationCenter extends Page
{
    protected static ?string $title = 'Centro de Notificaciones';

    protected static ?string $navigationLabel = 'Notificaciones';

    protected static ?string $slug = 'notificaciones';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBell;

    protected static string | UnitEnum | null $navigationGroup = 'Administracion';

    protected string $view = 'filament.pages.notification-center';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('limpiarHistorial')
                ->label('Limpiar Historial')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->form([
                    TextInput::make('password')
                        ->label('Clave de inicio de sesion')
                        ->password()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();

                    if (! $user) {
                        return;
                    }

                    $password = (string) ($data['password'] ?? '');

                    if (! Hash::check($password, (string) $user->password)) {
                        Notification::make()
                            ->title('Clave incorrecta')
                            ->body('No se pudo limpiar el historial porque la clave es invalida.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $user->notifications()->delete();

                    Notification::make()
                        ->title('Historial limpiado')
                        ->body('Se eliminaron tus notificaciones guardadas.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $modules = $this->getNotificationModules();

        return [
            'total' => (int) ($user?->notifications()->count() ?? 0),
            'unread' => (int) ($user?->unreadNotifications()->count() ?? 0),
            'latest' => $user?->notifications()->latest()->take(20)->get() ?? collect(),
            'modules' => $modules,
            'notificationModules' => $modules->count(),
            'activeModules' => $modules->where('hasPending', true)->count(),
            'pendingItems' => $modules->sum(fn (array $module): int => (int) $module['count']),
        ];
    }

    protected function getNotificationModules(): Collection
    {
        $resources = [
            [
                'resource' => SolicitudCompraResource::class,
                'description' => 'Solicitudes con avisos de devolucion, avance y seguimiento del flujo de compra.',
                'group' => 'Compras',
            ],
            [
                'resource' => TicketResource::class,
                'description' => 'Tickets de soporte que requieren gestion o seguimiento.',
                'group' => 'Administracion',
            ],
            [
                'resource' => AprobacionesCompraResource::class,
                'description' => 'Solicitudes de compra pendientes de revision en tu bandeja.',
                'group' => 'Compras',
            ],
            [
                'resource' => SumarioResource::class,
                'description' => 'Eventos del flujo de sumarios y seguimiento documental de compras.',
                'group' => 'Compras',
            ],
            [
                'resource' => OrdenCompraResource::class,
                'description' => 'Avisos sobre ordenes, recepcion, conformidad y cierre del proceso.',
                'group' => 'Compras',
            ],
            [
                'resource' => DailyWithdrawalResource::class,
                'description' => 'Retiros diarios pendientes por atender en inventario.',
                'group' => 'Inventario',
            ],
        ];

        return collect($resources)
            ->map(function (array $module): ?array {
                $resource = $module['resource'];

                if (! $this->canAccessNotificationModule($resource)) {
                    return null;
                }

                $badge = method_exists($resource, 'getNavigationBadge') ? $resource::getNavigationBadge() : null;
                $count = blank($badge) ? 0 : (int) $badge;

                return [
                    'label' => $resource::getNavigationLabel(),
                    'description' => $module['description'],
                    'group' => $module['group'],
                    'count' => $count,
                    'hasPending' => $count > 0,
                    'url' => $resource::getUrl('index'),
                    'badgeColor' => $count > 0
                        ? (method_exists($resource, 'getNavigationBadgeColor') ? ($resource::getNavigationBadgeColor() ?? 'primary') : 'primary')
                        : 'gray',
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $module): int => $module['hasPending'] ? 0 : 1,
                fn (array $module): string => $module['label'],
            ])
            ->values();
    }

    protected function canAccessNotificationModule(string $resource): bool
    {
        if (method_exists($resource, 'shouldRegisterNavigation')) {
            return (bool) $resource::shouldRegisterNavigation();
        }

        if (method_exists($resource, 'canViewAny')) {
            return (bool) $resource::canViewAny();
        }

        return false;
    }
}
