<?php

namespace App\Actions\AssetManagement;

use App\Actions\Action;
use App\Enums\ItemTypeEnum;
use App\Models\AssetReceipt;
use App\Services\AssetReceipt\AssetReceiptReceiver;
use App\Services\SnipeIt\SnipeItCreatedRecord;
use App\Services\SnipeIt\SnipeItException;

class ReceiveAssetReceipt extends Action
{
    public function __construct(
        protected AssetReceiptReceiver $receiver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws SnipeItException
     */
    public function handle(AssetReceipt $receipt, array $data): SnipeItCreatedRecord
    {
        $receipt->loadMissing('item');
        $type = $receipt->item?->type ?? ItemTypeEnum::Asset;

        return $this->receiver->receive(
            $receipt,
            $this->receiver->attributesFromFormData($data, type: $type)
        );
    }
}
