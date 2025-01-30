<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseRequests extends Model
{
    protected $fillable = [
        'pr_no',
        'date',
        'budget_account_id',
        'department_id',
        'user_id',
        'is_submited',
        'is_approved',
        'is_canceled',
        'cancel_remark',
    ];

    public function budgetAccount(): BelongsTo
    {
        return $this->belongsTo(SubBudgetAccounts::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseRequestDetails(): HasOne
    {
        return $this->hasOne(PurchaseRequestDetails::class, 'pr_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departments::class, 'department_id');
    }

    public function approvedby():BelongsTo
    {
        return $this->belongsTo(User::class , 'approved_by');
    }
}
