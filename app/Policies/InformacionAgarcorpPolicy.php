<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\InformacionAgarcorp;
use Illuminate\Auth\Access\HandlesAuthorization;

class InformacionAgarcorpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InformacionAgarcorp');
    }

    public function view(AuthUser $authUser, InformacionAgarcorp $informacionAgarcorp): bool
    {
        return $authUser->can('View:InformacionAgarcorp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InformacionAgarcorp');
    }

    public function update(AuthUser $authUser, InformacionAgarcorp $informacionAgarcorp): bool
    {
        return $authUser->can('Update:InformacionAgarcorp');
    }

    public function delete(AuthUser $authUser, InformacionAgarcorp $informacionAgarcorp): bool
    {
        return $authUser->can('Delete:InformacionAgarcorp');
    }

    public function restore(AuthUser $authUser, InformacionAgarcorp $informacionAgarcorp): bool
    {
        return $authUser->can('Restore:InformacionAgarcorp');
    }

    public function forceDelete(AuthUser $authUser, InformacionAgarcorp $informacionAgarcorp): bool
    {
        return $authUser->can('ForceDelete:InformacionAgarcorp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InformacionAgarcorp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InformacionAgarcorp');
    }

    public function replicate(AuthUser $authUser, InformacionAgarcorp $informacionAgarcorp): bool
    {
        return $authUser->can('Replicate:InformacionAgarcorp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InformacionAgarcorp');
    }

}