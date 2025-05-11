<?php

namespace App\Policies;

use App\Models\PettyCashReimbursment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PettyCashReimbursmentPolicy
{
    use HandlesAuthorization;

    public function pv_approve(User $user): bool
    {
        return $user->can('pv_approve_petty::cash::reimbursment');
    }

    public function fin_hod_approve(User $user): bool
    {
        return $user->can('fin_hod_approve_petty::cash::reimbursment');
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_petty::cash::reimbursment');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PettyCashReimbursment $pettyCashReimbursment): bool
    {
        return $user->can('view_petty::cash::reimbursment');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_petty::cash::reimbursment');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PettyCashReimbursment $pettyCashReimbursment): bool
    {
        if ($pettyCashReimbursment->status->value === 'draft' && $user->can('update_petty::cash::reimbursment') || $pettyCashReimbursment->status->value === 'fin_approved' && $user->can('update_petty::cash::reimbursment')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PettyCashReimbursment $pettyCashReimbursment): bool
    {
        return $user->can('delete_petty::cash::reimbursment');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_petty::cash::reimbursment');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, PettyCashReimbursment $pettyCashReimbursment): bool
    {
        return $user->can('{{ ForceDelete }}');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('{{ ForceDeleteAny }}');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, PettyCashReimbursment $pettyCashReimbursment): bool
    {
        return $user->can('{{ Restore }}');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('{{ RestoreAny }}');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, PettyCashReimbursment $pettyCashReimbursment): bool
    {
        return $user->can('{{ Replicate }}');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('{{ Reorder }}');
    }
}
