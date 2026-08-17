<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequests;
use Illuminate\Validation\ValidationException;

class CancelPurchaseRequest extends Action
{
    public function handle(PurchaseRequests $pr, int $userId, string $cancelRemark): PurchaseRequests
    {
        if ($pr->status === PurchaseRequestsStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Draft PRs cannot be canceled this way.']);
        }

        $pr->update([
            'status' => PurchaseRequestsStatus::Canceled,
            'cancel_remark' => $cancelRemark,
            'approved_canceled_by' => $userId,
        ]);

        return $pr->fresh();
    }
}
