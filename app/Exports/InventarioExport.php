<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class InventarioExport implements FromCollection, WithHeadings, WithEvents
{
    public function collection(): Collection
    {
        $products = Product::query()
            ->with(['subcategory.category'])
            ->withSum([
                'movementItems as entradas_acumuladas' => fn ($movementItems) => $movementItems
                    ->whereHas('movement', fn ($movement) => $movement->whereIn('tipo', ['ingreso', 'entrada'])),
            ], 'cantidad')
            ->withSum([
                'movementItems as salidas_acumuladas' => fn ($movementItems) => $movementItems
                    ->whereHas('movement', fn ($movement) => $movement->where('tipo', 'salida')),
            ], 'cantidad')
            ->orderBy('descripcion')
            ->get();

        return $products->map(function (Product $product): array {
            $stockActual = (int) $product->stock_actual;
            $stockMinimo = (int) $product->stock_minimo;
            $status = $stockActual === 0
                ? 'Critico'
                : ($stockActual <= $stockMinimo ? 'Bajo' : 'Optimo');

            $entradas = (int) ($product->entradas_acumuladas ?? 0);
            $salidas = (int) ($product->salidas_acumuladas ?? 0);
            $precioUnitario = (float) $product->precio_unitario;

            return [
                $product->sku,
                $product->descripcion,
                $product->marca,
                $product->subcategory?->category?->name,
                $product->subcategory?->name,
                $product->estado,
                $product->medida,
                $product->serial,
                'ALMACEN',
                $product->ubicacion,
                $product->dpto_responsable,
                $stockMinimo,
                $status,
                $stockActual,
                $entradas,
                $salidas,
                number_format($precioUnitario, 2, '.', ''),
                number_format($stockActual * $precioUnitario, 2, '.', ''),
                optional($product->fecha_adquisicion)->format('d/m/Y'),
                optional($product->fecha_ultima_entrada)->format('d/m/Y'),
                optional($product->fecha_ultima_salida)->format('d/m/Y'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Producto',
            'Marca',
            'Categoria',
            'Subcatg',
            'Estado',
            'Medida',
            'Serial',
            'Almacen',
            'Ubicacion',
            'Dpto Responsable',
            'Min',
            'Status',
            'Cant. Total',
            'Entradas',
            'Salidas',
            'P.Unitario',
            'P.Total',
            'Fecha de Adquisicion',
            'Fecha de Ultima Entrada',
            'Fecha de Ultima Salida',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                foreach (range('A', 'U') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }
}