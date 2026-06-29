<?php

namespace App\Support;

use App\Models\Sumario;
use App\Models\SumarioProveedorDocumento;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SumarioProviderDocumentManager
{
    public const DISK = 'sumario_propuestas';

    public static function disk(): string
    {
        self::ensureDiskConfiguration();

        return self::DISK;
    }

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

    public static function validateRequiredDocuments(array $data, bool $mustExistOnDisk = true): ?string
    {
        if (! self::hasPersistenceTable()) {
            return 'La funcionalidad de propuestas requiere ejecutar la migracion pendiente del modulo de sumarios.';
        }

        $disk = $mustExistOnDisk ? Storage::disk(self::disk()) : null;

        $missingProviders = [];
        $missingFilesByProvider = [];

        foreach (self::providerFieldMap() as $slot => $field) {
            $providerName = self::providerNameForSlot($data, $slot);

            if ($providerName === '') {
                continue;
            }

            $paths = self::normalizePaths($data[$field] ?? []);

            if (! self::hasDocumentValue($paths)) {
                $missingProviders[] = $providerName;
                continue;
            }

            if ($mustExistOnDisk && $disk !== null) {
                $missingPhysicalFiles = collect($paths)
                    ->filter(fn (string $path): bool => ! self::pathExistsInDiskOrTemp($disk, $path))
                    ->values()
                    ->all();

                if ($missingPhysicalFiles !== []) {
                    $missingFilesByProvider[] = $providerName;
                }
            }
        }

        if ($missingProviders !== []) {
            return 'Debes cargar al menos una propuesta para: ' . implode(', ', $missingProviders) . '.';
        }

        if ($missingFilesByProvider !== []) {
            return 'No se encontraron en disco algunos archivos de propuesta para: '
                . implode(', ', $missingFilesByProvider)
                . '. Vuelve a subir esos archivos antes de enviar.';
        }

        return null;
    }

    public static function syncForSumario(Sumario $sumario, array $data, callable $providerIdResolver): void
    {
        if (! self::hasPersistenceTable()) {
            return;
        }

        $disk = Storage::disk(self::disk());

        $sumario->loadMissing('providerDocuments');

        foreach (self::providerFieldMap() as $slot => $field) {
            $providerName = self::providerNameForSlot($data, $slot);
            $providerId = $providerName !== '' ? $providerIdResolver($providerName) : null;
            $desiredPaths = self::normalizeAndPersistPaths($data[$field] ?? [], $data, $slot, $disk);

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

                self::deleteFileIfPresent($disk, (string) $document->archivo_path);
                $document->delete();
            }

            foreach ($desiredPaths as $path) {
                if ($existingByPath->has($path)) {
                    continue;
                }

                $mimeType = $disk->exists($path)
                    ? ($disk->mimeType($path) ?: null)
                    : null;
                $size = $disk->exists($path)
                    ? ($disk->size($path) ?: null)
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

    /**
     * @return array<int, string>
     */
    private static function normalizeAndPersistPaths(mixed $paths, array $data, int $providerSlot, $disk): array
    {
        if (! is_array($paths)) {
            return [];
        }

        $normalized = [];

        foreach ($paths as $path) {
            if (is_object($path)) {
                $storedFromObject = self::storeUploadedObject($path, $data, $providerSlot, $disk);

                if ($storedFromObject !== null) {
                    $normalized[] = $storedFromObject;
                }

                continue;
            }

            $candidate = trim((string) $path);

            if ($candidate === '' || str_contains($candidate, '..')) {
                continue;
            }

            if ($disk->exists($candidate)) {
                $normalized[] = $candidate;
                continue;
            }

            $storedFromTemp = self::storeTempPath($candidate, $data, $providerSlot, $disk);

            if ($storedFromTemp !== null) {
                $normalized[] = $storedFromTemp;
                continue;
            }

            $trimmedCandidate = ltrim($candidate, '/\\');

            if ($trimmedCandidate !== '' && $disk->exists($trimmedCandidate)) {
                $normalized[] = $trimmedCandidate;
            }
        }

        return collect($normalized)
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

    private static function deleteFileIfPresent($disk, string $path): void
    {
        if ($path === '') {
            return;
        }

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private static function pathExistsInDiskOrTemp($disk, string $path): bool
    {
        if ($disk->exists($path)) {
            return true;
        }

        return self::resolveTempSourcePath($path) !== null;
    }

    private static function resolveTempSourcePath(string $path): ?string
    {
        $candidate = trim($path);

        if ($candidate === '') {
            return null;
        }

        if (is_file($candidate)) {
            return $candidate;
        }

        $prefixedStoragePath = storage_path('app/private/' . ltrim($candidate, '/\\'));

        if (is_file($prefixedStoragePath)) {
            return $prefixedStoragePath;
        }

        if (str_starts_with($candidate, 'livewire-tmp/')) {
            $livewirePath = storage_path('app/private/' . $candidate);

            if (is_file($livewirePath)) {
                return $livewirePath;
            }
        }

        return null;
    }

    private static function storeUploadedObject(object $file, array $data, int $providerSlot, $disk): ?string
    {
        if (method_exists($file, 'storeAs')) {
            $stored = $file->storeAs('', self::buildStoredFileName($file, $data, $providerSlot), self::disk());

            return is_string($stored) && $stored !== '' ? $stored : null;
        }

        if (! method_exists($file, 'getRealPath')) {
            return null;
        }

        $realPath = $file->getRealPath();

        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            return null;
        }

        $storedName = self::buildStoredFileName($file, $data, $providerSlot);
        $stream = fopen($realPath, 'r');

        if (! is_resource($stream)) {
            return null;
        }

        try {
            $disk->writeStream($storedName, $stream);
        } finally {
            fclose($stream);
        }

        return $storedName;
    }

    private static function storeTempPath(string $path, array $data, int $providerSlot, $disk): ?string
    {
        $sourcePath = self::resolveTempSourcePath($path);

        if ($sourcePath === null) {
            return null;
        }

        $storedName = self::buildStoredFileNameFromPath($sourcePath, $data, $providerSlot);
        $stream = fopen($sourcePath, 'r');

        if (! is_resource($stream)) {
            return null;
        }

        try {
            $disk->writeStream($storedName, $stream);
        } finally {
            fclose($stream);
        }

        return $storedName;
    }

    private static function buildStoredFileName(object $file, array $data, int $providerSlot): string
    {
        $originalName = method_exists($file, 'getClientOriginalName')
            ? (string) $file->getClientOriginalName()
            : 'archivo';

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' && method_exists($file, 'getClientOriginalExtension')) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
        }

        return self::buildFileName($data, $providerSlot, $originalName, $extension);
    }

    private static function buildStoredFileNameFromPath(string $sourcePath, array $data, int $providerSlot): string
    {
        $originalName = basename($sourcePath);
        $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));

        if ($extension === '') {
            $mime = @mime_content_type($sourcePath) ?: '';
            $extension = self::mimeToExtension($mime);
        }

        return self::buildFileName($data, $providerSlot, $originalName, $extension);
    }

    private static function buildFileName(array $data, int $providerSlot, string $originalName, string $extension): string
    {
        $providerNameField = self::providerNameFieldMap()[$providerSlot] ?? null;
        $providerName = $providerNameField !== null ? trim((string) ($data[$providerNameField] ?? '')) : '';

        $providerSlug = Str::slug($providerName !== '' ? $providerName : ('proveedor-' . $providerSlot));
        $correlativo = trim((string) ($data['correlativo_sdc'] ?? 'sumario'));
        $correlativoSlug = Str::upper(Str::slug($correlativo !== '' ? $correlativo : 'sumario', '-'));
        $baseName = trim((string) pathinfo($originalName, PATHINFO_FILENAME));
        $baseNameSlug = Str::slug($baseName !== '' ? $baseName : 'archivo');
        $safeExtension = $extension !== '' ? $extension : 'bin';

        return $correlativoSlug
            . '_prov-' . $providerSlot
            . '_' . $providerSlug
            . '_' . $baseNameSlug
            . '_' . now()->format('YmdHis')
            . '_' . Str::lower(Str::random(6))
            . '.' . $safeExtension;
    }

    private static function mimeToExtension(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }

    private static function ensureDiskConfiguration(): void
    {
        $diskConfig = config('filesystems.disks.' . self::DISK);

        if (! is_array($diskConfig) || blank($diskConfig['driver'] ?? null)) {
            config()->set('filesystems.disks.' . self::DISK, [
                'driver' => 'local',
                'root' => base_path('Propuestas-Sumario'),
                'throw' => true,
                'report' => true,
            ]);
        }
    }
}