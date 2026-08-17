<?php

namespace App\Models\Concerns;

use App\Enums\PurchaseRequestsStatus;
use App\Models\Departments;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopesPurchaseRequests
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if ($user->can('approve_purchase::requests')) {
            return $query->whereNot('status', PurchaseRequestsStatus::Draft->value);
        }

        if ($user->can('md_dmd_approve_purchase::requests')) {
            return $query->whereIn('status', [
                PurchaseRequestsStatus::Approved->value,
                PurchaseRequestsStatus::MD_DMD_Approved->value,
                PurchaseRequestsStatus::MD_DMD_Rejected->value,
                PurchaseRequestsStatus::Closed->value,
            ]);
        }

        if ($user->view_all_pr == true) {
            return $query->whereNot('status', PurchaseRequestsStatus::Draft->value);
        }

        if (Departments::where('hod', $user->id)->exists()) {
            $departmentIds = Departments::where('hod', $user->id)->pluck('id')->all();

            return $query->where(function ($query) use ($user, $departmentIds): void {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($query) use ($departmentIds): void {
                        $query->whereHas('user', fn ($sub) => $sub->whereIn('department_id', $departmentIds))
                            ->whereNot('status', PurchaseRequestsStatus::Draft->value);
                    });
            });
        }

        if ($user->can('view_purchase::requests') && $user->can('create_purchase::orders')) {
            return $query->whereIn('status', [
                PurchaseRequestsStatus::MD_DMD_Approved->value,
                PurchaseRequestsStatus::Closed->value,
            ]);
        }

        return $query->where('user_id', $user->id);
    }
}
