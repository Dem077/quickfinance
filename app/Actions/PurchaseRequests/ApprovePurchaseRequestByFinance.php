<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequests;
use Illuminate\Validation\ValidationException;

class ApprovePurchaseRequestByFinance extends Action
{
    public function handle(PurchaseRequests $pr, int $userId): PurchaseRequests
    {
        if ($pr->status !== PurchaseRequestsStatus::HODApproved) {
            throw ValidationException::withMessages(['status' => 'PR is not awaiting finance approval.']);
        }

        $pr->update([
            'status' => PurchaseRequestsStatus::Approved,
            'approved_canceled_by' => $userId,
        ]);

        return $pr->fresh();
    }
}
