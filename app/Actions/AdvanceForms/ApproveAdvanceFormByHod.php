<?php

namespace App\Actions\AdvanceForms;

use App\Actions\Action;
use App\Enums\AdvanceFormStatus;
use App\Models\AdvanceForm;
use App\Models\PurchaseOrders;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApproveAdvanceFormByHod extends Action
{
    public function handle(PurchaseOrders $purchaseOrder, User $actor): AdvanceForm
    {
        $advanceForm = $this->assertHodCanAct($purchaseOrder, $actor);

        $advanceForm->update([
            'status' => AdvanceFormStatus::HOD_Approved,
            'hod_approved_by' => $actor->id,
        ]);

        return $advanceForm->fresh();
    }

    protected function assertHodCanAct(PurchaseOrders $purchaseOrder, User $actor): AdvanceForm
    {
        $advanceForm = $purchaseOrder->advanceForm;

        if (! $advanceForm) {
            throw ValidationException::withMessages([
                'advance_form' => 'No advance form found.',
            ]);
        }

        if (
            ! $purchaseOrder->is_advance_form_required
            || $purchaseOrder->payment_method !== 'purchase_order'
            || $advanceForm->status !== AdvanceFormStatus::Submitted
        ) {
            throw ValidationException::withMessages([
                'advance_form' => 'Advance form is not awaiting HOD approval.',
            ]);
        }

        $advanceForm->loadMissing('user.department.user');

        $hodId = $advanceForm->user?->department?->user?->id;

        if (! $hodId || (int) $hodId !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'advance_form' => 'You are not the HOD for the generator\'s department.',
            ]);
        }

        return $advanceForm;
    }
}
