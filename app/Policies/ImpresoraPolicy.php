<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Impresora;
use Illuminate\Auth\Access\HandlesAuthorization;

class ImpresoraPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Impresora');
    }

    public function view(AuthUser $authUser, Impresora $impresora): bool
    {
        return $authUser->can('View:Impresora');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Impresora');
    }

    public function update(AuthUser $authUser, Impresora $impresora): bool
    {
        return $authUser->can('Update:Impresora');
    }

    public function delete(AuthUser $authUser, Impresora $impresora): bool
    {
        return $authUser->can('Delete:Impresora');
    }

    public function restore(AuthUser $authUser, Impresora $impresora): bool
    {
        return $authUser->can('Restore:Impresora');
    }

    public function forceDelete(AuthUser $authUser, Impresora $impresora): bool
    {
        return $authUser->can('ForceDelete:Impresora');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Impresora');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Impresora');
    }

    public function replicate(AuthUser $authUser, Impresora $impresora): bool
    {
        return $authUser->can('Replicate:Impresora');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Impresora');
    }

}