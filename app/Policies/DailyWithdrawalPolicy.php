<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DailyWithdrawal;
use Illuminate\Auth\Access\HandlesAuthorization;

class DailyWithdrawalPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DailyWithdrawal');
    }

    public function view(AuthUser $authUser, DailyWithdrawal $dailyWithdrawal): bool
    {
        return $authUser->can('View:DailyWithdrawal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DailyWithdrawal');
    }

    public function update(AuthUser $authUser, DailyWithdrawal $dailyWithdrawal): bool
    {
        return $authUser->can('Update:DailyWithdrawal');
    }

    public function delete(AuthUser $authUser, DailyWithdrawal $dailyWithdrawal): bool
    {
        return $authUser->can('Delete:DailyWithdrawal');
    }

    public function restore(AuthUser $authUser, DailyWithdrawal $dailyWithdrawal): bool
    {
        return $authUser->can('Restore:DailyWithdrawal');
    }

    public function forceDelete(AuthUser $authUser, DailyWithdrawal $dailyWithdrawal): bool
    {
        return $authUser->can('ForceDelete:DailyWithdrawal');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DailyWithdrawal');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DailyWithdrawal');
    }

    public function replicate(AuthUser $authUser, DailyWithdrawal $dailyWithdrawal): bool
    {
        return $authUser->can('Replicate:DailyWithdrawal');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DailyWithdrawal');
    }

}