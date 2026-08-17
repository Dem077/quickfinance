<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Mail\StatusEmail;
use App\Models\PurchaseRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RejectPurchaseRequestByHod extends Action
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
            'is_approved_by_hod' => true,
            'approved_by_hod' => $hodUserId,
            'status' => PurchaseRequestsStatus::HODRejected,
        ]);

        if ($pr->user?->email) {
            Mail::to($pr->user->email)->queue(new StatusEmail('Purchase Request '.$pr->pr_no, 'rejected', '', 'HOD'));
        }

        return $pr->fresh();
    }
}
