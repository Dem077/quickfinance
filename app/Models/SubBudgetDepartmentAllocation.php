<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubBudgetDepartmentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'sub_budget_account_id',
        'department_id',
        'location_id',
        'amount',
    ];

    public function subBudgetAccount(): BelongsTo
    {
        return $this->belongsTo(SubBudgetAccounts::class, 'sub_budget_account_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departments::class, 'department_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
