<?php

namespace App\Actions\PettyCash;

use App\Actions\Action;
use App\Enums\PettyCashStatus;
use App\Mail\NotificationEmail;
use App\Models\PettyCashReimbursment;
use App\Models\PurchaseOrders;
use Illuminate\Support\Facades\DB;
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

        return DB::transaction(function () use ($reimbursment): PettyCashReimbursment {
            if (blank($reimbursment->form_no)) {
                $reimbursment->form_no = PettyCashReimbursment::generateNextFormNo();
            }

            $reimbursment->status = PettyCashStatus::Submitted;
            $reimbursment->save();

            $poIds = $reimbursment->pettyCashReimbursmentDetails()
                ->whereNotNull('po_id')
                ->pluck('po_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            if ($poIds !== []) {
                PurchaseOrders::query()
                    ->whereIn('id', $poIds)
                    ->where('payment_method', 'petty_cash')
                    ->update(['po_no' => $reimbursment->form_no]);
            }

            $hodEmail = $reimbursment->user?->department?->user?->email;
            if ($hodEmail) {
                Mail::to($hodEmail)->queue(new NotificationEmail('Petty Cash Request '.$reimbursment->id));
            }

            return $reimbursment->fresh();
        });
    }
}
