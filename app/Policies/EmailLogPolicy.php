<?php

namespace App\Policies;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_email::log');
    }

    public function view(User $user, EmailLog $emailLog): bool
    {
        return $user->can('view_any_email::log');
    }
}
