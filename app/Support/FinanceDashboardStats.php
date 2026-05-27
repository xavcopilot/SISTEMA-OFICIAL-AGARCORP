<?php

namespace App\Support;

use App\Models\OrdenCompra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceDashboardStats
{
    private const PENDING_PAYMENT_WORKFLOW = 'PENDIENTE_PAGO_FINANZAS';

    private const PAID_WORKFLOWS = [
        'PAGO_REGISTRADO_FINANZAS',
        'PAGADO_Y_EN_TRANSITO',
        'DOCUMENTO_RECEPCION_CARGADO_PROCURA',
        'EN_TRANSICION_ALMACEN',
        'CONFORMIDAD_POR_ITEMS_COMPLETA',
        'FACTURA_ENVIADA_ADMINISTRACION',
        'BACKUP_FACTURA_COMPLETADO',
        'CERRADA_CONFORME',
    ];

    private const FINANCE_PIPELINE_WORKFLOWS = [
        self::PENDING_PAYMENT_WORKFLOW,
        ...self::PAID_WORKFLOWS,
    ];

    public static function getSummary(?array $filters = null): array
    {
        $payments = self::getPaymentSummary($filters);
        $documentation = self::getDocumentationSummary($filters);

        return [
            'paid_orders' => $payments['paid'],
            'pending_orders' => $payments['pending'],
            'loaded_invoices' => $documentation['loaded'],
            'pending_documentation' => $documentation['pending'],
            'average_document_days' => self::getAverageDocumentationClosureDays($filters),
        ];
    }

    public static function getPaymentSummary(?array $filters = null): array
    {
        $query = self::paymentPipelineQuery($filters);

        $pending = (clone $query)
            ->where('workflow_post_compra', self::PENDING_PAYMENT_WORKFLOW)
            ->count();

        $paid = (clone $query)
            ->where(function (Builder $query): Builder {
                return $query
                    ->whereNotNull('pago_registrado_at')
                    ->orWhereIn('workflow_post_compra', self::PAID_WORKFLOWS);
            })
            ->count();

        return [
            'paid' => (int) $paid,
            'pending' => (int) $pending,
            'total' => (int) ($paid + $pending),
        ];
    }

    public static function getDocumentationSummary(?array $filters = null): array
    {
        $query = self::documentationPipelineQuery($filters);

        $loaded = (clone $query)
            ->whereNotNull('factura_cargada_administracion_at')
            ->count();

        $pending = (clone $query)
            ->whereNull('factura_cargada_administracion_at')
            ->count();

        return [
            'loaded' => (int) $loaded,
            'pending' => (int) $pending,
            'total' => (int) ($loaded + $pending),
        ];
    }

    public static function getAverageDocumentationClosureDays(?array $filters = null): float
    {
        $rows = OrdenCompra::query()
            ->whereNotNull('factura_enviada_administracion_at')
            ->whereNotNull('factura_cargada_administracion_at')
            ->when($filters['desde'] ?? null, fn (Builder $query, mixed $from): Builder => $query
                ->whereDate('factura_cargada_administracion_at', '>=', $from))
            ->when($filters['hasta'] ?? null, fn (Builder $query, mixed $until): Builder => $query
                ->whereDate('factura_cargada_administracion_at', '<=', $until))
            ->get([
                'factura_enviada_administracion_at',
                'factura_cargada_administracion_at',
            ]);

        if ($rows->isEmpty()) {
            return 0.0;
        }

        return round((float) $rows->avg(function (OrdenCompra $orden): float {
            if (! $orden->factura_enviada_administracion_at || ! $orden->factura_cargada_administracion_at) {
                return 0.0;
            }

            return round(
                max(0, $orden->factura_enviada_administracion_at->diffInHours($orden->factura_cargada_administracion_at)) / 24,
                2
            );
        }), 2);
    }

    public static function getAverageDocumentationClosureTrend(?array $filters = null): Collection
    {
        $query = DB::table('ordenes_compra')
            ->whereNotNull('factura_enviada_administracion_at')
            ->whereNotNull('factura_cargada_administracion_at')
            ->selectRaw("TO_CHAR(factura_cargada_administracion_at, 'YYYY-MM') as month_key")
            ->selectRaw("TO_CHAR(factura_cargada_administracion_at, 'MM/YYYY') as label")
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (factura_cargada_administracion_at - factura_enviada_administracion_at)) / 3600) / 24 as total')
            ->groupBy('month_key', 'label')
            ->orderBy('month_key');

        self::applyDateFiltersToBaseQuery($query, $filters, 'factura_cargada_administracion_at');

        return $query->get()->map(fn ($row): object => (object) [
            'label' => $row->label,
            'total' => round((float) ($row->total ?? 0), 2),
        ]);
    }

    public static function getPaymentsByProvider(?array $filters = null, int $limit = 8): Collection
    {
        $query = DB::table('ordenes_compra')
            ->leftJoin('proveedores', 'proveedores.id', '=', 'ordenes_compra.proveedor_id')
            ->whereNotNull('ordenes_compra.pago_registrado_at')
            ->selectRaw("COALESCE(NULLIF(proveedores.nombre, ''), 'SIN PROVEEDOR') as label")
            ->selectRaw('COALESCE(SUM(COALESCE(ordenes_compra.monto_pagado, ordenes_compra.total_general, 0)), 0) as total')
            ->groupBy('proveedores.nombre')
            ->orderByDesc('total');

        self::applyDateFiltersToBaseQuery($query, $filters, 'ordenes_compra.pago_registrado_at');

        return self::limitWithOthers($query->get(), $limit);
    }

    private static function paymentPipelineQuery(?array $filters = null): Builder
    {
        $query = OrdenCompra::query()
            ->where(function (Builder $query): Builder {
                return $query
                    ->whereIn('workflow_post_compra', self::FINANCE_PIPELINE_WORKFLOWS)
                    ->orWhereNotNull('pago_registrado_at');
            });

        self::applyDateFilters($query, $filters, 'created_at');

        return $query;
    }

    private static function documentationPipelineQuery(?array $filters = null): Builder
    {
        $query = OrdenCompra::query()
            ->where(function (Builder $query): Builder {
                return $query
                    ->whereIn('workflow_post_compra', self::PAID_WORKFLOWS)
                    ->orWhereNotNull('factura_enviada_administracion_at')
                    ->orWhereNotNull('factura_cargada_administracion_at');
            });

        self::applyDateFilters($query, $filters, 'created_at');

        return $query;
    }

    private static function applyDateFilters(Builder $query, ?array $filters = null, string $column = 'created_at'): void
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

    private static function limitWithOthers(Collection $rows, int $limit): Collection
    {
        if ($rows->count() <= $limit) {
            return $rows->map(fn ($row): object => (object) [
                'label' => (string) $row->label,
                'total' => round((float) $row->total, 2),
            ]);
        }

        $topRows = $rows->take($limit)->map(fn ($row): object => (object) [
            'label' => (string) $row->label,
            'total' => round((float) $row->total, 2),
        ]);

        $othersTotal = (float) $rows->slice($limit)->sum('total');

        return $topRows->push((object) [
            'label' => 'Otros',
            'total' => round($othersTotal, 2),
        ]);
    }
}