<?php

namespace App\Actions\PurchaseOrders;

use App\Actions\Action;
use App\Enums\PurchaseOrderStatus;
use App\Models\Item;
use App\Models\PurchaseOrderDetails;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrder extends Action
{
    /**
     * @param  array{
     *     vendor_id: int,
     *     po_no?: string|null,
     *     date: string,
     *     pr_id: int,
     *     payment_method: string,
     *     is_advance_form_required?: bool|int,
     *     details: array<int, array{
     *         itemcode: string,
     *         unit_measure: string,
     *         qty: float|int,
     *         unit_price: float|int,
     *         tax_amount: float|int,
     *         amount: float|int,
     *         budget_account_id: int
     *     }>
     * }  $data
     */
    public function handle(array $data): PurchaseOrders
    {
        if (empty($data['details'])) {
            throw ValidationException::withMessages([
                'details' => 'At least one line item is required.',
            ]);
        }

        return DB::transaction(function () use ($data): PurchaseOrders {
            $paymentMethod = $data['payment_method'] ?? '';
            $poNo = $paymentMethod === 'petty_cash'
                ? null
                : ($data['po_no'] ?? null);

            $purchaseOrder = PurchaseOrders::create([
                'vendor_id' => $data['vendor_id'],
                'po_no' => $poNo,
                'date' => $data['date'],
                'pr_id' => $data['pr_id'],
                'payment_method' => $paymentMethod,
                'is_advance_form_required' => $paymentMethod === 'purchase_order'
                    ? (bool) ($data['is_advance_form_required'] ?? false)
                    : false,
                'status' => PurchaseOrderStatus::Draft,
            ]);

            $pr = PurchaseRequests::query()->findOrFail($data['pr_id']);

            foreach ($data['details'] as $detail) {
                $item = Item::query()->where('item_code', $detail['itemcode'])->firstOrFail();

                $pr->purchaseRequestDetails()
                    ->where('item_id', $item->id)
                    ->update(['is_utilized' => true]);

                PurchaseOrderDetails::create([
                    'po_id' => $purchaseOrder->id,
                    'item_id' => $item->id,
                    'itemcode' => $detail['itemcode'],
                    'desc' => $item->name,
                    'budget_account_id' => $detail['budget_account_id'],
                    'unit_measure' => $detail['unit_measure'],
                    'qty' => $detail['qty'],
                    'unit_price' => $detail['unit_price'],
                    'tax_amount' => $detail['tax_amount'],
                    'amount' => $detail['amount'],
                ]);
            }

            return $purchaseOrder->fresh(['purchaseOrderDetails']);
        });
    }
}
