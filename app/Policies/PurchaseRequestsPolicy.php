<?php

namespace App\Policies;

use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequests;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Auth;

class PurchaseRequestsPolicy
{
    use HandlesAuthorization;

    public function send_approval(User $user): bool
    {
        return $user->can('send_approval_purchase::requests');
    }

    public function approve(User $user): bool
    {
        return $user->can('approve_purchase::requests');
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_purchase::requests');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseRequests $purchaseRequests): bool
    {
        return $user->can('view_purchase::requests');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_purchase::requests');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseRequests $purchaseRequests): bool
    {
        // dd($this->send_approval(Auth::user()));
        // dd($this->send_approval($user));
        // return $user->can('update_purchase::requests');
        if ($purchaseRequests->status == PurchaseRequestsStatus::Approved->value || ($purchaseRequests->status == PurchaseRequestsStatus::Submitted->value && $this->send_approval(Auth::user()))) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseRequests $purchaseRequests): bool
    {
        return $user->can('delete_purchase::requests');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_purchase::requests');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, PurchaseRequests $purchaseRequests): bool
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
    public function restore(User $user, PurchaseRequests $purchaseRequests): bool
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
    public function replicate(User $user, PurchaseRequests $purchaseRequests): bool
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
