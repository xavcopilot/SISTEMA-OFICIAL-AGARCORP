<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class ControlCodeGenerator
{
    public static function generate(string $prefix, string $modelClass, string $column): string
    {
        $base = strtoupper(trim($prefix));

        do {
            $candidate = $base . '-' . now()->format('His');

            if (! self::exists($modelClass, $column, $candidate)) {
                return $candidate;
            }

            $candidateWithMilliseconds = $base . '-' . now()->format('Hisv');

            if (! self::exists($modelClass, $column, $candidateWithMilliseconds)) {
                return $candidateWithMilliseconds;
            }

            $candidateWithSuffix = $candidateWithMilliseconds . '-' . random_int(10, 99);
        } while (self::exists($modelClass, $column, $candidateWithSuffix));

        return $candidateWithSuffix;
    }

    private static function exists(string $modelClass, string $column, string $value): bool
    {
        /** @var Model $modelClass */
        return $modelClass::query()->where($column, $value)->exists();
    }
}