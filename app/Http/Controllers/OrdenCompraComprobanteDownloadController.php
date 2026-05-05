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
        $downloadName = $this->buildDownloadName($ordenCompra, $path);

        if ($path === '') {
            abort(404, 'No hay comprobante disponible para esta ODC.');
        }

        if (Storage::disk('odc_comprobantes')->exists($path)) {
            if (request()->boolean('inline')) {
                return Storage::disk('odc_comprobantes')->response($path, $downloadName, [
                    'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
                ]);
            }

            return Storage::disk('odc_comprobantes')->download($path, $downloadName);
        }

        // Fallback para comprobantes antiguos guardados en el disco public.
        if (Storage::disk('public')->exists($path)) {
            if (request()->boolean('inline')) {
                return Storage::disk('public')->response($path, $downloadName, [
                    'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
                ]);
            }

            return Storage::disk('public')->download($path, $downloadName);
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

    private function buildDownloadName(OrdenCompra $ordenCompra, string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $extension = $extension !== '' ? strtolower($extension) : 'bin';

        $correlativo = (string) ($ordenCompra->correlativo_odc ?? $ordenCompra->id ?? 'sin-odc');
        $correlativo = preg_replace('/[^A-Za-z0-9_-]+/', '-', $correlativo) ?: 'sin-odc';

        return 'comprobante-de-pago-' . $correlativo . '.' . $extension;
    }
}
