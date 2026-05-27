<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdenCompraDocumentoRecepcionDownloadController extends Controller
{
    public function __invoke(OrdenCompra $ordenCompra): StreamedResponse
    {
        $tipoDocumento = $this->resolveRequestedDocumentType($ordenCompra, (string) request('documento', ''));
        $storedPath = $this->storedPathForDocument($ordenCompra, $tipoDocumento);

        if ($storedPath === '') {
            abort(404, 'No hay documento de recepcion disponible para esta ODC.');
        }

        [$disk, $path] = $this->resolveReceptionLocation($storedPath, $tipoDocumento);

        if ($disk === null || $path === null) {
            abort(404, 'No se encontro el documento de recepcion.');
        }

        $downloadName = $this->buildDownloadName($ordenCompra, $path, $tipoDocumento);

        if (request()->boolean('inline')) {
            return Storage::disk($disk)->response($path, $downloadName, [
                'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
            ]);
        }

        return Storage::disk($disk)->download($path, $downloadName);
    }

    private function resolveRequestedDocumentType(OrdenCompra $ordenCompra, string $requestedDocument): string
    {
        $requestedDocument = strtoupper(trim($requestedDocument));

        if (in_array($requestedDocument, ['FACTURA', 'NOTA'], true)) {
            return $requestedDocument;
        }

        if ((string) ($ordenCompra->tipo_documento_recepcion ?? '') === 'NOTA' && $ordenCompra->hasNotaEntregaRecepcion()) {
            return 'NOTA';
        }

        if ($ordenCompra->hasFacturaRecepcion()) {
            return 'FACTURA';
        }

        return 'NOTA';
    }

    private function storedPathForDocument(OrdenCompra $ordenCompra, string $tipoDocumento): string
    {
        $path = $tipoDocumento === 'NOTA'
            ? $ordenCompra->notaEntregaRecepcionPath()
            : $ordenCompra->facturaRecepcionPath();

        return $this->normalizePath((string) ($path ?? ''));
    }

    private function resolveReceptionDisk(string $tipoDocumento): string
    {
        return strtoupper(trim($tipoDocumento)) === 'NOTA'
            ? 'odc_notas_entrega'
            : 'odc_facturas';
    }

    private function resolveReceptionLocation(string $storedPath, string $tipoDocumento): array
    {
        $normalized = $this->normalizePath($storedPath);

        if ($normalized === '') {
            return [null, null];
        }

        $disk = $this->resolveReceptionDisk($tipoDocumento);
        $folderHint = $disk === 'odc_notas_entrega' ? 'notas-entrega/' : 'facturas/';

        $candidates = array_values(array_unique(array_filter([
            $normalized,
            basename($normalized),
            $this->stripPublicOrderPrefix($normalized, $folderHint),
            $this->stripPublicOrderPrefix($normalized, ''),
        ])));

        foreach ($candidates as $candidate) {
            if (Storage::disk($disk)->exists($candidate)) {
                return [$disk, $candidate];
            }
        }

        // Fallback para documentos historicos guardados en public.
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
