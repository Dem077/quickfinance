<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdvanceForm extends Model
{
    protected $fillable = [
        'qoation_no',
        'expected_delivery',
        'request_number',
        'advance_percentage',
        'advance_amount',
        'balance_amount',
        'generated_by',
        'vendors_id',
    ];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function vendor():BelongsTo
    {
        return $this->belongsTo(Vendors::class, 'vendors_id');
    }

    public function purchaseOrder():HasOne
    {
        return $this->Hasone(PurchaseOrders::class , 'advance_form_id');
    }
}
