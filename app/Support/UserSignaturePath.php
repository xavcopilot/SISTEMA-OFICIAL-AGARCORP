<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserSignaturePath
{
    private const DISK = 'public';
    private const DIRECTORY = 'firmas';

    public static function resolveForUser(?User $user, string $fallbackToken = '__ENVIADA__'): ?string
    {
        if (! $user) {
            return $fallbackToken;
        }

        $pathById = self::findByUserId((int) $user->id);
        if ($pathById !== null) {
            return $pathById;
        }

        $relativePath = self::buildRelativePath($user);

        if (Storage::disk(self::DISK)->exists($relativePath)) {
            return $relativePath;
        }

        return $fallbackToken;
    }

    public static function findByUserId(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $prefix = (string) $userId;
        $files = Storage::disk(self::DISK)->files(self::DIRECTORY);

        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);

            if ($filename === $prefix || str_starts_with($filename, $prefix . '-')) {
                return $file;
            }
        }

        return null;
    }

    public static function buildRelativePath(User $user): string
    {
        $nameSlug = Str::slug(trim((string) $user->name), '-');

        if ($nameSlug === '') {
            $nameSlug = 'usuario';
        }

        return self::DIRECTORY . '/' . (int) $user->id . '-' . $nameSlug . '.png';
    }
}
