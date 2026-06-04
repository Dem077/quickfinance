<?php

namespace App\Models;

use App\Enums\AssetReceiptStatus;
use App\Enums\ItemTypeEnum;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrders extends Model
{
    protected $fillable = [
        'vendor_id',
        'po_no',
        'date',
        'pr_id',
        'payment_method',
        'grn_number',
        'is_submitted',
        'is_closed',
        'is_closed_by',
        'supporting_document',
        'advance_form_id',
        'is_advance_form_required',
        'is_reimbursed',
        'status',
    ];

    protected $casts = [
        'status' => PurchaseOrderStatus::class,
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

    public function assetReceipts(): HasMany
    {
        return $this->hasMany(AssetReceipt::class, 'purchase_order_id')
            ->orderBy('purchase_order_detail_id')
            ->orderBy('unit_index');
    }

    public function syncAssetReceipts(): void
    {
        if ($this->payment_method !== 'purchase_order') {
            return;
        }

        $this->loadMissing('purchaseOrderDetails.items');

        foreach ($this->purchaseOrderDetails as $detail) {
            $item = $detail->resolvedItem();

            if (! $item || ! $item->type->syncsToSnipeIt()) {
                continue;
            }

            if ($item->type === ItemTypeEnum::Accessory) {
                AssetReceipt::firstOrCreate(
                    [
                        'purchase_order_detail_id' => $detail->id,
                        'unit_index' => 1,
                    ],
                    [
                        'purchase_order_id' => $this->id,
                        'item_id' => $item->id,
                        'status' => AssetReceiptStatus::Pending,
                        'snipe_quantity' => $detail->assetLineQuantity(),
                    ]
                );

                AssetReceipt::query()
                    ->where('purchase_order_detail_id', $detail->id)
                    ->where('unit_index', '>', 1)
                    ->where('status', AssetReceiptStatus::Pending)
                    ->delete();

                continue;
            }

            $quantity = $detail->assetLineQuantity();

            for ($unitIndex = 1; $unitIndex <= $quantity; $unitIndex++) {
                AssetReceipt::firstOrCreate(
                    [
                        'purchase_order_detail_id' => $detail->id,
                        'unit_index' => $unitIndex,
                    ],
                    [
                        'purchase_order_id' => $this->id,
                        'item_id' => $item->id,
                        'status' => AssetReceiptStatus::Pending,
                    ]
                );
            }

            AssetReceipt::query()
                ->where('purchase_order_detail_id', $detail->id)
                ->where('unit_index', '>', $quantity)
                ->where('status', AssetReceiptStatus::Pending)
                ->delete();
        }
    }

    public function hasPendingAssetReceipts(): bool
    {
        return $this->assetReceipts()
            ->where('status', AssetReceiptStatus::Pending)
            ->exists();
    }

    public function advanceForm(): BelongsTo
    {
        return $this->belongsTo(AdvanceForm::class, 'advance_form_id');
    }

    public function pettyCashReimbursment(): HasMany
    {
        return $this->hasMany(PettyCashReimbursment::class, 'po_id');
    }
}
