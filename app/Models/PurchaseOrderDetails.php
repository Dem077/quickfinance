<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderDetails extends Model
{
    protected $fillable = [
        'po_id',
        'itemcode',
        'desc',
        'unit_measure',
        'qty',
        'unit_price',
        'budget_account_id',
        'amount',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrders::class, 'po_id');
    }

    public function budgetAccount(): BelongsTo
    {
        return $this->belongsTo(SubBudgetAccounts::class, 'budget_account_id');
    }
}
