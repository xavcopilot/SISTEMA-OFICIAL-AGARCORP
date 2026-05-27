<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdenCompraComprobanteDownloadController extends Controller
{
    public function __invoke(OrdenCompra $ordenCompra): StreamedResponse
    {
        $storedPath = $this->normalizePath((string) ($ordenCompra->comprobante_pago_path ?? ''));

        if ($storedPath === '') {
            abort(404, 'No hay comprobante disponible para esta ODC.');
        }

        [$disk, $path] = $this->resolveComprobanteLocation($storedPath);

        if ($disk === null || $path === null) {
            abort(404, 'No se encontro el archivo del comprobante.');
        }

        $downloadName = $this->buildDownloadName($ordenCompra, $path);

        if (request()->boolean('inline')) {
            return Storage::disk($disk)->response($path, $downloadName, [
                'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
            ]);
        }

        return Storage::disk($disk)->download($path, $downloadName);
    }

    private function resolveComprobanteLocation(string $storedPath): array
    {
        $normalized = $this->normalizePath($storedPath);

        if ($normalized === '') {
            return [null, null];
        }

        // Preferimos la raiz de Comprobantes-ODC (disco odc_comprobantes).
        $candidates = array_values(array_unique(array_filter([
            $normalized,
            basename($normalized),
            $this->stripPublicOrderPrefix($normalized, 'comprobantes-pago/'),
            $this->stripPublicOrderPrefix($normalized, 'comprobantes/'),
            $this->stripPublicOrderPrefix($normalized, ''),
        ])));

        foreach ($candidates as $candidate) {
            if (Storage::disk('odc_comprobantes')->exists($candidate)) {
                return ['odc_comprobantes', $candidate];
            }
        }

        // Fallback para comprobantes historicos guardados en public.
        foreach ($candidates as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                return ['public', $candidate];
            }
        }

        return [null, null];
    }

    private function stripPublicOrderPrefix(string $path, string $suffix): ?string
    {
        $prefix = 'ordenes-compra/';

        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        $stripped = substr($path, strlen($prefix));

        if ($suffix !== '' && str_starts_with($stripped, $suffix)) {
            return substr($stripped, strlen($suffix));
        }

        return $suffix === '' ? $stripped : null;
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
