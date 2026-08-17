<?php

namespace App\Actions\AdvanceForms;

use App\Enums\AdvanceFormStatus;
use App\Models\AdvanceForm;
use App\Models\PurchaseOrders;
use App\Models\User;

class RejectAdvanceFormByHod extends ApproveAdvanceFormByHod
{
    public function handle(PurchaseOrders $purchaseOrder, User $actor): AdvanceForm
    {
        $advanceForm = $this->assertHodCanAct($purchaseOrder, $actor);

        $advanceForm->update([
            'status' => AdvanceFormStatus::HOD_Rejected,
        ]);

        return $advanceForm->fresh();
    }
}
