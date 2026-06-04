<?php

namespace App\Models;

use App\Enums\ItemTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function assetReceipts(): HasMany
    {
        return $this->hasMany(AssetReceipt::class, 'purchase_order_detail_id');
    }

    public function assetLineQuantity(): int
    {
        return max(1, (int) round((float) $this->qty));
    }

    public function resolvedItem(): ?Item
    {
        $this->loadMissing('items');

        if ($this->items) {
            return $this->items;
        }

        if (! $this->itemcode) {
            return null;
        }

        return Item::query()->where('item_code', $this->itemcode)->first();
    }

    public function scopeWhereSnipeItItem(Builder $query): Builder
    {
        $types = [ItemTypeEnum::Asset->value, ItemTypeEnum::Accessory->value];

        return $query->where(function (Builder $query) use ($types): void {
            $query->whereHas('items', fn (Builder $itemQuery): Builder => $itemQuery
                ->whereIn('type', $types))
                ->orWhereExists(function ($subquery) use ($types): void {
                    $subquery->selectRaw('1')
                        ->from('items')
                        ->whereColumn('items.item_code', 'purchase_order_details.itemcode')
                        ->whereIn('items.type', $types);
                });
        });
    }
}
