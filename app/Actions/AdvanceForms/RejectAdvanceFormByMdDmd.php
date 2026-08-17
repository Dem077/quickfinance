<?php

namespace App\Actions\AdvanceForms;

use App\Enums\AdvanceFormStatus;
use App\Models\AdvanceForm;
use App\Models\PurchaseOrders;
use App\Models\User;

class RejectAdvanceFormByMdDmd extends ApproveAdvanceFormByMdDmd
{
    public function handle(PurchaseOrders $purchaseOrder, User $actor): AdvanceForm
    {
        $advanceForm = $this->assertMdDmdCanAct($purchaseOrder);

        $advanceForm->update([
            'status' => AdvanceFormStatus::DMD_MD_Rejected,
        ]);

        return $advanceForm->fresh();
    }
}
