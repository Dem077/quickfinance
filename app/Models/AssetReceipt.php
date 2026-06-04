<?php

namespace App\Models;

use App\Enums\AssetReceiptStatus;
use App\Enums\ItemTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetReceipt extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'purchase_order_detail_id',
        'unit_index',
        'item_id',
        'status',
        'asset_tag',
        'name',
        'asset_description',
        'serial_number',
        'snipe_model_id',
        'snipe_status_id',
        'snipe_location_id',
        'snipe_supplier_id',
        'snipe_category_id',
        'snipe_quantity',
        'model',
        'model_number',
        'order_number',
        'invoice_number',
        'purchase_date',
        'purchase_cost',
        'notes',
        'cao_asset_code',
        'finance_old_asset_tag',
        'asset_class',
        'mac_address',
        'snipe_it_hardware_id',
        'snipe_it_accessory_id',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'status' => AssetReceiptStatus::class,
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrders::class, 'purchase_order_id');
    }

    public function purchaseOrderDetail(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderDetails::class, 'purchase_order_detail_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isAccessoryLine(): bool
    {
        $this->loadMissing('item');

        return $this->item?->type === ItemTypeEnum::Accessory;
    }

    public function unitLabel(): ?string
    {
        if ($this->isAccessoryLine()) {
            $qty = $this->snipe_quantity ?? $this->purchaseOrderDetail?->assetLineQuantity() ?? 1;

            return 'Qty '.$qty;
        }

        $this->loadMissing('purchaseOrderDetail');

        $total = $this->purchaseOrderDetail?->assetLineQuantity() ?? 1;

        if ($total <= 1) {
            return null;
        }

        return 'Unit '.($this->unit_index ?? 1).' of '.$total;
    }

    public function defaultUnitPurchaseCost(): ?float
    {
        $this->loadMissing('purchaseOrderDetail');

        $detail = $this->purchaseOrderDetail;

        if (! $detail || $detail->amount === null) {
            return null;
        }

        $quantity = $detail->assetLineQuantity();

        return round((float) $detail->amount / $quantity, 2);
    }
}
