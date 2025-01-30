<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestDetails extends Model
{
    protected $fillable = [
        'item',
        'unit',
        'amount',
        'pr_id',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequests::class, 'pr_id');
    }
}
