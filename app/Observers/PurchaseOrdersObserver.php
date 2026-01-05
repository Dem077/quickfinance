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
                $detail->budgetAccount->update([
                    'amount' => $detail->budgetAccount->amount - $detail->amount, ]);
                BudgetTransactionHistory::createtransaction($detail->budgetAccount->id, 'Purchase Order', $detail->amount, $detail->budgetAccount->amount, 'Purchase Order Closed for PO ('.$record->po_no.' | Item: '.$detail->desc.' )', Auth::id());
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
