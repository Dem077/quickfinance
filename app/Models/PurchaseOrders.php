<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrders extends Model
{
    protected $fillable = [
        'vendor_id',
        'po_no',
        'date',
        'pr_id',
        'payment_method',
        'is_submitted',
        'is_closed',
        'is_closed_by',
        'supporting_document',
        'advance_form_id',
        'is_advance_form_required',
        'is_reimbursed',
        'status',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendors::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequests::class, 'pr_id');
    }

    public function purchaseOrderDetails(): HasMany
    {
        return $this->hasMany(PurchaseOrderDetails::class, 'po_id');
    }

    public function advanceForm(): BelongsTo
    {
        return $this->belongsTo(AdvanceForm::class , 'advance_form_id');
    }

    public function pettyCashReimbursment(): HasMany
    {
        return $this->hasMany(PettyCashReimbursment::class, 'po_id');
    }
    
}
