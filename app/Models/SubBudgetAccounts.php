<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubBudgetAccounts extends Model
{

    protected $fillable = [
        'code',
        'name',
        'amount',
        'budget_account_id',
    ];

    public function budgetAccount():BelongsTo
    {
        return $this->belongsTo(BudgetAccounts::class);
    }
}
