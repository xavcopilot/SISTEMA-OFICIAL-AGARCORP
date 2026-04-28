<?php

namespace App\Support\Filament;

use App\Filament\Resources\AdministracionFacturas\AdministracionFacturasResource;
use App\Filament\Resources\AdministracionPagosOdc\AdministracionPagosOdcResource;
use App\Filament\Resources\AprobacionesCompra\AprobacionesCompraResource;
use App\Filament\Resources\AprobacionOdcs\AprobacionOdcResource;
use App\Filament\Resources\AprobacionSumarios\AprobacionSumariosResource;
use App\Filament\Resources\FacturasCompra\FacturasCompraResource;
use App\Filament\Resources\InspeccionSumarios\InspeccionSumariosResource;
use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Filament\Resources\RecepcionMaterialesNuevos\RecepcionMaterialesNuevosResource;
use App\Filament\Resources\RecepcionProductosProcura\RecepcionProductosProcuraResource;
use App\Filament\Resources\SolicitudesCompra\SolicitudCompraResource;
use App\Filament\Resources\Sumarios\SumarioResource;
use App\Support\Filament\DatabaseNotificationSender;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class FlowInboxNotificationService
{
    /**
     * @return array<int, array{resource: class-string, module: string, description: string}>
     */
    public function trackedResources(): array
    {
        return [
            [
                'resource' => SolicitudCompraResource::class,
                'module' => 'Solicitudes',
                'description' => 'Solicitudes con pendientes de firma, revision o correccion.',
            ],
            [
                'resource' => AprobacionesCompraResource::class,
                'module' => 'Solicitudes',
                'description' => 'Bandeja de aprobaciones por area para el flujo de solicitud de compra.',
            ],
            [
                'resource' => SumarioResource::class,
                'module' => 'Compras',
                'description' => 'Seguimiento de sumarios en creacion, validacion y correccion.',
            ],
            [
                'resource' => InspeccionSumariosResource::class,
                'module' => 'Compras',
                'description' => 'Sumarios pendientes de inspeccion en Finanzas.',
            ],
            [
                'resource' => AprobacionSumariosResource::class,
                'module' => 'Compras',
                'description' => 'Sumarios pendientes de aprobacion gerencial.',
            ],
            [
                'resource' => OrdenCompraResource::class,
                'module' => 'ODC',
                'description' => 'Ordenes en creacion, correcciones y etapas operativas.',
            ],
            [
                'resource' => AprobacionOdcResource::class,
                'module' => 'ODC',
                'description' => 'ODC pendientes de aprobacion por Gerencia de Finanzas.',
            ],
            [
                'resource' => AdministracionPagosOdcResource::class,
                'module' => 'Pagos',
                'description' => 'ODC pendientes de registro de pago en Finanzas.',
            ],
            [
                'resource' => RecepcionProductosProcuraResource::class,
                'module' => 'Recepcion',
                'description' => 'ODC pagadas en transito, pendientes de soporte de entrega.',
            ],
            [
                'resource' => RecepcionMaterialesNuevosResource::class,
                'module' => 'Recepcion',
                'description' => 'Materiales en recepcion de almacen pendientes de transicion.',
            ],
            [
                'resource' => FacturasCompraResource::class,
                'module' => 'Facturas',
                'description' => 'Facturas recibidas pendientes de envio a Administracion.',
            ],
            [
                'resource' => AdministracionFacturasResource::class,
                'module' => 'Facturas',
                'description' => 'Facturas pendientes de carga contable en Administracion.',
            ],
        ];
    }

    public function syncUnreadNotificationsForUser(User $user): void
    {
        $windows = $this->runAsUser($user, fn (): Collection => $this->windowCountsForUser($user));
        $cacheKey = $this->cacheKey($user);
        $previousCounts = (array) Cache::get($cacheKey, []);

        foreach ($windows as $window) {
            $resource = (string) $window['resource'];
            $count = (int) $window['count'];
            $previous = (int) ($previousCounts[$resource] ?? 0);

            if ($count <= 0 || $count <= $previous) {
                continue;
            }

            $delta = $count - $previous;

            $notification = Notification::make()
                ->title('Nueva notificacion de flujo')
                ->body(
                    'Modulo ' . (string) $window['module']
                    . ', ventana ' . (string) $window['label']
                    . ': tienes ' . $count . ' pendientes (' . $delta . ' nuevos).'
                )
                ->warning();

            DatabaseNotificationSender::sendNow($notification, $user, dispatchEvent: true);
        }

        Cache::put(
            $cacheKey,
            $windows
                ->mapWithKeys(fn (array $window): array => [(string) $window['resource'] => (int) $window['count']])
                ->all(),
            now()->addDays(14)
        );
    }

    /**
     * @return Collection<int, array{resource: class-string, module: string, label: string, description: string, count: int, hasPending: bool, url: string, badgeColor: string}>
     */
    public function windowCountsForUser(?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user) {
            return collect();
        }

        return collect($this->trackedResources())
            ->map(function (array $window) {
                $resource = (string) $window['resource'];

                if (! $this->canAccessResource($resource)) {
                    return null;
                }

                $count = $this->resolveResourceBadgeCount($resource);

                return [
                    'resource' => $resource,
                    'module' => (string) $window['module'],
                    'label' => $resource::getNavigationLabel(),
                    'description' => (string) $window['description'],
                    'count' => $count,
                    'hasPending' => $count > 0,
                    'url' => $resource::getUrl('index'),
                    'badgeColor' => $count > 0
                        ? (method_exists($resource, 'getNavigationBadgeColor') ? ((string) ($resource::getNavigationBadgeColor() ?? 'primary')) : 'primary')
                        : 'gray',
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @template TReturn
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function runAsUser(User $user, callable $callback)
    {
        $guard = Auth::guard();
        $previousUser = $guard->user();

        $guard->setUser($user);

        try {
            return $callback();
        } finally {
            if ($previousUser) {
                $guard->setUser($previousUser);
            } else {
                $guard->logout();
            }
        }
    }

    /**
     * @return Collection<int, array{module: string, count: int, hasPending: bool}>
     */
    public function moduleCountsForUser(?User $user = null): Collection
    {
        return $this->windowCountsForUser($user)
            ->groupBy('module')
            ->map(fn (Collection $windows, string $module): array => [
                'module' => $module,
                'count' => (int) $windows->sum(fn (array $window): int => (int) $window['count']),
                'hasPending' => (bool) $windows->contains(fn (array $window): bool => (bool) $window['hasPending']),
            ])
            ->sortByDesc('count')
            ->values();
    }

    protected function canAccessResource(string $resource): bool
    {
        if (method_exists($resource, 'shouldRegisterNavigation')) {
            return (bool) $resource::shouldRegisterNavigation();
        }

        if (method_exists($resource, 'canViewAny')) {
            return (bool) $resource::canViewAny();
        }

        return false;
    }

    protected function resolveResourceBadgeCount(string $resource): int
    {
        if (! method_exists($resource, 'getNavigationBadge')) {
            return 0;
        }

        $badge = $resource::getNavigationBadge();

        if (blank($badge)) {
            return 0;
        }

        return max(0, (int) preg_replace('/[^0-9-]/', '', (string) $badge));
    }

    protected function cacheKey(User $user): string
    {
        return 'agarcorp:flow-inbox:counts:' . (string) $user->getAuthIdentifier();
    }
}
