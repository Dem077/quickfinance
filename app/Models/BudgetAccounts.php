<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetAccounts extends Model 
{
    use HasFactory;

    protected $fillable = [
        'name',
        'expenditure_type',
        'account',
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
