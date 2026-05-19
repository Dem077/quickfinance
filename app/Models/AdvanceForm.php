<?php

namespace App\Models;

use App\Enums\AdvanceFormStatus;
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
        'status',
        'md_dmd_approved_by',
        'hod_approved_by',
    ];

    protected $casts = [
        'status' => AdvanceFormStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function mdDmdApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'md_dmd_approved_by');
    }

    public function hodApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hod_approved_by');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendors::class, 'vendors_id');
    }

    public function purchaseOrder(): HasOne
    {
        return $this->Hasone(PurchaseOrders::class, 'advance_form_id');
    }
}
