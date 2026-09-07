<?php

namespace App\Actions\PurchaseOrders;

use App\Actions\Action;
use App\Enums\PurchaseOrderStatus;
use App\Models\Item;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPurchaseOrder extends Action
{
    public function handle(PurchaseOrders $purchaseOrder): PurchaseOrders
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase orders can be submitted.',
            ]);
        }

        return DB::transaction(function () use ($purchaseOrder): PurchaseOrders {
            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Submitted,
                'is_submitted' => true,
            ]);

            $purchaseOrder->syncAssetReceipts();

            foreach ($purchaseOrder->purchaseOrderDetails as $detail) {
                $item = Item::query()->where('item_code', $detail->itemcode)->first();

                if ($item) {
                    PurchaseRequestDetails::query()
                        ->where('item_id', $item->id)
                        ->where('pr_id', $purchaseOrder->pr_id)
                        ->update(['is_utilized' => true]);
                }
            }

            return $purchaseOrder->fresh(['purchaseOrderDetails', 'advanceForm']);
        });
    }
}
