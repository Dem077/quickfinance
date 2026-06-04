<?php

namespace App\Services\AssetReceipt;

use App\Enums\AssetReceiptStatus;
use App\Models\AssetReceipt;
use App\Services\SnipeIt\SnipeItCreatedAsset;
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
    public function attributesFromFormData(array $data, ?array $perAsset = null): array
    {
        $perAsset ??= [];

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
    public function receive(AssetReceipt $receipt, array $attributes): SnipeItCreatedAsset
    {
        if ($receipt->status !== AssetReceiptStatus::Pending) {
            throw new SnipeItException('This asset unit has already been received.');
        }

        $created = $this->snipeIt->createAssetFromReceipt(
            $receipt->fill($attributes)
        );

        $receipt->update([
            ...$attributes,
            'asset_tag' => $created->assetTag,
            'snipe_it_hardware_id' => $created->hardwareId,
            'status' => AssetReceiptStatus::Received,
            'received_by' => Auth::id(),
            'received_at' => now(),
        ]);

        return $created;
    }
}
