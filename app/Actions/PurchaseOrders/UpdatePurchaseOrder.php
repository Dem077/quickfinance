<?php

namespace App\Actions\PurchaseOrders;

use App\Actions\Action;
use App\Enums\PurchaseOrderStatus;
use App\Models\PettyCashReimbursment;
use App\Models\PurchaseOrders;
use Illuminate\Validation\ValidationException;

class UpdatePurchaseOrder extends Action
{
    /**
     * @param  array{
     *     vendor_id: int,
     *     po_no?: string|null,
     *     date: string,
     *     pr_id: int,
     *     payment_method: string,
     *     is_advance_form_required?: bool|int
     * }  $data
     */
    public function handle(PurchaseOrders $purchaseOrder, array $data): PurchaseOrders
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase orders can be updated.',
            ]);
        }

        $poNo = $data['po_no'] ?? $purchaseOrder->po_no;

        if (($data['payment_method'] ?? '') === 'petty_cash' && ! empty($poNo)) {
            if ($poNo === PettyCashReimbursment::GENERATE_FORM_NO_OPTION) {
                $poNo = PettyCashReimbursment::resolveFormNoForProcure($poNo);
            }
        }

        $purchaseOrder->update([
            'vendor_id' => $data['vendor_id'],
            'po_no' => $poNo,
            'date' => $data['date'],
            'pr_id' => $data['pr_id'],
            'payment_method' => $data['payment_method'],
            'is_advance_form_required' => ($data['payment_method'] ?? '') === 'purchase_order'
                ? (bool) ($data['is_advance_form_required'] ?? false)
                : false,
        ]);

        return $purchaseOrder->fresh();
    }
}
