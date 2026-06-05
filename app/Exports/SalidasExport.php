<?php

namespace App\Exports;

use App\Models\MovementItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class SalidasExport implements FromCollection, WithHeadings, WithEvents
{
    public function collection(): Collection
    {
        $items = MovementItem::query()
            ->with([
                'movement',
                'product.subcategory.category',
            ])
            ->whereHas('movement', fn ($movement) => $movement->where('tipo', 'salida'))
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
            $dptoResponsable = $movement?->dpto_responsable_unificado;

            return [
                $movement?->nro_control,
                $fecha?->format('d/m/Y'),
                $fecha?->format('m'),
                $dptoResponsable,
                $movement?->almacenista,
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
                $item->retorna ? 'SI' : 'NO',
                $item->observaciones_item ?: $movement?->comentarios,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'N° Control',
            'Fecha',
            'MES',
            'DPTO RESPONSABLE',
            'QUIEN ENTREGA',
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
            'RETORNA',
            'OBSERVACIONES',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                foreach (range('A', 'Q') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $sheet->getStyle('H')->getAlignment()->setWrapText(true);
                $sheet->getStyle('Q')->getAlignment()->setWrapText(true);
            },
        ];
    }
}