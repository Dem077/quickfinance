<?php

namespace App\Actions\PettyCash;

use App\Actions\Action;
use App\Enums\PettyCashStatus;
use App\Mail\NotificationEmail;
use App\Models\PettyCashReimbursment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SubmitPettyCashReimbursment extends Action
{
    public function handle(PettyCashReimbursment $reimbursment): PettyCashReimbursment
    {
        if ($reimbursment->status !== PettyCashStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft reimbursements can be submitted.',
            ]);
        }

        if ($reimbursment->pettyCashReimbursmentDetails()->count() < 1) {
            throw ValidationException::withMessages([
                'details' => 'Add at least one line item before submitting.',
            ]);
        }

        $reimbursment->update([
            'status' => PettyCashStatus::Submitted,
        ]);

        $hodEmail = $reimbursment->user?->department?->user?->email;
        if ($hodEmail) {
            Mail::to($hodEmail)->queue(new NotificationEmail('Petty Cash Request '.$reimbursment->id));
        }

        return $reimbursment->fresh();
    }
}
