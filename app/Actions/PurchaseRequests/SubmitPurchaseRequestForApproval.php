<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Mail\NotificationEmail;
use App\Models\PurchaseRequests;
use App\Support\PurchaseRequestBudget;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SubmitPurchaseRequestForApproval extends Action
{
    public function handle(PurchaseRequests $pr): PurchaseRequests
    {
        if ($pr->status !== PurchaseRequestsStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only draft PRs can be submitted.']);
        }

        PurchaseRequestBudget::assertCanSubmit($pr);

        $pr->update(['status' => PurchaseRequestsStatus::Submitted]);

        $hodEmail = $pr->user?->department?->user?->email;
        if ($hodEmail) {
            Mail::to($hodEmail)->queue(new NotificationEmail('Purchase Request '.$pr->pr_no));
        }

        return $pr->fresh();
    }
}
