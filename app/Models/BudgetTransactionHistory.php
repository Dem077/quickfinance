<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransactionHistory extends Model
{
    protected $fillable = [
        'sub_budget_id',
        'transaction_date',
        'transaction_type',
        'transaction_amount',
        'transaction_balance',
        'transaction_details',
        'transaction_by',
    ];

    public function subBudget(): BelongsTo
    {
        return $this->belongsTo(SubBudgetAccounts::class, 'sub_budget_id');
    }

    public function transactionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transaction_by');
    }

    public static function createtransaction($budgetid , $type, $amount, $balance, $details, $by)
    {
        $instance = new self();
        $instance->sub_budget_id = $budgetid;
        $instance->transaction_date = now();
        $instance->transaction_type = $type;
        $instance->transaction_amount = $amount;
        $instance->transaction_balance = $balance;
        $instance->transaction_details = $details;
        $instance->transaction_by = $by;
        $instance->save();

    }
}
