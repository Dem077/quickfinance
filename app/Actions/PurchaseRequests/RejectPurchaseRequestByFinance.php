<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Mail\StatusEmail;
use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RejectPurchaseRequestByFinance extends Action
{
    public function handle(PurchaseRequests $pr, int $userId, string $cancelRemark): PurchaseRequests
    {
        if ($pr->status !== PurchaseRequestsStatus::HODApproved) {
            throw ValidationException::withMessages(['status' => 'PR is not awaiting finance approval.']);
        }

        $pr->update([
            'status' => PurchaseRequestsStatus::Rejected,
            'cancel_remark' => $cancelRemark,
            'approved_canceled_by' => $userId,
        ]);

        if ($pr->user?->email) {
            Mail::to($pr->user->email)->queue(new StatusEmail('Purchase Request '.$pr->pr_no, 'rejected', $cancelRemark, 'Finance'));
        }

        return $pr->fresh();
    }
}
