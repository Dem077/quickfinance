<?php

namespace App\Services\AssetReceipt;

use App\Enums\AssetReceiptStatus;
use App\Enums\ItemTypeEnum;
use App\Models\AssetReceipt;
use App\Services\SnipeIt\SnipeItCreatedRecord;
use App\Services\SnipeIt\SnipeItException;
use App\Services\SnipeIt\SnipeItService;
use Illuminate\Support\Facades\Auth;

class AssetReceiptReceiver
{
    public function __construct(
        protected SnipeItService $snipeIt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributesFromFormData(array $data, ?array $perAsset = null, ?ItemTypeEnum $type = null): array
    {
        $perAsset ??= [];
        $type ??= ItemTypeEnum::Asset;

        if ($type === ItemTypeEnum::Accessory) {
            return [
                'name' => $perAsset['name'] ?? $data['name'] ?? null,
                'asset_description' => $perAsset['name'] ?? $data['name'] ?? null,
                'snipe_category_id' => $data['snipe_category_id'] ?? null,
                'snipe_quantity' => $perAsset['snipe_quantity'] ?? $data['snipe_quantity'] ?? null,
                'snipe_location_id' => $data['snipe_location_id'] ?? null,
                'snipe_supplier_id' => $data['snipe_supplier_id'] ?? null,
                'order_number' => $data['order_number'] ?? null,
                'invoice_number' => $data['order_number'] ?? null,
                'purchase_date' => $data['purchase_date'] ?? null,
                'purchase_cost' => $perAsset['purchase_cost'] ?? $data['purchase_cost'] ?? null,
                'model_number' => $data['model_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
        }

        return [
            'name' => $perAsset['name'] ?? $data['name'] ?? null,
            'asset_description' => $perAsset['name'] ?? $data['name'] ?? null,
            'serial_number' => $perAsset['serial_number'] ?? $data['serial_number'] ?? null,
            'snipe_model_id' => $data['snipe_model_id'] ?? null,
            'snipe_status_id' => $data['snipe_status_id'] ?? null,
            'snipe_location_id' => $data['snipe_location_id'] ?? null,
            'snipe_supplier_id' => $data['snipe_supplier_id'] ?? null,
            'order_number' => $data['order_number'] ?? null,
            'invoice_number' => $data['order_number'] ?? null,
            'purchase_date' => $data['purchase_date'] ?? null,
            'purchase_cost' => $perAsset['purchase_cost'] ?? $data['purchase_cost'] ?? null,
            'notes' => $data['notes'] ?? null,
            'cao_asset_code' => $data['cao_asset_code'] ?? null,
            'finance_old_asset_tag' => $data['finance_old_asset_tag'] ?? null,
            'asset_class' => $data['asset_class'] ?? null,
            'mac_address' => $perAsset['mac_address'] ?? $data['mac_address'] ?? null,
        ];
    }

    /**
     * @throws SnipeItException
     */
    public function receive(AssetReceipt $receipt, array $attributes): SnipeItCreatedRecord
    {
        if ($receipt->status !== AssetReceiptStatus::Pending) {
            throw new SnipeItException('This item has already been received.');
        }

        $receipt->loadMissing('item');
        $type = $receipt->item?->type ?? ItemTypeEnum::Asset;

        $created = $this->snipeIt->createFromReceipt(
            $receipt->fill($attributes)
        );

        $update = [
            ...$attributes,
            'status' => AssetReceiptStatus::Received,
            'received_by' => Auth::id(),
            'received_at' => now(),
        ];

        if ($created->isHardware()) {
            $update['asset_tag'] = $created->assetTag;
            $update['snipe_it_hardware_id'] = $created->id;
        } else {
            $update['snipe_it_accessory_id'] = $created->id;
        }

        $receipt->update($update);

        return $created;
    }
}
