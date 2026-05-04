<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OrdenCompra;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrdenCompraPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OrdenCompra');
    }

    public function view(AuthUser $authUser, OrdenCompra $ordenCompra): bool
    {
        return $authUser->can('View:OrdenCompra');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OrdenCompra');
    }

    public function update(AuthUser $authUser, OrdenCompra $ordenCompra): bool
    {
        return $authUser->can('Update:OrdenCompra');
    }

    public function delete(AuthUser $authUser, OrdenCompra $ordenCompra): bool
    {
        return $authUser->can('Delete:OrdenCompra');
    }

    public function restore(AuthUser $authUser, OrdenCompra $ordenCompra): bool
    {
        return $authUser->can('Restore:OrdenCompra');
    }

    public function forceDelete(AuthUser $authUser, OrdenCompra $ordenCompra): bool
    {
        return $authUser->can('ForceDelete:OrdenCompra');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OrdenCompra');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OrdenCompra');
    }

    public function replicate(AuthUser $authUser, OrdenCompra $ordenCompra): bool
    {
        return $authUser->can('Replicate:OrdenCompra');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OrdenCompra');
    }

}