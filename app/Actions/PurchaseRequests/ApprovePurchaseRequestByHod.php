<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequests;
use Illuminate\Validation\ValidationException;

class ApprovePurchaseRequestByHod extends Action
{
    public function handle(PurchaseRequests $pr, int $hodUserId): PurchaseRequests
    {
        $hodId = $pr->user?->department?->user?->id;
        if ((int) $hodId !== $hodUserId) {
            throw ValidationException::withMessages(['status' => 'Only the department HOD can perform this action.']);
        }

        if ($pr->status !== PurchaseRequestsStatus::Submitted) {
            throw ValidationException::withMessages(['status' => 'PR is not awaiting HOD approval.']);
        }

        $pr->update([
            'approved_by_hod' => $hodUserId,
            'status' => PurchaseRequestsStatus::HODApproved,
        ]);

        return $pr->fresh();
    }
}
