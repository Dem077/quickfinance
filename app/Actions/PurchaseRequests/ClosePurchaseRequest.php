<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequests;
use Illuminate\Validation\ValidationException;

class ClosePurchaseRequest extends Action
{
    public function handle(PurchaseRequests $pr, int $userId, array $purchaseOrderGrns = []): PurchaseRequests
    {
        if ($pr->status !== PurchaseRequestsStatus::MD_DMD_Approved) {
            throw ValidationException::withMessages(['status' => 'Only MD/DMD-approved PRs can be closed.']);
        }

        if ($purchaseOrderGrns !== []) {
            $pr->applyGrnNumbersForClose($purchaseOrderGrns);
        }

        $pr->update([
            'status' => PurchaseRequestsStatus::Closed,
            'is_closed_by' => $userId,
        ]);

        return $pr->fresh();
    }
}
