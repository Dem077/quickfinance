<?php

namespace App\Actions\PurchaseOrders;

use App\Actions\Action;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletePurchaseOrder extends Action
{
    public function handle(PurchaseOrders $purchaseOrder): void
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase orders can be deleted.',
            ]);
        }

        DB::transaction(function () use ($purchaseOrder): void {
            $prId = $purchaseOrder->pr_id;
            $purchaseOrder->loadMissing('purchaseOrderDetails');

            foreach ($purchaseOrder->purchaseOrderDetails as $detail) {
                $item = $detail->itemcode;
                $pr = PurchaseRequests::query()->find($prId);

                if ($pr) {
                    $pr->purchaseRequestDetails()
                        ->whereHas('items', function ($query) use ($item): void {
                            $query->where('item_code', $item);
                        })
                        ->update(['is_utilized' => false]);
                }
            }

            $purchaseOrder->purchaseOrderDetails()->delete();
            $purchaseOrder->delete();
        });
    }
}
