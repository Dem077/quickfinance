<?php

namespace App\Actions\PurchaseOrders;

use App\Actions\Action;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosePurchaseOrder extends Action
{
    /**
     * Close a submitted PO.
     * - purchase_order → Closed: PurchaseOrdersObserver deducts allocation + history
     * - petty_cash → WaitingReimbursement: observer releases PR pending hold (no deduct yet)
     *
     * @param  array{grn_number?: string|null}  $data
     */
    public function handle(PurchaseOrders $purchaseOrder, array $data, int $userId): PurchaseOrders
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted purchase orders can be closed.',
            ]);
        }

        if ($purchaseOrder->payment_method === 'petty_cash' && ! $purchaseOrder->supporting_document) {
            throw ValidationException::withMessages([
                'supporting_document' => 'Upload a receipt before closing a petty cash procure record.',
            ]);
        }

        if ($purchaseOrder->payment_method === 'purchase_order' && empty($data['grn_number'])) {
            throw ValidationException::withMessages([
                'grn_number' => 'GRN number is required to close a purchase order.',
            ]);
        }

        return DB::transaction(function () use ($purchaseOrder, $data, $userId): PurchaseOrders {
            if ($purchaseOrder->payment_method === 'petty_cash') {
                $purchaseOrder->update([
                    'status' => PurchaseOrderStatus::WaitingReimbursement,
                    'is_closed_by' => $userId,
                ]);
            } else {
                $purchaseOrder->syncAssetReceipts();

                $purchaseOrder->update([
                    'status' => PurchaseOrderStatus::Closed,
                    'is_closed_by' => $userId,
                    'grn_number' => $data['grn_number'],
                ]);
            }

            PurchaseRequests::checkAndUpdateClosedStatus($purchaseOrder->pr_id);

            return $purchaseOrder->fresh(['purchaseOrderDetails', 'advanceForm']);
        });
    }
}
