<?php

namespace App\Filament\Widgets\Compras;

use App\Models\SolicitudCompra;
use App\Support\SolicitudCompraFlow;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AprobacionesCompraKpiStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Indicadores de aprobaciones';

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user || ! SolicitudCompraFlow::isReviewer($user)) {
            return [];
        }

        $stats = [];

        foreach ($this->availableRoleConfigsForUser($user) as $config) {
            $approvedCount = $this->approvedCount($config, (int) $user->id);
            $rejectedCount = $this->rejectedCount($config, (int) $user->id);
            $closedCount = $this->closedCount($config, (int) $user->id);

            $stats[] = Stat::make('Aprobadas (' . $config['label'] . ')', number_format($approvedCount))
                ->description('Solicitudes aprobadas')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success');

            $stats[] = Stat::make('Rechazadas (' . $config['label'] . ')', number_format($rejectedCount))
                ->description('Solicitudes rechazadas')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');

            $stats[] = Stat::make('Cerradas (' . $config['label'] . ')', number_format($closedCount))
                ->description('Aprobadas por ' . $config['label'] . ' que llegaron a CERRADA')
                ->descriptionIcon('heroicon-m-flag')
                ->color('info');
        }

        return $stats;
    }

    /**
     * @return array<int, array{label: string, user_column: string, approved_date_column: string, rejection_stage: string}>
     */
    private function availableRoleConfigsForUser($user): array
    {
        $configs = [];

        if ($user->hasRole(SolicitudCompraFlow::STORAGE_ROLES)) {
            $configs[] = [
                'label' => 'Almacen',
                'user_column' => 'por_almacen_user_id',
                'approved_date_column' => 'fecha_almacen',
                'rejection_stage' => 'almacen',
            ];
        }

        if ($user->hasRole(SolicitudCompraFlow::APPROVER_ROLES)) {
            $configs[] = [
                'label' => 'Aprobador',
                'user_column' => 'aprobado_por_user_id',
                'approved_date_column' => 'fecha_aprobador',
                'rejection_stage' => 'aprobador',
            ];
        }

        if ($user->hasRole(SolicitudCompraFlow::PROCUREMENT_ROLES)) {
            $configs[] = [
                'label' => 'Procura',
                'user_column' => 'recibido_por_user_id',
                'approved_date_column' => 'fecha_receptor',
                'rejection_stage' => 'procura',
            ];
        }

        return $configs;
    }

    /**
     * @param array{user_column: string, approved_date_column: string} $config
     */
    private function approvedCount(array $config, int $userId): int
    {
        return SolicitudCompra::query()
            ->where($config['user_column'], $userId)
            ->whereNotNull($config['approved_date_column'])
            ->count();
    }

    /**
     * @param array{user_column: string, rejection_stage: string} $config
     */
    private function rejectedCount(array $config, int $userId): int
    {
        return SolicitudCompra::query()
            ->where($config['user_column'], $userId)
            ->where('estado', 'RECHAZADA')
            ->where('rechazo_etapa', $config['rejection_stage'])
            ->count();
    }

    /**
     * @param array{user_column: string, approved_date_column: string} $config
     */
    private function closedCount(array $config, int $userId): int
    {
        return SolicitudCompra::query()
            ->where($config['user_column'], $userId)
            ->whereNotNull($config['approved_date_column'])
            ->where('estado', 'CERRADA')
            ->count();
    }
}
