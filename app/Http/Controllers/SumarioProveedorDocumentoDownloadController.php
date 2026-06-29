<?php

namespace App\Http\Controllers;

use App\Models\Sumario;
use App\Models\SumarioProveedorDocumento;
use App\Support\SumarioProviderDocumentManager;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SumarioProveedorDocumentoDownloadController extends Controller
{
    public function __invoke(Sumario $sumario, SumarioProveedorDocumento $documento): StreamedResponse
    {
        if (! $this->canAccess()) {
            abort(403);
        }

        if ((int) $documento->sumario_id !== (int) $sumario->id) {
            abort(404, 'El documento solicitado no pertenece a este sumario.');
        }

        $path = $this->normalizePath((string) ($documento->archivo_path ?? ''));

        $disk = Storage::disk(SumarioProviderDocumentManager::disk());

        if ($path === '' || ! $disk->exists($path)) {
            abort(404, 'No se encontro la propuesta solicitada.');
        }

        $downloadName = $this->downloadName($documento, $path);

        if (request()->boolean('inline')) {
            return $disk->response($path, $downloadName, [
                'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
            ]);
        }

        return $disk->download($path, $downloadName);
    }

    private function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if ($user->hasRole('Procura') || $user->hasRole('Validador Finanzas') || $user->hasRole('Gerencia de Finanzas')) {
            return true;
        }

        return $user->can('ViewAny:Sumario')
            || $user->can('ValidateFinance:Sumario')
            || $user->can('ApprovePayment:Sumario');
    }

    private function normalizePath(string $path): string
    {
        $normalized = ltrim(trim($path), '/\\');

        if ($normalized === '' || str_contains($normalized, '..')) {
            return '';
        }

        return $normalized;
    }

    private function downloadName(SumarioProveedorDocumento $documento, string $path): string
    {
        $originalName = trim((string) ($documento->nombre_original ?? ''));

        if ($originalName !== '') {
            return $originalName;
        }

        $providerName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($documento->proveedor_nombre_snapshot ?? 'proveedor')) ?: 'proveedor';
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return 'propuesta-' . $providerName . ($extension !== '' ? '.' . strtolower($extension) : '');
    }
}