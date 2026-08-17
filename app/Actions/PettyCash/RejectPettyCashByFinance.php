<?php

namespace App\Actions\PettyCash;

use App\Actions\Action;
use App\Enums\PettyCashStatus;
use App\Mail\StatusEmail;
use App\Models\PettyCashReimbursment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RejectPettyCashByFinance extends Action
{
    public function handle(PettyCashReimbursment $reimbursment): PettyCashReimbursment
    {
        if ($reimbursment->status !== PettyCashStatus::DepApproved) {
            throw ValidationException::withMessages([
                'status' => 'Only department-approved reimbursements can be rejected by finance.',
            ]);
        }

        if (! $reimbursment->hasPvNumbers()) {
            throw ValidationException::withMessages([
                'pv_number' => 'Finance rejection requires PV numbers to be present.',
            ]);
        }

        $reimbursment->update([
            'status' => PettyCashStatus::Draft,
            'pv_number' => null,
            'verified_by' => null,
        ]);

        if ($reimbursment->user?->email) {
            Mail::to($reimbursment->user->email)->queue(
                new StatusEmail('Petty Cash Request '.$reimbursment->id, 'rejected', '', 'Finance', true)
            );
        }

        return $reimbursment->fresh();
    }
}
