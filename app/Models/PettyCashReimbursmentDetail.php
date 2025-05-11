<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashReimbursmentDetail extends Model
{
    protected $fillable = [
        'date',
        'Vendor_id',
        'details',
        'po_id',
        'amount',
        'bill_no',
        'sub_budget_id',
        'petty_cash_reimb_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendors::class, 'Vendor_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrders::class, 'po_id');
    }

    public function subBudget()
    {
        return $this->belongsTo(SubBudgetAccounts::class, 'sub_budget_id');
    }

    public function pettyCashReimbursment(): BelongsTo
    {
        return $this->belongsTo(PettyCashReimbursment::class, 'petty_cash_reimb_id');
    }
}
