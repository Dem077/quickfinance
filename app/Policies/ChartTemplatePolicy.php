<?php

namespace App\Policies;

use App\Models\ChartTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChartTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_chart');
    }

    public function view(User $user, ChartTemplate $chartTemplate): bool
    {
        return $user->can('view_chart');
    }

    public function create(User $user): bool
    {
        return $user->can('create_chart');
    }

    public function update(User $user, ChartTemplate $chartTemplate): bool
    {
        return $user->can('update_chart');
    }

    public function delete(User $user, ChartTemplate $chartTemplate): bool
    {
        return $user->can('delete_chart');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_chart');
    }
}
