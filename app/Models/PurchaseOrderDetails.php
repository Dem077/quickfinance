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
        'amount',
    ];

    public function purchaseOrder():BelongsTo
    {
        return $this->belongsTo(PurchaseOrders::class, 'po_id');
    }
}
