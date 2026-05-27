<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Sumario;
use Illuminate\Auth\Access\HandlesAuthorization;

class SumarioPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Sumario');
    }

    public function view(AuthUser $authUser, Sumario $sumario): bool
    {
        return $authUser->can('View:Sumario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Sumario');
    }

    public function update(AuthUser $authUser, Sumario $sumario): bool
    {
        return $authUser->can('Update:Sumario');
    }

    public function delete(AuthUser $authUser, Sumario $sumario): bool
    {
        return $authUser->can('Delete:Sumario');
    }

    public function restore(AuthUser $authUser, Sumario $sumario): bool
    {
        return $authUser->can('Restore:Sumario');
    }

    public function forceDelete(AuthUser $authUser, Sumario $sumario): bool
    {
        return $authUser->can('ForceDelete:Sumario');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Sumario');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Sumario');
    }

    public function replicate(AuthUser $authUser, Sumario $sumario): bool
    {
        return $authUser->can('Replicate:Sumario');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Sumario');
    }

}