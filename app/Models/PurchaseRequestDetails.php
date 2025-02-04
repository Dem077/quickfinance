<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestDetails extends Model
{
    protected $fillable = [
        'item_id',
        'unit',
        'amount',
        'pr_id',
        'is_utilized',
    ];

  

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequests::class, 'pr_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderDetails::class , 'item_id');
    }

    public function items(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
