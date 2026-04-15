<?php

namespace App\Support;

use App\Models\FormDraft;
use App\Models\User;
use Illuminate\Support\Carbon;

class FormDraftStore
{
    public const DEFAULT_TTL_HOURS = 72;

    public static function save(User $user, string $formKey, array $payload, ?int $ttlHours = null): FormDraft
    {
        self::purgeExpired();

        $expiresAt = now()->addHours($ttlHours ?? self::DEFAULT_TTL_HOURS);

        return FormDraft::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'form_key' => $formKey,
            ],
            [
                'payload' => $payload,
                'expires_at' => $expiresAt,
            ]
        );
    }

    public static function load(User $user, string $formKey): ?array
    {
        self::purgeExpired();

        $draft = FormDraft::query()
            ->where('user_id', $user->id)
            ->where('form_key', $formKey)
            ->first();

        if (! $draft) {
            return null;
        }

        return is_array($draft->payload) ? $draft->payload : null;
    }

    public static function clear(User $user, string $formKey): void
    {
        FormDraft::query()
            ->where('user_id', $user->id)
            ->where('form_key', $formKey)
            ->delete();
    }

    public static function purgeExpired(): void
    {
        FormDraft::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->delete();
    }
}
