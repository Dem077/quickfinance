<?php

namespace App\Models;

use App\Support\PettyCashClaimedPrItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PettyCashReimbursmentDetail extends Model
{
    protected $fillable = [
        'date',
        'Vendor_id',
        'item_id',
        'details',
        'po_id',
        'amount',
        'bill_no',
        'sub_budget_id',
        'petty_cash_reimb_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $detail): void {
            if (! $detail->po_id || ! $detail->item_id) {
                return;
            }

            if (PettyCashClaimedPrItems::isClaimed(
                (int) $detail->po_id,
                (int) $detail->item_id,
                exceptDetailId: $detail->exists ? $detail->id : null,
            )) {
                throw ValidationException::withMessages([
                    'item_id' => 'This purchase request item is already on another petty cash reimbursement.',
                ]);
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendors::class, 'Vendor_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrders::class, 'po_id');
    }

    public function items()
    {
        return $this->belongsTo(Item::class, 'item_id');
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
