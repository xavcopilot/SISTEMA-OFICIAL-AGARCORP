<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\AprobacionesCompra\AprobacionesCompraResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ConsultarEntradas\ConsultarEntradasResource;
use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use App\Filament\Resources\SolicitudesCompra\SolicitudCompraResource;
use App\Filament\Resources\Sumarios\SumarioResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class DesktopWelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard.desktop-welcome-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getViewData(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        $hour = (int) now()->format('H');
        $minute = (int) now()->format('i');
        $minutesOfDay = ($hour * 60) + $minute;

        $greeting = match (true) {
            $minutesOfDay <= 719 => 'Buenos dias',
            $minutesOfDay <= 1079 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        $role = $user?->getRoleNames()->first() ?? 'Sin rol asignado';
        $department = $user?->departamento?->nombre ?? 'Departamento no asignado';
        $cargo = $user?->cargo?->nombre ?? 'Cargo no asignado';

        $highlights = array_values(array_filter([
            $user?->can('Create:SolicitudCompra') ? 'Puedes registrar solicitudes de compra y dar seguimiento a tus requerimientos.' : null,
            AprobacionesCompraResource::canAccess() ? 'Tienes bandeja de revision para aprobar, devolver o avanzar solicitudes.' : null,
            SumarioResource::canViewAny() ? 'Puedes trabajar con sumarios y el flujo documental de compras.' : null,
            OrdenCompraResource::canViewAny() ? 'Tienes acceso a ordenes de compra, recepcion y trazabilidad del cierre.' : null,
            ConsultarEntradasResource::canAccess() ? 'Puedes consultar movimientos de inventario y entradas registradas.' : null,
            CategoryResource::canViewAny() ? 'Puedes administrar catalogos clave del inventario.' : null,
            $user?->can('Manage:Ticket') ? 'Tienes gestion global del modulo de soporte y sus estados.' : null,
        ]));

        $quickLinks = array_values(array_filter([
            SolicitudCompraResource::canAccess() ? [
                'title' => 'Solicitudes',
                'description' => 'Crear, consultar o corregir solicitudes de compra.',
                'url' => SolicitudCompraResource::getUrl('index'),
                'tone' => 'blue',
            ] : null,
            AprobacionesCompraResource::canAccess() ? [
                'title' => 'Bandeja de revision',
                'description' => 'Atender aprobaciones y devoluciones pendientes.',
                'url' => AprobacionesCompraResource::getUrl('index'),
                'tone' => 'amber',
            ] : null,
            SumarioResource::canViewAny() ? [
                'title' => 'Sumarios',
                'description' => 'Comparar cotizaciones y organizar compras por proveedor.',
                'url' => SumarioResource::getUrl('index'),
                'tone' => 'emerald',
            ] : null,
            OrdenCompraResource::canViewAny() ? [
                'title' => 'Ordenes de compra',
                'description' => 'Revisar ODC, recepcion, conformidad y cierre.',
                'url' => OrdenCompraResource::getUrl('index'),
                'tone' => 'indigo',
            ] : null,
            TicketResource::canCreate() || $user?->can('Manage:Ticket') ? [
                'title' => 'Soporte',
                'description' => 'Abrir tickets o gestionar solicitudes tecnicas.',
                'url' => TicketResource::getUrl('index'),
                'tone' => 'rose',
            ] : null,
            ConsultarEntradasResource::canAccess() ? [
                'title' => 'Inventario',
                'description' => 'Entradas, salidas y trazabilidad de materiales.',
                'url' => ConsultarEntradasResource::getUrl('index'),
                'tone' => 'slate',
            ] : null,
        ]));

        $lastVisitedModule = $user
            ? Cache::get('agarcorp:last-module:' . $user->getAuthIdentifier())
            : null;

        return [
            'greeting' => $greeting,
            'userName' => $user?->name ?? 'Usuario',
            'role' => $role,
            'department' => $department,
            'cargo' => $cargo,
            'today' => now()->translatedFormat('l, d \d\e F \d\e Y'),
            'lastVisitedModule' => $lastVisitedModule,
            'highlights' => $highlights,
            'quickLinks' => $quickLinks,
        ];
    }
}
