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

    public function budgetTransferFrom(): HasMany
    {
        return $this->hasMany(BudgetTransfer::class, 'from_budget_id');
    }

    public function budgetTransferTo(): HasMany
    {
        return $this->hasMany(BudgetTransfer::class, 'to_budget_id');
    }
}
