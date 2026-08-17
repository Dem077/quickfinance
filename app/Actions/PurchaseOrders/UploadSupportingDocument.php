<?php

namespace App\Actions\PurchaseOrders;

use App\Actions\Action;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrders;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class UploadSupportingDocument extends Action
{
    public function handle(PurchaseOrders $purchaseOrder, UploadedFile $file): PurchaseOrders
    {
        if ($purchaseOrder->payment_method !== 'petty_cash') {
            throw ValidationException::withMessages([
                'supporting_document' => 'Receipts can only be uploaded for petty cash records.',
            ]);
        }

        if ($purchaseOrder->supporting_document) {
            throw ValidationException::withMessages([
                'supporting_document' => 'A receipt has already been uploaded.',
            ]);
        }

        if ($purchaseOrder->status === PurchaseOrderStatus::Closed) {
            throw ValidationException::withMessages([
                'supporting_document' => 'Cannot upload a receipt for a closed record.',
            ]);
        }

        $path = $file->store('purchase-order-receipts', 'public');

        $purchaseOrder->update([
            'supporting_document' => $path,
        ]);

        return $purchaseOrder->fresh();
    }
}
