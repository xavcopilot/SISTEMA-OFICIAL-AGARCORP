<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Cargo;
use Illuminate\Auth\Access\HandlesAuthorization;

class CargoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Cargo');
    }

    public function view(AuthUser $authUser, Cargo $cargo): bool
    {
        return $authUser->can('View:Cargo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Cargo');
    }

    public function update(AuthUser $authUser, Cargo $cargo): bool
    {
        return $authUser->can('Update:Cargo');
    }

    public function delete(AuthUser $authUser, Cargo $cargo): bool
    {
        return $authUser->can('Delete:Cargo');
    }

    public function restore(AuthUser $authUser, Cargo $cargo): bool
    {
        return $authUser->can('Restore:Cargo');
    }

    public function forceDelete(AuthUser $authUser, Cargo $cargo): bool
    {
        return $authUser->can('ForceDelete:Cargo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Cargo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Cargo');
    }

    public function replicate(AuthUser $authUser, Cargo $cargo): bool
    {
        return $authUser->can('Replicate:Cargo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Cargo');
    }

}