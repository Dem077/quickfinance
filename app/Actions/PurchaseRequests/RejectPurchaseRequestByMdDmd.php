<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Mail\StatusEmail;
use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RejectPurchaseRequestByMdDmd extends Action
{
    public function handle(PurchaseRequests $pr, int $userId, string $cancelRemark): PurchaseRequests
    {
        if ($pr->status !== PurchaseRequestsStatus::Approved) {
            throw ValidationException::withMessages(['status' => 'PR is not awaiting MD/DMD approval.']);
        }

        $pr->loadMissing('user');

        $pr->update([
            'status' => PurchaseRequestsStatus::MD_DMD_Rejected,
            'cancel_remark' => $cancelRemark,
            'approved_by_md_dmd' => $userId,
        ]);

        if ($pr->user?->email) {
            Mail::to($pr->user->email)->queue(new StatusEmail(
                'Purchase Request '.$pr->pr_no,
                'rejected',
                $cancelRemark,
                'MD / DMD',
            ));
        }

        return $pr->fresh();
    }
}
