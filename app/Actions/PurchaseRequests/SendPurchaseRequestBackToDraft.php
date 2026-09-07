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
        $allowed = [
            PurchaseRequestsStatus::Submitted,
            PurchaseRequestsStatus::HODApproved,
        ];

        if (! in_array($pr->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted or HOD-approved PRs can be sent back to draft.',
            ]);
        }

        $pr->update(['status' => PurchaseRequestsStatus::Draft]);

        return $pr->fresh();
    }
}
