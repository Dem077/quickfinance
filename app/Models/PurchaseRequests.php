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
        'user_id',
    ];

    public function budgetAccount():BelongsTo
    {
        return $this->belongsTo(BudgetAccounts::class);
    }

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseRequestDetails():HasOne
    {
        return $this->hasOne(PurchaseRequestDetails::class);
    }
}
