<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequests;
use App\Support\PurchaseRequestBudget;
use Illuminate\Validation\ValidationException;

class SubmitPurchaseRequestForApproval extends Action
{
    public function handle(PurchaseRequests $pr): PurchaseRequests
    {
        if ($pr->status !== PurchaseRequestsStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only draft PRs can be submitted.']);
        }

        PurchaseRequestBudget::assertCanSubmit($pr);

        $pr->update(['status' => PurchaseRequestsStatus::Submitted]);

        return $pr->fresh();
    }
}
