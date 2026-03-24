<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderDetails extends Model
{
    protected $fillable = [
        'po_id',
        'item_id',
        'itemcode',
        'desc',
        'unit_measure',
        'qty',
        'unit_price',
        'tax_amount',
        'budget_account_id',
        'amount',
        'is_utilized',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrders::class, 'po_id');
    }

    public function items()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
    public function budgetAccount(): BelongsTo
    {
        return $this->belongsTo(SubBudgetAccounts::class, 'budget_account_id');
    }
}
