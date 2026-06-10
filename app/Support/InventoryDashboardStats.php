<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryDashboardStats
{
    public static function getSummary(?array $filters = null): array
    {
        $query = Product::query();

        self::applyProductDateFilters($query, $filters);

        $summary = $query
            ->selectRaw('COUNT(*) as item_count')
            ->selectRaw('COALESCE(SUM(stock_actual), 0) as quantity_total')
            ->selectRaw('COALESCE(SUM(stock_actual * precio_unitario), 0) as assets_total')
            ->first();

        return [
            'item_count' => (int) ($summary->item_count ?? 0),
            'quantity_total' => (int) ($summary->quantity_total ?? 0),
            'assets_total' => (float) ($summary->assets_total ?? 0),
        ];
    }

    public static function getQuantityByCategory(?array $filters = null, int $limit = 10): Collection
    {
        $query = DB::table('products')
            ->join('subcategories', 'subcategories.id', '=', 'products.subcategory_id')
            ->join('categories', 'categories.id', '=', 'subcategories.category_id')
            ->selectRaw("categories.name as label, COALESCE(SUM(products.stock_actual), 0) as total")
            ->groupBy('categories.name')
            ->orderByDesc('total');

        self::applyProductDateFilters($query, $filters, 'products.fecha_adquisicion');

        return self::limitWithOthers($query->get(), $limit);
    }

    public static function getConsumptionByCategory(?array $filters = null, int $limit = 10): Collection
    {
        $query = DB::table('movement_items')
            ->join('inventory_movements', 'inventory_movements.id', '=', 'movement_items.movement_id')
            ->join('products', 'products.id', '=', 'movement_items.product_id')
            ->join('subcategories', 'subcategories.id', '=', 'products.subcategory_id')
            ->join('categories', 'categories.id', '=', 'subcategories.category_id')
            ->where('inventory_movements.tipo', 'salida')
            ->selectRaw("categories.name as label, COALESCE(SUM(movement_items.cantidad), 0) as total")
            ->groupBy('categories.name')
            ->orderByDesc('total');

        self::applyMovementDateFilters($query, $filters, 'inventory_movements.fecha');

        return self::limitWithOthers($query->get(), $limit);
    }

    public static function getAssignedByDepartment(?array $filters = null, int $limit = 10): Collection
    {
        $query = DB::table('products')
            ->selectRaw("COALESCE(NULLIF(dpto_responsable, ''), 'SIN RESPONSABLE') as label, COALESCE(SUM(stock_actual), 0) as total")
            ->groupBy('dpto_responsable')
            ->orderByDesc('total');

        self::applyProductDateFilters($query, $filters, 'products.fecha_adquisicion');

        return self::limitWithOthers($query->get(), $limit);
    }

    public static function getConsumptionByDepartment(?array $filters = null, int $limit = 10): Collection
    {
        $departmentExpression = "COALESCE(NULLIF(inventory_movements.dpto_responsable, ''), 'SIN DEPARTAMENTO')";

        $query = DB::table('movement_items')
            ->join('inventory_movements', 'inventory_movements.id', '=', 'movement_items.movement_id')
            ->where('inventory_movements.tipo', 'salida')
            ->selectRaw($departmentExpression . ' as label, COALESCE(SUM(movement_items.cantidad), 0) as total')
            ->groupByRaw($departmentExpression)
            ->orderByDesc('total');

        self::applyMovementDateFilters($query, $filters, 'inventory_movements.fecha');

        return self::limitWithOthers($query->get(), $limit);
    }

    private static function applyProductDateFilters(object $query, ?array $filters = null, string $column = 'fecha_adquisicion'): void
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

    private static function applyMovementDateFilters(QueryBuilder $query, ?array $filters = null, string $column = 'inventory_movements.fecha'): void
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
            return $rows->map(fn ($row) => (object) [
                'label' => $row->label,
                'total' => (int) $row->total,
            ]);
        }

        $topRows = $rows->take($limit)->map(fn ($row) => (object) [
            'label' => $row->label,
            'total' => (int) $row->total,
        ]);

        $othersTotal = (int) $rows->slice($limit)->sum('total');

        return $topRows->push((object) [
            'label' => 'Otros',
            'total' => $othersTotal,
        ]);
    }
}