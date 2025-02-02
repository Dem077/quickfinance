<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrders extends Model
{
    protected $fillable = [
        'vendor_id',
        'po_no',
        'date',
        'pr_id',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendors::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequests::class, 'pr_id');
    }

    public function purchaseOrderDetails(): HasOne
    {
        return $this->hasOne(PurchaseOrderDetails::class, 'po_id');
    }
    
}
