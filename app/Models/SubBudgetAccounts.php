<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubBudgetAccounts extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'amount',
        'budget_account_id',
        'location_id',
        'department_id'
    ];

    public function budgetAccount(): BelongsTo
    {
        return $this->belongsTo(BudgetAccounts::class, 'budget_account_id');
    }

    public function purchaseRequestdetails(): HasMany
    {
        return $this->hasMany(PurchaseRequestDetails::class, 'budget_account_id');
    }

    public function budgetTransferFrom(): HasMany
    {
        return $this->hasMany(BudgetTransfer::class, 'from_budget_id');
    }

    public function budgetTransferTo(): HasMany
    {
        return $this->hasMany(BudgetTransfer::class, 'to_budget_id');
    }

    public function pettyCashReimbursmentDetail(): HasMany
    {
        return $this->hasMany(PettyCashReimbursmentDetail::class, 'sub_budget_id');
    }

    public function budgetTransactionHistory(): HasMany
    {
        return $this->hasMany(BudgetTransactionHistory::class, 'sub_budget_id');
    }

    public function purchaseorderdetails(): HasMany
    {
        return $this->hasMany(PurchaseOrderDetails::class, 'budget_account_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departments::class, 'department_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
