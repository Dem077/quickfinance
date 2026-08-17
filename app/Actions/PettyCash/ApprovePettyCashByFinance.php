<?php

namespace App\Actions\PettyCash;

use App\Actions\Action;
use App\Enums\PettyCashStatus;
use App\Enums\PurchaseOrderStatus;
use App\Mail\StatusEmail;
use App\Models\PettyCashReimbursment;
use App\Models\PurchaseOrders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ApprovePettyCashByFinance extends Action
{
    public function handle(PettyCashReimbursment $reimbursment, int $actorId): PettyCashReimbursment
    {
        if (! in_array($reimbursment->status, [PettyCashStatus::DepApproved, PettyCashStatus::FinApproved], true)) {
            throw ValidationException::withMessages([
                'status' => 'Reimbursement is not awaiting finance approval.',
            ]);
        }

        if (! $reimbursment->hasPvNumbers()) {
            throw ValidationException::withMessages([
                'pv_number' => 'PV numbers must be added before finance approval.',
            ]);
        }

        return DB::transaction(function () use ($reimbursment, $actorId): PettyCashReimbursment {
            $reimbursment->deductFromDepartmentBudgets($actorId);

            $reimbursment->update([
                'status' => PettyCashStatus::Rembursed,
                'approved_by' => $actorId,
            ]);

            $reimbursment->load('pettyCashReimbursmentDetails.purchaseOrder');

            foreach ($reimbursment->pettyCashReimbursmentDetails as $detail) {
                if ($detail->purchaseOrder !== null) {
                    $detail->purchaseOrder->update([
                        'status' => PurchaseOrderStatus::Reimbursed,
                    ]);
                }

                if ($detail->po_id != null) {
                    PurchaseOrders::query()->whereKey($detail->po_id)->update(['is_reimbursed' => true]);
                }
            }

            if ($reimbursment->user?->email) {
                Mail::to($reimbursment->user->email)->queue(
                    new StatusEmail('Petty Cash Request '.$reimbursment->id, 'approved', '', 'Finance')
                );
            }

            return $reimbursment->fresh(['pettyCashReimbursmentDetails']);
        });
    }
}
