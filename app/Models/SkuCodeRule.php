<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SkuCodeRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'prefix',
        'next_correlative',
        'number_length',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'next_correlative' => 'integer',
        'number_length' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (SkuCodeRule $rule): void {
            $rule->prefix = self::normalizePrefix((string) $rule->prefix);
            $rule->next_correlative = max(1, (int) $rule->next_correlative);
            // number_length se calcula automáticamente según el correlativo actual
            $rule->number_length = self::requiredLength((int) $rule->next_correlative);
        });
    }

    /**
     * Calcula la longitud mínima necesaria para mostrar el correlativo,
     * partiendo de 4 dígitos y creciendo cuando el correlativo supere el
     * máximo representable (p. ej. 9999 → pasa a 5 dígitos, etc.).
     * Máximo absoluto: 10 dígitos.
     */
    public static function requiredLength(int $correlative): int
    {
        $length = 4;
        while ($length < 10 && $correlative >= (10 ** $length)) {
            $length++;
        }

        return $length;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function formatSku(int $correlative): string
    {
        $correlative = max(1, $correlative);

        return sprintf('%s-%0' . $this->number_length . 'd', $this->prefix, $correlative);
    }

    public static function normalizePrefix(string $value): string
    {
        $normalized = Str::upper(preg_replace('/[^A-Z0-9]/i', '', $value) ?? '');
        $trimmed = Str::substr($normalized, 0, 3);

        return Str::padRight($trimmed, 3, 'X');
    }
}
