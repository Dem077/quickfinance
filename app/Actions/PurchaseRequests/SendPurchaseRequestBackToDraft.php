<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequests;
use Illuminate\Validation\ValidationException;

class SendPurchaseRequestBackToDraft extends Action
{
    public function handle(PurchaseRequests $pr): PurchaseRequests
    {
        if ($pr->status !== PurchaseRequestsStatus::HODApproved) {
            throw ValidationException::withMessages(['status' => 'Only HOD-approved PRs can be sent back to draft.']);
        }

        $pr->update(['status' => PurchaseRequestsStatus::Draft]);

        return $pr->fresh();
    }
}
