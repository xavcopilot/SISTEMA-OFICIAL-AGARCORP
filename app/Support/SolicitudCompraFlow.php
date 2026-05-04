<?php

namespace App\Support;

use App\Models\SolicitudCompra;
use App\Models\User;
use App\Support\UserSignaturePath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SolicitudCompraFlow
{
    public const STORAGE_ROLES = ['Almacen'];

    public const APPROVER_ROLES = ['Lider', 'Alta Gerencia', 'Gerencia de Operaciones', 'Gerencia de Finanzas'];

    public const PROCUREMENT_ROLES = ['Procura'];

    public static function isAdministrator(?User $user): bool
    {
        return (bool) $user?->hasRole(['admin', 'Alta Gerencia']);
    }

    public static function hasManagementAccess(?User $user): bool
    {
        return (bool) $user?->hasRole(['admin', 'Alta Gerencia', 'A.I.T']);
    }

    public static function isReviewer(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(self::STORAGE_ROLES)
            || $user->hasRole(self::APPROVER_ROLES)
            || $user->hasRole(self::PROCUREMENT_ROLES);
    }

    public static function isApproverOnly(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(self::APPROVER_ROLES)
            && ! $user->hasRole(self::STORAGE_ROLES)
            && ! $user->hasRole(self::PROCUREMENT_ROLES);
    }

    public static function visibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (self::hasManagementAccess($user)) {
            return $query;
        }

        return $query->where(function (Builder $visibleQuery) use ($user): void {
            $visibleQuery
                ->where('solicitado_por_user_id', $user->id)
                ->orWhere('por_almacen_user_id', $user->id)
                ->orWhere('aprobado_por_user_id', $user->id)
                ->orWhere('recibido_por_user_id', $user->id);

            if ($user->hasRole(self::STORAGE_ROLES)) {
                $visibleQuery->orWhereNull('por_almacen_user_id');
            }

            if ($user->hasRole(self::PROCUREMENT_ROLES)) {
                $visibleQuery->orWhere(function (Builder $procuraQuery): void {
                    $procuraQuery
                        ->whereNotNull('fecha_aprobador')
                        ->whereNull('recibido_por_user_id');
                });
            }
        });
    }

    public static function canView(?User $user, SolicitudCompra $solicitudCompra): bool
    {
        if (! $user) {
            return false;
        }

        if (self::hasManagementAccess($user)) {
            return true;
        }

        if ((int) $solicitudCompra->solicitado_por_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $solicitudCompra->por_almacen_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $solicitudCompra->aprobado_por_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $solicitudCompra->recibido_por_user_id === (int) $user->id) {
            return true;
        }

        if ($user->hasRole(self::STORAGE_ROLES) && blank($solicitudCompra->por_almacen_user_id)) {
            return true;
        }

        return $user->hasRole(self::PROCUREMENT_ROLES)
            && filled($solicitudCompra->fecha_aprobador)
            && blank($solicitudCompra->recibido_por_user_id);
    }

    public static function canEditRequest(?User $user, SolicitudCompra $solicitudCompra): bool
    {
        if (self::canManageDraft($user, $solicitudCompra)) {
            return true;
        }

        return self::canEditRejectedRequest($user, $solicitudCompra);
    }

    public static function canDeleteRequest(?User $user, SolicitudCompra $solicitudCompra): bool
    {
        return self::canManageDraft($user, $solicitudCompra);
    }

    public static function canManageDraft(?User $user, SolicitudCompra $solicitudCompra): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $solicitudCompra->solicitado_por_user_id !== (int) $user->id) {
            return false;
        }

        return self::isDraft($solicitudCompra);
    }

    public static function isDraft(SolicitudCompra $solicitudCompra): bool
    {
        return (string) $solicitudCompra->estado === 'BORRADOR';
    }

    public static function submitDraft(SolicitudCompra $solicitudCompra, User $user): SolicitudCompra
    {
        $solicitudCompra = self::ensureTrackingIdentifiers($solicitudCompra, (int) $user->id);

        $almacenUserId = $solicitudCompra->por_almacen_user_id ?: self::defaultAlmacenUserId();
        $procuraUserId = $solicitudCompra->recibido_por_user_id ?: self::defaultProcuraUserId();

        $solicitudCompra->forceFill([
            'codigo_control' => $solicitudCompra->codigo_control,
            'numero_solicitud_usuario' => $solicitudCompra->numero_solicitud_usuario,
            'solicitado_por_user_id' => $solicitudCompra->solicitado_por_user_id ?: $user->id,
            'por_almacen_user_id' => $almacenUserId,
            'cargo_solicitante' => $solicitudCompra->cargo_solicitante ?: $user->cargo?->nombre,
            'cargo_almacen' => self::cargoForUserId($almacenUserId),
            'cargo_aprobador' => self::cargoForUserId($solicitudCompra->aprobado_por_user_id),
            'recibido_por_user_id' => $procuraUserId,
            'cargo_receptor' => self::cargoForUserId($procuraUserId),
            'firma_solicitante' => UserSignaturePath::resolveForUser($user, '__ENVIADA__'),
            'firma_almacen' => null,
            'firma_aprobador' => null,
            'firma_receptor' => null,
            'fecha_solicitante' => now()->toDateString(),
            'fecha_almacen' => null,
            'fecha_aprobador' => null,
            'fecha_receptor' => null,
            'hora_receptor' => null,
            'rechazo_etapa' => null,
            'rechazo_comentario' => null,
            'rechazo_por_user_id' => null,
            'rechazo_destinatario_user_id' => null,
            'rechazo_en' => null,
            'estado' => SolicitudCompra::ESTADO_EN_ESPERA_ALMACEN,
        ])->save();

        return $solicitudCompra->fresh();
    }

    public static function ensureTrackingIdentifiers(SolicitudCompra $solicitudCompra, ?int $requesterUserId = null): SolicitudCompra
    {
        return DB::transaction(function () use ($solicitudCompra, $requesterUserId): SolicitudCompra {
            $lockedRecord = SolicitudCompra::query()->lockForUpdate()->findOrFail($solicitudCompra->id);
            $resolvedRequesterId = (int) ($requesterUserId ?: $lockedRecord->solicitado_por_user_id);

            $changes = [];

            if (blank($lockedRecord->codigo_control)) {
                $changes['codigo_control'] = ControlCodeGenerator::generate('SOL', SolicitudCompra::class, 'codigo_control');
            }

            if (blank($lockedRecord->numero_solicitud_usuario) && $resolvedRequesterId > 0) {
                $changes['numero_solicitud_usuario'] = self::nextRequesterSequence($resolvedRequesterId);
            }

            if ($changes !== []) {
                $lockedRecord->forceFill($changes)->save();
            }

            return $lockedRecord->fresh();
        });
    }

    private static function nextRequesterSequence(int $requesterUserId): int
    {
        $lastSequence = SolicitudCompra::query()
            ->where('solicitado_por_user_id', $requesterUserId)
            ->lockForUpdate()
            ->orderByDesc('numero_solicitud_usuario')
            ->value('numero_solicitud_usuario');

        return ((int) $lastSequence) + 1;
    }

    public static function canEditRejectedRequest(?User $user, SolicitudCompra $solicitudCompra): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $solicitudCompra->solicitado_por_user_id !== (int) $user->id) {
            return false;
        }

        if ((string) $solicitudCompra->estado !== 'RECHAZADA') {
            return false;
        }

        // Cada version rechazada solo se corrige una vez: si ya existe una version posterior, se bloquea.
        return ! self::hasNewerVersion($solicitudCompra);
    }

    private static function hasNewerVersion(SolicitudCompra $solicitudCompra): bool
    {
        $sharedCode = (string) ($solicitudCompra->codigo_control ?: $solicitudCompra->id);

        return SolicitudCompra::query()
            ->where('codigo_control', $sharedCode)
            ->whereKeyNot($solicitudCompra->id)
            ->where('id', '>', $solicitudCompra->id)
            ->exists();
    }

    public static function canSignRequester(?User $user, SolicitudCompra $solicitudCompra): bool
    {
        if (! self::isDraft($solicitudCompra)) {
            return false;
        }

        if (! $user || ! blank($solicitudCompra->firma_solicitante)) {
            return false;
        }

        return (int) $solicitudCompra->solicitado_por_user_id === (int) $user->id;
    }

    public static function canSignAlmacen(?User $user, SolicitudCompra $solicitudCompra): bool
    {
        if ((string) $solicitudCompra->estado === 'RECHAZADA') {
            return false;
        }

        if (! $user) {
            return false;
        }

        if (blank($solicitudCompra->firma_solicitante) || ! blank($solicitudCompra->fecha_almacen)) {
            return false;
        }

        if (! $user->hasRole(self::STORAGE_ROLES)) {
            return false;
        }

        return blank($solicitudCompra->por_almacen_user_id)
            || ((int) $solicitudCompra->por_almacen_user_id === (int) $user->id);
    }

    public static function canSignApprover(?User $user, SolicitudCompra $solicitudCompra): bool
    {
        if ((string) $solicitudCompra->estado === 'RECHAZADA') {
            return false;
        }

        if (! $user || blank($solicitudCompra->fecha_almacen) || ! blank($solicitudCompra->fecha_aprobador)) {
            return false;
        }

        if (! $user->hasRole(self::APPROVER_ROLES)) {
            return false;
        }

        return blank($solicitudCompra->aprobado_por_user_id)
            || ((int) $solicitudCompra->aprobado_por_user_id === (int) $user->id);
    }

    public static function canSignProcura(?User $user, SolicitudCompra $solicitudCompra): bool
    {
        if ((string) $solicitudCompra->estado === 'RECHAZADA') {
            return false;
        }

        if (! $user || blank($solicitudCompra->fecha_aprobador) || ! blank($solicitudCompra->fecha_receptor)) {
            return false;
        }

        if (! $user->hasRole(self::PROCUREMENT_ROLES)) {
            return false;
        }

        return blank($solicitudCompra->recibido_por_user_id)
            || ((int) $solicitudCompra->recibido_por_user_id === (int) $user->id);
    }

    public static function pendingInboxQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('estado', '!=', 'RECHAZADA')
            ->where(function (Builder $inboxQuery) use ($user): void {
            if ($user->hasRole(self::STORAGE_ROLES)) {
                $inboxQuery->orWhere(function (Builder $storageQuery) use ($user): void {
                    $storageQuery
                        ->whereNotNull('firma_solicitante')
                        ->whereNull('fecha_almacen')
                        ->where('por_almacen_user_id', $user->id);
                });
            }

            if ($user->hasRole(self::APPROVER_ROLES)) {
                $inboxQuery->orWhere(function (Builder $approverQuery) use ($user): void {
                    $approverQuery
                        ->whereNotNull('fecha_almacen')
                        ->whereNull('fecha_aprobador')
                        ->where('aprobado_por_user_id', $user->id);
                });
            }

            if ($user->hasRole(self::PROCUREMENT_ROLES)) {
                $inboxQuery->orWhere(function (Builder $procuraQuery) use ($user): void {
                    $procuraQuery
                        ->whereNotNull('fecha_aprobador')
                        ->whereNull('fecha_receptor')
                        ->where('recibido_por_user_id', $user->id);
                });
            }

            // Cualquier usuario puede ver sus propias solicitudes pendientes de envio.
            $inboxQuery->orWhere(function (Builder $requesterQuery) use ($user): void {
                $requesterQuery
                    ->where('solicitado_por_user_id', $user->id)
                    ->whereNull('firma_solicitante');
            });
            });
    }

    public static function requesterRequestsQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('solicitado_por_user_id', $user->id)
            ->where('estado', '!=', 'BORRADOR')
            ->where('estado', '!=', SolicitudCompra::ESTADO_COMPLETADA)
            ->orderByDesc('updated_at');
    }

    public static function requesterDraftsQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('solicitado_por_user_id', $user->id)
            ->where('estado', 'BORRADOR')
            ->orderByDesc('updated_at');
    }

    public static function requesterHistoryQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('solicitado_por_user_id', $user->id)
            ->whereNotNull('firma_solicitante')
            ->orderByDesc('updated_at');
    }

    public static function requesterConformidadHistoryQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('solicitado_por_user_id', $user->id)
            ->where('estado', '!=', 'BORRADOR')
            ->where('estado', '!=', SolicitudCompra::ESTADO_COMPLETADA)
            ->whereHas('items.ordenCompraItems', fn (Builder $itemQuery) => $itemQuery->where('decision_solicitante', 'ACEPTADO'))
            ->orderByDesc('updated_at');
    }

    public static function requesterCompletedHistoryQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('solicitado_por_user_id', $user->id)
            ->where('estado', SolicitudCompra::ESTADO_COMPLETADA)
            ->orderByDesc('updated_at');
    }

    public static function reviewerApprovalHistoryQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where(function (Builder $historyQuery) use ($user): void {
                $historyQuery
                    ->where('por_almacen_user_id', $user->id)
                    ->orWhere('aprobado_por_user_id', $user->id)
                    ->orWhere('recibido_por_user_id', $user->id);
            })
            ->where(function (Builder $stateQuery): void {
                $stateQuery
                    ->whereNotNull('fecha_almacen')
                    ->orWhereNotNull('fecha_aprobador')
                    ->orWhereNotNull('fecha_receptor')
                    ->orWhere('estado', 'RECHAZADA');
            })
            ->orderByDesc('updated_at');
    }

    public static function roleApprovalHistoryQuery(Builder $query, ?User $user, string $roleKey): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return match ($roleKey) {
            'almacen' => $query
                ->where('por_almacen_user_id', $user->id)
                ->where(function (Builder $historyQuery): void {
                    $historyQuery
                        ->whereNotNull('fecha_almacen')
                        ->orWhere(function (Builder $rejectionQuery): void {
                            $rejectionQuery
                                ->where('estado', 'RECHAZADA')
                                ->where('rechazo_etapa', 'almacen');
                        });
                })
                ->orderByDesc('updated_at'),
            'aprobador' => $query
                ->where('aprobado_por_user_id', $user->id)
                ->where(function (Builder $historyQuery): void {
                    $historyQuery
                        ->whereNotNull('fecha_aprobador')
                        ->orWhere(function (Builder $rejectionQuery): void {
                            $rejectionQuery
                                ->where('estado', 'RECHAZADA')
                                ->where('rechazo_etapa', 'aprobador');
                        });
                })
                ->orderByDesc('updated_at'),
            'procura' => $query
                ->where('recibido_por_user_id', $user->id)
                ->where(function (Builder $historyQuery): void {
                    $historyQuery
                        ->whereNotNull('fecha_receptor')
                        ->orWhere(function (Builder $rejectionQuery): void {
                            $rejectionQuery
                                ->where('estado', 'RECHAZADA')
                                ->where('rechazo_etapa', 'procura');
                        });
                })
                ->orderByDesc('updated_at'),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function pendingAreaInboxQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('estado', '!=', 'RECHAZADA')
            ->where(function (Builder $inboxQuery) use ($user): void {
            if ($user->hasRole(self::STORAGE_ROLES)) {
                $inboxQuery->orWhere(function (Builder $storageQuery): void {
                    $storageQuery
                        ->whereNotNull('firma_solicitante')
                        ->whereNull('fecha_almacen');
                });
            }

            if ($user->hasRole(self::APPROVER_ROLES)) {
                $inboxQuery->orWhere(function (Builder $approverQuery): void {
                    $approverQuery
                        ->whereNotNull('fecha_almacen')
                        ->whereNull('fecha_aprobador');
                });
            }

            if ($user->hasRole(self::PROCUREMENT_ROLES)) {
                $inboxQuery->orWhere(function (Builder $procuraQuery): void {
                    $procuraQuery
                        ->whereNotNull('fecha_aprobador')
                        ->whereNull('fecha_receptor');
                });
            }
            });
    }

    public static function reviewedHistoryQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $historyQuery) use ($user): void {
            if ($user->hasRole(self::STORAGE_ROLES)) {
                $historyQuery->orWhere(function (Builder $storageQuery) use ($user): void {
                    $storageQuery
                        ->where('por_almacen_user_id', $user->id)
                        ->whereNotNull('fecha_almacen');
                });
            }

            if ($user->hasRole(self::PROCUREMENT_ROLES)) {
                $historyQuery->orWhere(function (Builder $procuraQuery) use ($user): void {
                    $procuraQuery
                        ->where('recibido_por_user_id', $user->id)
                        ->whereNotNull('fecha_receptor');
                });
            }

            if ($user->hasRole(self::APPROVER_ROLES)) {
                $historyQuery->orWhere(function (Builder $approverQuery) use ($user): void {
                    $approverQuery
                        ->where('aprobado_por_user_id', $user->id)
                        ->whereNotNull('fecha_aprobador');
                });
            }
        });
    }

    public static function rejectedInboxQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('estado', 'RECHAZADA')
            ->where('rechazo_destinatario_user_id', $user->id)
            ->whereNotNull('rechazo_comentario')
            ->orderByDesc('rechazo_en');
    }

    public static function previousRejectedVersion(SolicitudCompra $solicitudCompra): ?SolicitudCompra
    {
        $sharedCode = (string) ($solicitudCompra->codigo_control ?: $solicitudCompra->id);

        return SolicitudCompra::query()
            ->where('codigo_control', $sharedCode)
            ->whereKeyNot($solicitudCompra->id)
            ->where('estado', 'RECHAZADA')
            ->whereNotNull('rechazo_etapa')
            ->where('id', '<', $solicitudCompra->id)
            ->latest('rechazo_en')
            ->first();
    }

    public static function roleHistoryState(SolicitudCompra $solicitudCompra, string $roleKey): array
    {
        return match ($roleKey) {
            'almacen' => self::resolveRoleHistoryState(
                approvedAt: $solicitudCompra->fecha_almacen,
                rejectionStage: 'almacen',
                currentRejectionStage: $solicitudCompra->rechazo_etapa,
                approvedLabel: 'APROBADO',
                rejectedLabel: 'RECHAZADO'
            ),
            'aprobador' => self::resolveRoleHistoryState(
                approvedAt: $solicitudCompra->fecha_aprobador,
                rejectionStage: 'aprobador',
                currentRejectionStage: $solicitudCompra->rechazo_etapa,
                approvedLabel: 'APROBADO',
                rejectedLabel: 'RECHAZADO'
            ),
            'procura' => self::resolveRoleHistoryState(
                approvedAt: $solicitudCompra->fecha_receptor,
                rejectionStage: 'procura',
                currentRejectionStage: $solicitudCompra->rechazo_etapa,
                approvedLabel: 'APROBADO',
                rejectedLabel: 'RECHAZADO'
            ),
            default => ['label' => 'SIN ESTADO', 'color' => 'gray'],
        };
    }

    public static function rejectionStageLabel(?string $stage): ?string
    {
        return match ((string) $stage) {
            'almacen' => 'ALMACEN',
            'aprobador' => 'APROBADOR',
            'procura' => 'PROCURA',
            default => null,
        };
    }

    private static function resolveRoleHistoryState(mixed $approvedAt, string $rejectionStage, ?string $currentRejectionStage, string $approvedLabel, string $rejectedLabel): array
    {
        if ((string) $currentRejectionStage === $rejectionStage) {
            return ['label' => $rejectedLabel, 'color' => 'danger'];
        }

        if (filled($approvedAt)) {
            return ['label' => $approvedLabel, 'color' => 'success'];
        }

        return ['label' => 'SIN ESTADO', 'color' => 'gray'];
    }

    public static function limitToApprovers(Builder $query): Builder
    {
        $currentUserId = auth()->id();

        if ($currentUserId) {
            $query->where('id', '!=', $currentUserId);
        }

        return self::limitUsersByRoles($query, self::APPROVER_ROLES)
            ->orderByRaw("CASE WHEN LOWER(name) LIKE '%johnny%' THEN 0 WHEN LOWER(name) LIKE '%cristina%' THEN 1 WHEN LOWER(name) LIKE '%richard%' THEN 2 ELSE 3 END")
            ->orderBy('name');
    }

    public static function limitToStorageUsers(Builder $query): Builder
    {
        return self::limitUsersByRoles($query, self::STORAGE_ROLES)->orderBy('name');
    }

    public static function limitToProcurementUsers(Builder $query): Builder
    {
        return self::limitUsersByRoles($query, self::PROCUREMENT_ROLES)
            ->orderByRaw("CASE WHEN LOWER(name) LIKE '%hectlys%' OR LOWER(email) LIKE '%hectlys%' THEN 0 ELSE 1 END")
            ->orderBy('name');
    }

    public static function defaultAlmacenUserId(): ?int
    {
        return self::usersByRoles(self::STORAGE_ROLES)->value('id');
    }

    public static function defaultProcuraUserId(): ?int
    {
        return self::usersByRoles(self::PROCUREMENT_ROLES)
            ->orderByRaw("CASE WHEN LOWER(name) LIKE '%hectlys%' OR LOWER(email) LIKE '%hectlys%' THEN 0 ELSE 1 END")
            ->value('id');
    }

    public static function cargoForUserId(?int $userId): ?string
    {
        if (blank($userId)) {
            return null;
        }

        return User::query()->with('cargo:id,nombre')->find($userId)?->cargo?->nombre;
    }

    private static function usersByRoles(array $roles): Builder
    {
        return User::query()
            ->whereHas('roles', function (Builder $roleQuery) use ($roles): void {
                $roleQuery->whereIn('name', $roles);
            })
            ->orderBy('name');
    }

    private static function limitUsersByRoles(Builder $query, array $roles): Builder
    {
        return $query->whereHas('roles', function (Builder $roleQuery) use ($roles): void {
            $roleQuery->whereIn('name', $roles);
        });
    }
}