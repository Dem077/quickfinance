<?php

namespace App\Models;

use App\Enums\UnitsEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestDetails extends Model
{
    protected $fillable = [
        'item_id',
        'unit',
        'budget_account_id',
        'amount',
        'pr_id',
        'is_utilized',
        'est_cost',
    ];

    protected function unit(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => UnitsEnum::resolve($value),
            set: function (UnitsEnum|string|null $value) {
                if ($value instanceof UnitsEnum) {
                    return $value->value;
                }

                if ($value === null || $value === '') {
                    return null;
                }

                return UnitsEnum::resolve((string) $value)?->value ?? $value;
            },
        );
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequests::class, 'pr_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderDetails::class, 'item_id');
    }

    public function items(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function budgetAccount(): BelongsTo
    {
        return $this->belongsTo(SubBudgetAccounts::class, 'budget_account_id');
    }
}
