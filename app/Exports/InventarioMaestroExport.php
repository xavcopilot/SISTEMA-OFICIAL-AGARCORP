<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class InventarioMaestroExport implements FromCollection, WithHeadings, WithEvents
{
    public function collection(): Collection
    {
        $products = Product::query()
            ->with(['subcategory.category'])
            ->orderBy('descripcion')
            ->get();

        return $products->map(function (Product $product): array {
            return [
                optional($product->fecha_adquisicion)->format('d/m/Y'),
                $product->cod_ingreso,
                $product->sku,
                $product->descripcion,
                $product->marca,
                $product->subcategory?->category?->name,
                $product->subcategory?->name,
                $product->serial,
                $product->estado,
                $product->medida,
                $product->ubicacion,
                $product->dpto_responsable,
                (int) $product->stock_minimo,
                number_format((float) $product->precio_unitario, 2, '.', ''),
                'ALMACEN',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'FECHA INGRESO',
            'COD INGRESO',
            'SKU',
            'DESCRIPCION',
            'MARCA',
            'CATEGORIA',
            'SUBCATEGORIA',
            'SERIAL',
            'ESTADO',
            'MEDIDA',
            'UBICACION',
            'DPTO RESPONSABLE',
            'RANGO MIN',
            'PRECIO',
            'ALMACEN',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                foreach (range('A', 'O') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }
}