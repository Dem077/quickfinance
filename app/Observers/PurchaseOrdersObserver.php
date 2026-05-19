<?php

namespace App\Observers;

use App\Enums\PurchaseOrderStatus;
use App\Models\BudgetTransactionHistory;
use App\Models\PurchaseOrders;
use Illuminate\Support\Facades\Auth;

class PurchaseOrdersObserver
{
    /**
     * Handle the PurchaseOrders "created" event.
     */
    public function created(PurchaseOrders $purchaseOrders): void
    {
        //
    }

    /**
     * Handle the PurchaseOrders "updated" event.
     */
    public function updated(PurchaseOrders $purchaseOrders): void
    {
        if ($purchaseOrders->payment_method == 'purchase_order' && $purchaseOrders->status === PurchaseOrderStatus::Closed) {
            foreach ($purchaseOrders->purchaseOrderDetails as $detail) {
                // Reduce the allocation for the department tied to this purchase order detail.
                $allocation = $detail->budgetAccount
                    ->allocations()
                    ->where('department_id', $purchaseOrders->purchaseRequest?->department_id)
                    ->first();

                if ($allocation) {
                    $newAmount = $allocation->amount - $detail->amount;
                    $allocation->update(['amount' => $newAmount]);

                    BudgetTransactionHistory::createtransaction(
                        $detail->budgetAccount->id,
                        'Purchase Order',
                        $detail->amount,
                        $detail->budgetAccount->allocations()->sum('amount'),
                        'Purchase Order Closed for PO ('.$purchaseOrders->po_no.' | Item: '.$detail->desc.' )',
                        Auth::id()
                    );
                }
            }
        }
    }

    /**
     * Handle the PurchaseOrders "deleted" event.
     */
    public function deleted(PurchaseOrders $purchaseOrders): void
    {
        //
    }

    /**
     * Handle the PurchaseOrders "restored" event.
     */
    public function restored(PurchaseOrders $purchaseOrders): void
    {
        //
    }

    /**
     * Handle the PurchaseOrders "force deleted" event.
     */
    public function forceDeleted(PurchaseOrders $purchaseOrders): void
    {
        //
    }
}
