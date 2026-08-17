<?php

namespace App\Actions\AdvanceForms;

use App\Actions\Action;
use App\Enums\AdvanceFormStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\AdvanceForm;
use App\Models\PurchaseOrders;
use Illuminate\Validation\ValidationException;

class SubmitAdvanceForm extends Action
{
    public function handle(PurchaseOrders $purchaseOrder, int $userId): AdvanceForm
    {
        $advanceForm = $purchaseOrder->advanceForm;

        if (! $advanceForm) {
            throw ValidationException::withMessages([
                'advance_form' => 'No advance form to submit.',
            ]);
        }

        if (
            ! $purchaseOrder->is_advance_form_required
            || $purchaseOrder->status !== PurchaseOrderStatus::Submitted
            || $purchaseOrder->payment_method !== 'purchase_order'
            || $advanceForm->status !== AdvanceFormStatus::Draft
        ) {
            throw ValidationException::withMessages([
                'advance_form' => 'Advance form cannot be submitted in its current state.',
            ]);
        }

        if ((int) $advanceForm->generated_by !== $userId) {
            throw ValidationException::withMessages([
                'advance_form' => 'Only the generator can submit this advance form.',
            ]);
        }

        $advanceForm->update([
            'status' => AdvanceFormStatus::Submitted,
        ]);

        return $advanceForm->fresh();
    }
}
