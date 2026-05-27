<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SkuCodeRule;
use Illuminate\Auth\Access\HandlesAuthorization;

class SkuCodeRulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SkuCodeRule');
    }

    public function view(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return $authUser->can('View:SkuCodeRule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SkuCodeRule');
    }

    public function update(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return $authUser->can('Update:SkuCodeRule');
    }

    public function delete(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return $authUser->can('Delete:SkuCodeRule');
    }

    public function restore(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return $authUser->can('Restore:SkuCodeRule');
    }

    public function forceDelete(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return $authUser->can('ForceDelete:SkuCodeRule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SkuCodeRule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SkuCodeRule');
    }

    public function replicate(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return $authUser->can('Replicate:SkuCodeRule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SkuCodeRule');
    }

}