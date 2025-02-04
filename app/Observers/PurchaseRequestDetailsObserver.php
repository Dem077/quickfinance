<?php

namespace App\Observers;

use App\Models\PurchaseRequestDetails;
use Illuminate\Support\Facades\Log;

class PurchaseRequestDetailsObserver
{
    /**
     * Handle the PurchaseRequestDetails "created" event.
     */
    public function created(PurchaseRequestDetails $purchaseRequestDetails): void
    {
    }

    /**
     * Handle the PurchaseRequestDetails "updated" event.
     */
    public function updated(PurchaseRequestDetails $purchaseRequestDetails): void
    {
    // Debug logging
    Log::info('PurchaseRequestDetails updated', [
        'id' => $purchaseRequestDetails->id,
        'is_utilized' => $purchaseRequestDetails->is_utilized,
        'isDirty' => $purchaseRequestDetails->isDirty('is_utilized')
    ]);

    if ($purchaseRequestDetails->isDirty('is_utilized') && $purchaseRequestDetails->is_utilized) {
        $purchaseRequestDetails->purchaseRequest->checkAndUpdateClosedStatus();
    }
    }

    /**
     * Handle the PurchaseRequestDetails "deleted" event.
     */
    public function deleted(PurchaseRequestDetails $purchaseRequestDetails): void
    {
        //
    }

    /**
     * Handle the PurchaseRequestDetails "restored" event.
     */
    public function restored(PurchaseRequestDetails $purchaseRequestDetails): void
    {
        //
    }

    /**
     * Handle the PurchaseRequestDetails "force deleted" event.
     */
    public function forceDeleted(PurchaseRequestDetails $purchaseRequestDetails): void
    {
        //
    }
}
