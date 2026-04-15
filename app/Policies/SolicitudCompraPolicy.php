<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SolicitudCompra;
use App\Support\SolicitudCompraFlow;
use Illuminate\Auth\Access\HandlesAuthorization;

class SolicitudCompraPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SolicitudCompra');
    }

    public function view(AuthUser $authUser, SolicitudCompra $solicitudCompra): bool
    {
        return $authUser->can('View:SolicitudCompra');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SolicitudCompra');
    }

    public function update(AuthUser $authUser, SolicitudCompra $solicitudCompra): bool
    {
        if (SolicitudCompraFlow::canManageDraft($authUser, $solicitudCompra)) {
            return true;
        }

        return $authUser->can('Update:SolicitudCompra');
    }

    public function delete(AuthUser $authUser, SolicitudCompra $solicitudCompra): bool
    {
        if (SolicitudCompraFlow::canManageDraft($authUser, $solicitudCompra)) {
            return true;
        }

        return $authUser->can('Delete:SolicitudCompra');
    }

    public function restore(AuthUser $authUser, SolicitudCompra $solicitudCompra): bool
    {
        return $authUser->can('Restore:SolicitudCompra');
    }

    public function forceDelete(AuthUser $authUser, SolicitudCompra $solicitudCompra): bool
    {
        return $authUser->can('ForceDelete:SolicitudCompra');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SolicitudCompra');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SolicitudCompra');
    }

    public function replicate(AuthUser $authUser, SolicitudCompra $solicitudCompra): bool
    {
        return $authUser->can('Replicate:SolicitudCompra');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SolicitudCompra');
    }

}