<?php

namespace App\Policies;

use App\Models\PurchaseOrders;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetManagementPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_asset::management');
    }

    public function view(User $user, PurchaseOrders $purchaseOrders): bool
    {
        return $user->can('view_asset::management');
    }
}
