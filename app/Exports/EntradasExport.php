<?php

namespace App\Exports;

use App\Models\MovementItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class EntradasExport implements FromCollection, WithHeadings, WithEvents
{
    public function collection(): Collection
    {
        $items = MovementItem::query()
            ->with([
                'movement',
                'product.subcategory.category',
            ])
            ->whereHas('movement', fn ($movement) => $movement->whereIn('tipo', ['ingreso', 'entrada']))
            ->orderByDesc(
                MovementItem::query()
                    ->select('fecha')
                    ->from('inventory_movements')
                    ->whereColumn('inventory_movements.id', 'movement_items.movement_id')
                    ->limit(1)
            )
            ->orderByDesc('id')
            ->get();

        return $items->map(function (MovementItem $item): array {
            $movement = $item->movement;
            $product = $item->product;
            $fecha = $movement?->fecha;

            return [
                $movement?->nro_control,
                $fecha?->format('d/m/Y'),
                $fecha?->format('m'),
                $movement?->nro_solicitud,
                $movement?->orden_compra,
                $movement?->factura_nota,
                $movement?->nro_doc_legal,
                $movement?->proveedor,
                $product?->sku,
                $product?->descripcion,
                $product?->marca,
                $product?->subcategory?->category?->name,
                $product?->subcategory?->name,
                $product?->serial,
                $product?->estado,
                $product?->medida,
                $item->cantidad,
                $product?->ubicacion,
                $product?->dpto_responsable,
                number_format((float) ($item->precio_momento ?? $product?->precio_unitario ?? 0), 2, '.', ''),
                $movement?->comentarios,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'N° Control',
            'Fecha',
            'MES',
            'N° DE SOLICITUD',
            'ORDEN DE COMPRA',
            'F/N/I',
            'N°',
            'PROVEEDOR',
            'SKU',
            'DESCRIPCION',
            'MARCA',
            'CATEGORIA',
            'SUBCAT',
            'SERIAL',
            'ESTADO',
            'MEDIDA',
            'CANT',
            'UBICACION',
            'DPTO RESPONSIBLE',
            'PRECIO',
            'COMENTARIO',
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

                $sheet->getStyle('J')->getAlignment()->setWrapText(true);
                $sheet->getStyle('U')->getAlignment()->setWrapText(true);
            },
        ];
    }
}
