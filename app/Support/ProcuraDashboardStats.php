<?php

namespace App\Support;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcuraDashboardStats
{
    public static function getSummary(?array $filters = null): array
    {
        return [
            'average_request_to_summary_days' => self::getAverageRequestToSummaryDays($filters),
            'average_summary_to_order_days' => self::getAverageSummaryToOrderDays($filters),
            'average_summaries_per_request' => self::getAverageSummariesPerRequest($filters),
            'average_orders_per_summary' => self::getAverageOrdersPerSummary($filters),
        ];
    }

    public static function getAverageRequestToSummaryDays(?array $filters = null): float
    {
        $rows = self::requestToFirstSummaryBaseQuery($filters)->get();

        if ($rows->isEmpty()) {
            return 0.0;
        }

        return round((float) $rows->avg('days_to_summary'), 2);
    }

    public static function getRequestToSummaryTrend(?array $filters = null): Collection
    {
        $monthKeyExpression = self::monthKeyExpression('metrics.first_summary_at');
        $monthLabelExpression = self::monthLabelExpression('metrics.first_summary_at');

        return DB::query()
            ->fromSub(self::requestToFirstSummaryBaseQuery($filters), 'metrics')
            ->selectRaw("{$monthKeyExpression} as month_key")
            ->selectRaw("{$monthLabelExpression} as label")
            ->selectRaw('AVG(metrics.days_to_summary) as total')
            ->groupByRaw("{$monthKeyExpression}, {$monthLabelExpression}")
            ->orderByRaw($monthKeyExpression)
            ->get()
            ->map(fn ($row): object => (object) [
                'label' => $row->label,
                'total' => round((float) ($row->total ?? 0), 2),
            ]);
    }

    public static function getAverageSummaryToOrderDays(?array $filters = null): float
    {
        $rows = self::summaryToFirstOrderBaseQuery($filters)->get();

        if ($rows->isEmpty()) {
            return 0.0;
        }

        return round((float) $rows->avg('days_to_order'), 2);
    }

    public static function getSummaryToOrderByAnalyst(?array $filters = null, int $limit = 8): Collection
    {
        $rows = DB::query()
            ->fromSub(self::summaryToFirstOrderBaseQuery($filters), 'metrics')
            ->selectRaw("COALESCE(NULLIF(metrics.analyst_name, ''), 'SIN ANALISTA') as label")
            ->selectRaw('AVG(metrics.days_to_order) as total')
            ->groupBy('metrics.analyst_name')
            ->orderBy('total')
            ->get();

        return self::limitWithOthers($rows, $limit);
    }

    public static function getSummariesPerRequest(?array $filters = null, int $limit = 10): Collection
    {
        $requestLabelExpression = self::prefixedPaddedIdExpression('solicitud_compras.id', 'SC-');

        $rows = DB::table('solicitud_compras')
            ->join('sumarios', 'sumarios.solicitud_compra_id', '=', 'solicitud_compras.id')
            ->selectRaw("{$requestLabelExpression} as label")
            ->selectRaw('COUNT(sumarios.id) as total')
            ->groupBy('solicitud_compras.id')
            ->orderByDesc('total');

        self::applyDateFiltersToBaseQuery($rows, $filters, 'sumarios.created_at');

        return self::limitWithOthers($rows->get(), $limit);
    }

    public static function getAverageSummariesPerRequest(?array $filters = null): float
    {
        $rows = DB::table('solicitud_compras')
            ->leftJoin('sumarios', 'sumarios.solicitud_compra_id', '=', 'solicitud_compras.id')
            ->selectRaw('solicitud_compras.id as request_id')
            ->selectRaw('COUNT(sumarios.id) as total')
            ->groupBy('solicitud_compras.id');

        self::applyDateFiltersToBaseQuery($rows, $filters, 'solicitud_compras.created_at');

        $data = $rows->get();

        if ($data->isEmpty()) {
            return 0.0;
        }

        return round((float) $data->avg('total'), 2);
    }

    public static function getOrdersPerSummary(?array $filters = null, int $limit = 10): Collection
    {
        $summaryFallbackLabelExpression = self::prefixedPaddedIdExpression('sumarios.id', 'SUM-');

        $rows = DB::table('sumarios')
            ->join('ordenes_compra', 'ordenes_compra.sumario_id', '=', 'sumarios.id')
            ->selectRaw("COALESCE(NULLIF(sumarios.correlativo_sdc, ''), {$summaryFallbackLabelExpression}) as label")
            ->selectRaw('COUNT(ordenes_compra.id) as total')
            ->groupBy('sumarios.id', 'sumarios.correlativo_sdc')
            ->orderByDesc('total');

        self::applyDateFiltersToBaseQuery($rows, $filters, 'ordenes_compra.created_at');

        return self::limitWithOthers($rows->get(), $limit);
    }

    public static function getAverageOrdersPerSummary(?array $filters = null): float
    {
        $rows = DB::table('sumarios')
            ->leftJoin('ordenes_compra', 'ordenes_compra.sumario_id', '=', 'sumarios.id')
            ->selectRaw('sumarios.id as summary_id')
            ->selectRaw('COUNT(ordenes_compra.id) as total')
            ->groupBy('sumarios.id');

        self::applyDateFiltersToBaseQuery($rows, $filters, 'sumarios.created_at');

        $data = $rows->get();

        if ($data->isEmpty()) {
            return 0.0;
        }

        return round((float) $data->avg('total'), 2);
    }

    private static function requestToFirstSummaryBaseQuery(?array $filters = null): QueryBuilder
    {
        $daysExpression = self::daysBetweenExpression('solicitud_compras.created_at', 'first_sumarios.first_summary_at');

        $query = DB::table('solicitud_compras')
            ->joinSub(
                DB::table('sumarios')
                    ->select('solicitud_compra_id')
                    ->selectRaw('MIN(created_at) as first_summary_at')
                    ->groupBy('solicitud_compra_id'),
                'first_sumarios',
                'first_sumarios.solicitud_compra_id',
                '=',
                'solicitud_compras.id'
            )
            ->selectRaw('solicitud_compras.created_at as solicitud_created_at')
            ->selectRaw('first_sumarios.first_summary_at as first_summary_at')
            ->selectRaw("{$daysExpression} as days_to_summary");

        self::applyDateFiltersToBaseQuery($query, $filters, 'first_sumarios.first_summary_at');

        return $query;
    }

    private static function summaryToFirstOrderBaseQuery(?array $filters = null): QueryBuilder
    {
        $daysExpression = self::daysBetweenExpression('sumarios.created_at', 'first_orders.first_order_at');

        $query = DB::table('sumarios')
            ->joinSub(
                DB::table('ordenes_compra')
                    ->select('sumario_id')
                    ->selectRaw('MIN(created_at) as first_order_at')
                    ->groupBy('sumario_id'),
                'first_orders',
                'first_orders.sumario_id',
                '=',
                'sumarios.id'
            )
            ->leftJoin('users', 'users.id', '=', 'sumarios.elaborado_por_user_id')
            ->selectRaw('sumarios.created_at as sumario_created_at')
            ->selectRaw('first_orders.first_order_at as first_order_at')
            ->selectRaw('users.name as analyst_name')
            ->selectRaw("{$daysExpression} as days_to_order");

        self::applyDateFiltersToBaseQuery($query, $filters, 'first_orders.first_order_at');

        return $query;
    }

    private static function applyDateFiltersToBaseQuery(QueryBuilder $query, ?array $filters = null, string $column = 'created_at'): void
    {
        $from = $filters['desde'] ?? null;
        $until = $filters['hasta'] ?? null;

        if ($from) {
            $query->whereDate($column, '>=', $from);
        }

        if ($until) {
            $query->whereDate($column, '<=', $until);
        }
    }

    private static function daysBetweenExpression(string $startColumn, string $endColumn): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "EXTRACT(EPOCH FROM ({$endColumn} - {$startColumn})) / 86400",
            default => "TIMESTAMPDIFF(HOUR, {$startColumn}, {$endColumn}) / 24",
        };
    }

    private static function monthKeyExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private static function monthLabelExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR({$column}, 'MM/YYYY')",
            default => "DATE_FORMAT({$column}, '%m/%Y')",
        };
    }

    private static function prefixedPaddedIdExpression(string $column, string $prefix): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "CONCAT('{$prefix}', LPAD(CAST({$column} AS TEXT), 4, '0'))",
            default => "CONCAT('{$prefix}', LPAD({$column}, 4, '0'))",
        };
    }

    private static function limitWithOthers(Collection $rows, int $limit): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $normalized = $rows->map(fn ($row): object => (object) [
            'label' => (string) $row->label,
            'total' => round((float) $row->total, 2),
        ]);

        if ($normalized->count() <= $limit) {
            return $normalized;
        }

        $topRows = $normalized->take($limit);
        $othersTotal = (float) $normalized->slice($limit)->sum('total');

        return $topRows->push((object) [
            'label' => 'Otros',
            'total' => round($othersTotal, 2),
        ]);
    }
}