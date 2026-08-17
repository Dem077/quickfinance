<?php

namespace App\Actions\AdvanceForms;

use App\Actions\Action;
use App\Enums\AdvanceFormStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\AdvanceForm;
use App\Models\PurchaseOrders;
use Illuminate\Validation\ValidationException;

class RegenerateAdvanceForm extends Action
{
    /**
     * @param  array{qoation_no: string, expected_delivery: string|int, advance_amount: float|int}  $data
     */
    public function handle(PurchaseOrders $purchaseOrder, array $data, int $userId): AdvanceForm
    {
        $advanceForm = $purchaseOrder->advanceForm;

        if (! $advanceForm) {
            throw ValidationException::withMessages([
                'advance_form' => 'No advance form exists to regenerate.',
            ]);
        }

        if (
            ! $purchaseOrder->is_advance_form_required
            || $purchaseOrder->status !== PurchaseOrderStatus::Submitted
            || $purchaseOrder->payment_method !== 'purchase_order'
            || $advanceForm->status !== AdvanceFormStatus::Draft
        ) {
            throw ValidationException::withMessages([
                'advance_form' => 'Advance form cannot be regenerated in its current state.',
            ]);
        }

        $total = (float) $purchaseOrder->purchaseOrderDetails()->sum('amount');
        $percentage = (float) $data['advance_amount'];
        $advanceAmount = ($percentage / 100) * $total;

        $advanceForm->update([
            'qoation_no' => $data['qoation_no'],
            'expected_delivery' => $data['expected_delivery'],
            'advance_percentage' => $percentage,
            'advance_amount' => $advanceAmount,
            'balance_amount' => $total - $advanceAmount,
            'generated_by' => $userId,
        ]);

        return $advanceForm->fresh();
    }
}
