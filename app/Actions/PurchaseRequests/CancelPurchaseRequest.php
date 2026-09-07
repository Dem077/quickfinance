<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Mail\StatusEmail;
use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CancelPurchaseRequest extends Action
{
    public function handle(PurchaseRequests $pr, int $userId, string $cancelRemark): PurchaseRequests
    {
        if ($pr->status === PurchaseRequestsStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Draft PRs cannot be canceled this way.']);
        }

        $pr->loadMissing('user');

        $pr->update([
            'status' => PurchaseRequestsStatus::Canceled,
            'cancel_remark' => $cancelRemark,
            'approved_canceled_by' => $userId,
        ]);

        if ($pr->user?->email) {
            Mail::to($pr->user->email)->queue(new StatusEmail(
                'Purchase Request '.$pr->pr_no,
                'canceled',
                $cancelRemark,
                'Finance',
            ));
        }

        return $pr->fresh();
    }
}
