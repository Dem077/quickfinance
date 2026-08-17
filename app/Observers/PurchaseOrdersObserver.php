<?php

namespace App\Observers;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrders;

class PurchaseOrdersObserver
{
    /**
     * Handle the PurchaseOrders "updated" event.
     */
    public function updated(PurchaseOrders $purchaseOrders): void
    {
        if (! $purchaseOrders->wasChanged('status')) {
            return;
        }

        // Regular PO closed: deduct line amounts from department allocation + history.
        if (
            $purchaseOrders->payment_method === 'purchase_order'
            && $purchaseOrders->status === PurchaseOrderStatus::Closed
        ) {
            $purchaseOrders->deductDepartmentAllocations($purchaseOrders->is_closed_by);

            return;
        }

        // Petty cash: release PR on-hold for covered items (no allocation deduct here).
        if (
            $purchaseOrders->payment_method === 'petty_cash'
            && in_array($purchaseOrders->status, [
                PurchaseOrderStatus::WaitingReimbursement,
                PurchaseOrderStatus::Reimbursed,
                PurchaseOrderStatus::Submitted,
            ], true)
        ) {
            $purchaseOrders->releasePurchaseRequestPendingHold();
        }
    }

    public function created(PurchaseOrders $purchaseOrders): void
    {
        //
    }

    public function deleted(PurchaseOrders $purchaseOrders): void
    {
        //
    }

    public function restored(PurchaseOrders $purchaseOrders): void
    {
        //
    }

    public function forceDeleted(PurchaseOrders $purchaseOrders): void
    {
        //
    }
}
