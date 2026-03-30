<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            self::assertNoTrackedSerialDuplicate($product->serial, null);

            if (! empty($product->sku)) {
                self::syncRuleCounterFromProvidedSku((int) $product->subcategory_id, (string) $product->sku);

                return;
            }

            $product->sku = $product->generateSku();
        });

        static::updating(function (Product $product): void {
            if ($product->isDirty('serial')) {
                self::assertNoTrackedSerialDuplicate($product->serial, $product->id);
            }

            if (! $product->isDirty('sku')) {
                return;
            }

            if (! auth()->check() || auth()->user()->hasRole('A.I.T')) {
                return;
            }

            $product->sku = (string) $product->getOriginal('sku');
        });
    }

    protected $fillable = [
        'sku',
        'cod_ingreso',
        'descripcion',
        'marca',
        'subcategory_id',
        'serial',
        'estado',
        'medida',
        'ubicacion',
        'dpto_responsable',
        'stock_minimo',
        'stock_actual',
        'precio_unitario',
        'fecha_adquisicion',
        'fecha_ultima_entrada',
        'fecha_ultima_salida',
        'is_archived',
    ];

    protected $casts = [
        'stock_minimo' => 'integer',
        'stock_actual' => 'integer',
        'precio_unitario' => 'decimal:2',
        'fecha_adquisicion' => 'date',
        'fecha_ultima_entrada' => 'date',
        'fecha_ultima_salida' => 'date',
        'is_archived' => 'boolean',
    ];

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function movementItems(): HasMany
    {
        return $this->hasMany(MovementItem::class);
    }

    public function generateSku(): string
    {
        $categoryId = $this->resolveCategoryId();

        if (! $categoryId) {
            throw new \RuntimeException('No se puede generar SKU sin una categoria asociada.');
        }

        return self::consumeNextSkuForCategory($categoryId);
    }

    public static function previewSkuForCategoryId(?int $categoryId, int $offset = 0): ?string
    {
        if (! $categoryId) {
            return null;
        }

        $categoryName = Category::query()->whereKey($categoryId)->value('name');

        if (! $categoryName) {
            return null;
        }

        $rule = SkuCodeRule::query()->where('category_id', $categoryId)->first();

        $offset = max(0, $offset);

        if ($rule) {
            $prefix = (string) $rule->prefix;
            $nextNumber = max(1, (int) $rule->next_correlative);
            $length = max(4, (int) $rule->number_length);
        } else {
            $prefix = self::categoryPrefix($categoryName);
            $nextNumber = self::inferNextCorrelative($prefix);
            $length = SkuCodeRule::requiredLength($nextNumber);
        }

        $takenLookup = self::takenCorrelativeLookupByPrefix($prefix);
        $candidate = self::findNthAvailableCorrelative($takenLookup, $nextNumber, $offset);
        $displayLength = max($length, SkuCodeRule::requiredLength($candidate));

        return sprintf('%s-%0' . $displayLength . 'd', $prefix, $candidate);
    }

    private function resolveCategoryId(): ?int
    {
        if (! empty($this->subcategory_id)) {
            return (int) Subcategory::query()->whereKey($this->subcategory_id)->value('category_id');
        }

        $subcategory = $this->subcategory()->with('category')->first();

        return $subcategory?->category?->id;
    }

    private static function consumeNextSkuForCategory(int $categoryId): string
    {
        return (string) DB::transaction(function () use ($categoryId): string {
            $rule = self::getOrCreateRuleForCategory($categoryId, true);
            $nextNumber = max(1, (int) $rule->next_correlative);
            $gapNumber = self::findFirstMissingCorrelative($rule->prefix, $nextNumber);
            $candidate = $gapNumber ?? $nextNumber;
            $sku = $rule->formatSku($candidate);

            while (self::query()->where('sku', $sku)->exists()) {
                $candidate++;
                $sku = $rule->formatSku($candidate);
            }

            if ($gapNumber === null) {
                $rule->update([
                    'next_correlative' => $candidate + 1,
                ]);
            }

            return $sku;
        }, 3);
    }

    private static function findFirstMissingCorrelative(string $prefix, int $upToExclusive): ?int
    {
        if ($upToExclusive <= 1) {
            return null;
        }

        $taken = self::query()
            ->where('sku', 'like', $prefix . '-%')
            ->pluck('sku')
            ->map(function (string $sku): ?int {
                if (preg_match('/-(\d+)$/', $sku, $matches) !== 1) {
                    return null;
                }

                return (int) $matches[1];
            })
            ->filter(fn (?int $value): bool => $value !== null && $value > 0)
            ->unique()
            ->values();

        $takenLookup = array_fill_keys($taken->all(), true);

        for ($number = 1; $number < $upToExclusive; $number++) {
            if (! isset($takenLookup[$number])) {
                return $number;
            }
        }

        return null;
    }

    private static function findNthAvailableCorrelative(array $takenLookup, int $nextNumber, int $offset): int
    {
        $remaining = max(0, $offset);

        for ($number = 1; $number < $nextNumber; $number++) {
            if (isset($takenLookup[$number])) {
                continue;
            }

            if ($remaining === 0) {
                return $number;
            }

            $remaining--;
        }

        $number = max(1, $nextNumber);

        while (true) {
            if (! isset($takenLookup[$number])) {
                if ($remaining === 0) {
                    return $number;
                }

                $remaining--;
            }

            $number++;
        }
    }

    private static function takenCorrelativeLookupByPrefix(string $prefix): array
    {
        return self::query()
            ->where('sku', 'like', $prefix . '-%')
            ->pluck('sku')
            ->map(function (string $sku): ?int {
                if (preg_match('/-(\d+)$/', $sku, $matches) !== 1) {
                    return null;
                }

                return (int) $matches[1];
            })
            ->filter(fn (?int $value): bool => $value !== null && $value > 0)
            ->unique()
            ->values()
            ->reduce(function (array $carry, int $value): array {
                $carry[$value] = true;

                return $carry;
            }, []);
    }

    private static function getOrCreateRuleForCategory(int $categoryId, bool $withLock = false): SkuCodeRule
    {
        $query = SkuCodeRule::query()->where('category_id', $categoryId);

        if ($withLock) {
            $query->lockForUpdate();
        }

        $rule = $query->first();

        if ($rule) {
            return $rule;
        }

        $categoryName = (string) Category::query()->whereKey($categoryId)->value('name');
        $prefix = self::categoryPrefix($categoryName);

        return SkuCodeRule::query()->create([
            'category_id' => $categoryId,
            'prefix' => $prefix,
            'next_correlative' => self::inferNextCorrelative($prefix),
            'number_length' => 4,
            'is_active' => true,
            'notes' => null,
        ]);
    }

    private static function inferNextCorrelative(string $prefix): int
    {
        $lastSku = self::query()
            ->where('sku', 'like', $prefix . '-%')
            ->orderByDesc('sku')
            ->value('sku');

        if ($lastSku && preg_match('/-(\d+)$/', (string) $lastSku, $matches) === 1) {
            return ((int) $matches[1]) + 1;
        }

        return 1;
    }

    private static function syncRuleCounterFromProvidedSku(int $subcategoryId, string $sku): void
    {
        if ($subcategoryId <= 0 || trim($sku) === '') {
            return;
        }

        $categoryId = (int) Subcategory::query()->whereKey($subcategoryId)->value('category_id');

        if ($categoryId <= 0) {
            return;
        }

        if (preg_match('/^([A-Z0-9]+)-(\d+)$/', Str::upper(trim($sku)), $matches) !== 1) {
            return;
        }

        DB::transaction(function () use ($categoryId, $matches): void {
            $rule = self::getOrCreateRuleForCategory($categoryId, true);

            if ($rule->prefix !== $matches[1]) {
                return;
            }

            $usedNumber = (int) $matches[2];

            if ($usedNumber >= (int) $rule->next_correlative) {
                $rule->update([
                    'next_correlative' => $usedNumber + 1,
                ]);
            }
        }, 3);
    }

    private static function categoryPrefix(string $categoryName): string
    {
        $normalized = Str::upper(preg_replace('/[^A-Z0-9]/i', '', $categoryName) ?? '');
        $prefix = Str::substr($normalized, 0, 3);

        return Str::padRight($prefix, 3, 'X');
    }

    private static function assertNoTrackedSerialDuplicate(mixed $serial, ?int $ignoreProductId): void
    {
        $normalized = self::normalizeTrackedSerial($serial);

        if ($normalized === null) {
            return;
        }

        $query = self::query()->whereRaw('UPPER(TRIM(serial)) = ?', [$normalized]);

        if ($ignoreProductId) {
            $query->whereKeyNot($ignoreProductId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'serial' => 'El serial ya existe para otro producto registrado.',
            ]);
        }
    }

    private static function normalizeTrackedSerial(mixed $serial): ?string
    {
        $value = Str::upper(trim((string) $serial));

        if ($value === '') {
            return null;
        }

        $nonTrackable = [
            'N/A',
            'NA',
            'N.D',
            'ND',
            'SIN SERIAL',
            'S/N',
            'SN',
            'NO APLICA',
        ];

        if (in_array($value, $nonTrackable, true)) {
            return null;
        }

        return $value;
    }
}
