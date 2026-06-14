<?php

namespace App\Support;

use App\Models\Sumario;
use App\Models\SumarioProveedorDocumento;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SumarioProviderDocumentManager
{
    public const DISK = 'sumario_propuestas';

    /**
     * @return array<int, string>
     */
    public static function providerFieldMap(): array
    {
        return [
            1 => 'propuestas_proveedor_1_paths',
            2 => 'propuestas_proveedor_2_paths',
            3 => 'propuestas_proveedor_3_paths',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function providerNameFieldMap(): array
    {
        return [
            1 => 'proveedor_a_nombre',
            2 => 'proveedor_b_nombre',
            3 => 'proveedor_c_nombre',
        ];
    }

    public static function validateRequiredDocuments(array $data): ?string
    {
        if (! self::hasPersistenceTable()) {
            return 'La funcionalidad de propuestas requiere ejecutar la migracion pendiente del modulo de sumarios.';
        }

        $missingProviders = [];

        foreach (self::providerFieldMap() as $slot => $field) {
            $providerName = self::providerNameForSlot($data, $slot);

            if ($providerName === '') {
                continue;
            }

            if (! self::hasDocumentValue($data[$field] ?? [])) {
                $missingProviders[] = $providerName;
            }
        }

        if ($missingProviders === []) {
            return null;
        }

        return 'Debes cargar al menos una propuesta para: ' . implode(', ', $missingProviders) . '.';
    }

    public static function syncForSumario(Sumario $sumario, array $data, callable $providerIdResolver): void
    {
        if (! self::hasPersistenceTable()) {
            return;
        }

        $sumario->loadMissing('providerDocuments');

        foreach (self::providerFieldMap() as $slot => $field) {
            $providerName = self::providerNameForSlot($data, $slot);
            $providerId = $providerName !== '' ? $providerIdResolver($providerName) : null;
            $desiredPaths = self::normalizePaths($data[$field] ?? []);

            $existingDocuments = $sumario->providerDocuments
                ->where('opcion_numero', $slot)
                ->values();

            $existingByPath = $existingDocuments->keyBy('archivo_path');

            foreach ($existingDocuments as $document) {
                if (in_array((string) $document->archivo_path, $desiredPaths, true)) {
                    $document->forceFill([
                        'proveedor_id' => $providerId,
                        'proveedor_nombre_snapshot' => $providerName,
                    ])->save();

                    continue;
                }

                self::deleteFileIfPresent((string) $document->archivo_path);
                $document->delete();
            }

            foreach ($desiredPaths as $path) {
                if ($existingByPath->has($path)) {
                    continue;
                }

                $mimeType = Storage::disk(self::DISK)->exists($path)
                    ? (Storage::disk(self::DISK)->mimeType($path) ?: null)
                    : null;
                $size = Storage::disk(self::DISK)->exists($path)
                    ? (Storage::disk(self::DISK)->size($path) ?: null)
                    : null;

                SumarioProveedorDocumento::query()->create([
                    'sumario_id' => $sumario->id,
                    'opcion_numero' => $slot,
                    'proveedor_id' => $providerId,
                    'proveedor_nombre_snapshot' => $providerName,
                    'archivo_path' => $path,
                    'nombre_original' => basename($path),
                    'mime_type' => $mimeType,
                    'tamano_bytes' => $size,
                    'subido_por_user_id' => auth()->id(),
                ]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public static function normalizePaths(mixed $paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        return collect($paths)
            ->map(fn ($path): string => trim((string) $path))
            ->filter(fn (string $path): bool => $path !== '' && ! str_contains($path, '..'))
            ->unique()
            ->values()
            ->all();
    }

    public static function hasPersistenceTable(): bool
    {
        return Schema::hasTable('sumario_proveedor_documentos');
    }

    private static function hasDocumentValue(mixed $paths): bool
    {
        if (! is_array($paths)) {
            return false;
        }

        foreach ($paths as $path) {
            if (is_string($path) && trim($path) !== '') {
                return true;
            }

            if (is_object($path)) {
                return true;
            }
        }

        return false;
    }

    private static function providerNameForSlot(array $data, int $slot): string
    {
        $field = self::providerNameFieldMap()[$slot] ?? null;

        if ($field === null) {
            return '';
        }

        return trim((string) ($data[$field] ?? ''));
    }

    private static function deleteFileIfPresent(string $path): void
    {
        if ($path === '') {
            return;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}