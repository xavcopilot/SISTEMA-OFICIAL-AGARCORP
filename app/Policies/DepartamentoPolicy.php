<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Departamento;
use Illuminate\Auth\Access\HandlesAuthorization;

class DepartamentoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Departamento');
    }

    public function view(AuthUser $authUser, Departamento $departamento): bool
    {
        return $authUser->can('View:Departamento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Departamento');
    }

    public function update(AuthUser $authUser, Departamento $departamento): bool
    {
        return $authUser->can('Update:Departamento');
    }

    public function delete(AuthUser $authUser, Departamento $departamento): bool
    {
        return $authUser->can('Delete:Departamento');
    }

    public function restore(AuthUser $authUser, Departamento $departamento): bool
    {
        return $authUser->can('Restore:Departamento');
    }

    public function forceDelete(AuthUser $authUser, Departamento $departamento): bool
    {
        return $authUser->can('ForceDelete:Departamento');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Departamento');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Departamento');
    }

    public function replicate(AuthUser $authUser, Departamento $departamento): bool
    {
        return $authUser->can('Replicate:Departamento');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Departamento');
    }

}