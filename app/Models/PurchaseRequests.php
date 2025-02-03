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
        'location_id',
        'project_id',
        'purpose',
        'user_id',
        'is_submited',
        'is_approved',
        'is_canceled',
        'cancel_remark',
        'uploaded_document',
        'approved_canceled_by',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function approvedby(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_canceled_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

}
