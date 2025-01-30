<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetAccounts extends Model
{
    protected $fillable = [
        'name',
        'code',
        'expenditure_type',
        'account',
        'amount',
    ];

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequests::class);
    }

    public function subBudgetAccounts(): HasMany
    {
        return $this->hasMany(SubBudgetAccounts::class, 'budget_account_id');
    }
}
