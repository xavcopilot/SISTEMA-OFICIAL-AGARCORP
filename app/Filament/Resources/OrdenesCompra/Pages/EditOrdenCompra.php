<?php

namespace App\Filament\Resources\OrdenesCompra\Pages;

use App\Filament\Resources\OrdenesCompra\OrdenCompraResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditOrdenCompra extends EditRecord
{
    protected static string $resource = OrdenCompraResource::class;

    protected Width | string | null $maxWidth = Width::Full;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $subTotal = round((float) ($this->record->items()->sum('precio_total') ?? 0), 2);
        $iva = round($subTotal * 0.16, 2);
        $montoExento = round((float) ($data['monto_exento'] ?? 0), 2);
        $gastosAdicionales = round((float) ($data['gastos_adicionales'] ?? 0), 2);

        $data['sub_total'] = $subTotal;
        $data['iva_16'] = $iva;
        $data['total_general'] = round($subTotal + $iva + $montoExento + $gastosAdicionales, 2);

        return $data;
    }
}
