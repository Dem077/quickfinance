<?php

namespace App\Actions\AdvanceForms;

use App\Actions\Action;
use App\Enums\AdvanceFormStatus;
use App\Models\AdvanceForm;
use App\Models\PurchaseOrders;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApproveAdvanceFormByMdDmd extends Action
{
    public function handle(PurchaseOrders $purchaseOrder, User $actor): AdvanceForm
    {
        $advanceForm = $this->assertMdDmdCanAct($purchaseOrder);

        $advanceForm->update([
            'status' => AdvanceFormStatus::DMD_MD_Approved,
            'md_dmd_approved_by' => $actor->id,
        ]);

        return $advanceForm->fresh();
    }

    protected function assertMdDmdCanAct(PurchaseOrders $purchaseOrder): AdvanceForm
    {
        $advanceForm = $purchaseOrder->advanceForm;

        if (! $advanceForm) {
            throw ValidationException::withMessages([
                'advance_form' => 'No advance form found.',
            ]);
        }

        if ($advanceForm->status !== AdvanceFormStatus::HOD_Approved) {
            throw ValidationException::withMessages([
                'advance_form' => 'Advance form is not awaiting MD/DMD approval.',
            ]);
        }

        return $advanceForm;
    }
}
