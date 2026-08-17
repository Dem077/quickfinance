<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequests;
use Illuminate\Validation\ValidationException;

class RejectPurchaseRequestByMdDmd extends Action
{
    public function handle(PurchaseRequests $pr, int $userId): PurchaseRequests
    {
        if ($pr->status !== PurchaseRequestsStatus::Approved) {
            throw ValidationException::withMessages(['status' => 'PR is not awaiting MD/DMD approval.']);
        }

        $pr->update([
            'status' => PurchaseRequestsStatus::MD_DMD_Rejected,
            'approved_by_md_dmd' => $userId,
        ]);

        return $pr->fresh();
    }
}
