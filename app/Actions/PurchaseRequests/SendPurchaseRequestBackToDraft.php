<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Mail\StatusEmail;
use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SendPurchaseRequestBackToDraft extends Action
{
    public function handle(PurchaseRequests $pr, string $cancelRemark, string $sentBackBy = ''): PurchaseRequests
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

        $pr->loadMissing('user');

        $by = $sentBackBy !== ''
            ? $sentBackBy
            : ($pr->status === PurchaseRequestsStatus::Submitted ? 'HOD' : 'Finance');

        $pr->update([
            'status' => PurchaseRequestsStatus::Draft,
        ]);

        if ($pr->user?->email) {
            Mail::to($pr->user->email)->queue(new StatusEmail(
                'Purchase Request '.$pr->pr_no,
                'rejected',
                $cancelRemark,
                $by,
                true,
            ));
        }

        return $pr->fresh();
    }
}
