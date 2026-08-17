<?php

namespace App\Actions\PettyCash;

use App\Actions\Action;
use App\Enums\PettyCashStatus;
use App\Mail\NotificationEmail;
use App\Mail\StatusEmail;
use App\Models\PettyCashReimbursment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RejectPettyCashByDepartment extends Action
{
    public function handle(PettyCashReimbursment $reimbursment, int $actorId): PettyCashReimbursment
    {
        $hodId = $reimbursment->user?->department?->user?->id
            ?? $reimbursment->user?->department?->hod;

        if ((int) $hodId !== $actorId) {
            throw ValidationException::withMessages([
                'status' => 'Only the requester\'s department HOD can perform this action.',
            ]);
        }

        if ($reimbursment->status !== PettyCashStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'Reimbursement is not awaiting department approval.',
            ]);
        }

        $reimbursment->update([
            'status' => PettyCashStatus::Draft,
        ]);

        if ($reimbursment->user?->email) {
            Mail::to($reimbursment->user->email)->queue(
                new StatusEmail('Petty Cash Request '.$reimbursment->id, 'rejected', '', 'Department HOD', true)
            );
        }

        $pvApprovers = User::permission('pv_approve_petty::cash::reimbursment')->get();
        foreach ($pvApprovers as $approver) {
            Mail::to($approver->email)->queue(new NotificationEmail('Petty Cash Request '.$reimbursment->id));
        }

        return $reimbursment->fresh();
    }
}
