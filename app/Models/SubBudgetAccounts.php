<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubBudgetAccounts extends Model
{
    protected $fillable = [
        'code',
        'name',
        'amount',
        'budget_account_id',
    ];

    public function budgetAccount(): BelongsTo
    {
        return $this->belongsTo(BudgetAccounts::class, 'budget_account_id');
    }

    public function purchaseRequest(): HasMany
    {
        return $this->hasMany(PurchaseRequests::class, 'budget_account_id');
    }
}
