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
        $tipoDocumento = (string) ($ordenCompra->tipo_documento_recepcion ?? '');
        $downloadName = $this->buildDownloadName($ordenCompra, $path, $tipoDocumento);

        if ($path === '') {
            abort(404, 'No hay documento de recepcion disponible para esta ODC.');
        }

        $disk = $this->resolveReceptionDisk($tipoDocumento);

        if (Storage::disk($disk)->exists($path)) {
            if (request()->boolean('inline')) {
                return Storage::disk($disk)->response($path, $downloadName, [
                    'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
                ]);
            }

            return Storage::disk($disk)->download($path, $downloadName);
        }

        // Fallback para documentos historicos guardados en public.
        if (Storage::disk('public')->exists($path)) {
            if (request()->boolean('inline')) {
                return Storage::disk('public')->response($path, $downloadName, [
                    'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
                ]);
            }

            return Storage::disk('public')->download($path, $downloadName);
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

    private function buildDownloadName(OrdenCompra $ordenCompra, string $path, string $tipoDocumento): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $extension = $extension !== '' ? strtolower($extension) : 'bin';

        $correlativo = (string) ($ordenCompra->correlativo_odc ?? $ordenCompra->id ?? 'sin-odc');
        $correlativo = preg_replace('/[^A-Za-z0-9_-]+/', '-', $correlativo) ?: 'sin-odc';

        $prefix = strtoupper(trim($tipoDocumento)) === 'NOTA'
            ? 'nota-de-entrega'
            : 'factura';

        return $prefix . '-' . $correlativo . '.' . $extension;
    }
}
