<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdenCompraDocumentoRecepcionDownloadController extends Controller
{
    public function __invoke(OrdenCompra $ordenCompra): StreamedResponse
    {
        $path = $this->normalizePath((string) ($ordenCompra->factura_path ?? ''));

        if ($path === '') {
            abort(404, 'No hay documento de recepcion disponible para esta ODC.');
        }

        $disk = $this->resolveReceptionDisk((string) ($ordenCompra->tipo_documento_recepcion ?? ''));

        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->download($path, basename($path));
        }

        // Fallback para documentos historicos guardados en public.
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, basename($path));
        }

        abort(404, 'No se encontro el documento de recepcion.');
    }

    private function resolveReceptionDisk(string $tipoDocumento): string
    {
        return strtoupper(trim($tipoDocumento)) === 'NOTA'
            ? 'odc_notas_entrega'
            : 'odc_facturas';
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
