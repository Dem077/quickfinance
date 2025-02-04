<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransfer extends Model
{
   protected $fillable = [
         'from_budget_id',
         'to_budget_id',
         'user_id',
         'amount',
         'description'
   ];

    public function fromBudget() :BelongsTo
    {
        return $this->belongsTo(SubBudgetAccounts::class, 'from_budget_id');
    }

    public function toBudget(): BelongsTo
    {
        return $this->belongsTo(SubBudgetAccounts::class, 'to_budget_id');
    }

    public function user() :BelongsTo
    {
        return $this->belongsTo(User::class , 'user_id');
    }
}
