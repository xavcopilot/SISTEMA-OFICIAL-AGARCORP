<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdenCompraComprobanteDownloadController extends Controller
{
    public function __invoke(OrdenCompra $ordenCompra): StreamedResponse
    {
        $path = $this->normalizePath((string) ($ordenCompra->comprobante_pago_path ?? ''));

        if ($path === '') {
            abort(404, 'No hay comprobante disponible para esta ODC.');
        }

        if (Storage::disk('odc_comprobantes')->exists($path)) {
            return Storage::disk('odc_comprobantes')->download($path, basename($path));
        }

        // Fallback para comprobantes antiguos guardados en el disco public.
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, basename($path));
        }

        abort(404, 'No se encontro el archivo del comprobante.');
    }

    private function normalizePath(string $path): string
    {
        $normalized = ltrim(trim($path), '/\\');

        if ($normalized === '' || str_contains($normalized, '..')) {
            return '';
        }

        return $normalized;
    }
}
