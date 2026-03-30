<?php

namespace App\Policies;

use App\Models\SkuCodeRule;
use Illuminate\Foundation\Auth\User as AuthUser;

class SkuCodeRulePolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->hasRole('A.I.T');
    }

    public function view(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return $authUser->hasRole('A.I.T');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->hasRole('A.I.T');
    }

    public function update(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return $authUser->hasRole('A.I.T');
    }

    public function delete(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return $authUser->hasRole('A.I.T');
    }

    public function restore(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, SkuCodeRule $skuCodeRule): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
