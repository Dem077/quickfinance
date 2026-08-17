<?php

namespace App\Actions\PettyCash;

use App\Actions\Action;
use App\Enums\PettyCashStatus;
use App\Models\PettyCashReimbursment;
use App\Models\PettyCashReimbursmentDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePettyCashReimbursment extends Action
{
    /**
     * @param  array{
     *     date: string,
     *     supporting_documents?: string|null,
     *     details: array<int, array{
     *         date: string,
     *         Vendor_id: int,
     *         bill_no: string,
     *         sub_budget_id: int,
     *         amount: float|int,
     *         po_id?: int|null,
     *         item_id?: int|null,
     *         details?: string|null
     *     }>
     * }  $data
     */
    public function handle(array $data, int $userId): PettyCashReimbursment
    {
        if (empty($data['details'])) {
            throw ValidationException::withMessages([
                'details' => 'At least one reimbursement item is required.',
            ]);
        }

        return DB::transaction(function () use ($data, $userId): PettyCashReimbursment {
            $reimbursment = PettyCashReimbursment::create([
                'date' => $data['date'],
                'form_no' => PettyCashReimbursment::generateNextFormNo(),
                'user_id' => $userId,
                'status' => PettyCashStatus::Draft,
                'supporting_documents' => $data['supporting_documents'] ?? null,
            ]);

            foreach ($data['details'] as $detail) {
                PettyCashReimbursmentDetail::create([
                    'petty_cash_reimb_id' => $reimbursment->id,
                    'Vendor_id' => $detail['Vendor_id'],
                    'bill_no' => $detail['bill_no'],
                    'date' => $detail['date'],
                    'sub_budget_id' => $detail['sub_budget_id'],
                    'item_id' => $detail['item_id'] ?? null,
                    'details' => $detail['details'] ?? '',
                    'po_id' => $detail['po_id'] ?? null,
                    'amount' => $detail['amount'],
                ]);
            }

            return $reimbursment->fresh(['pettyCashReimbursmentDetails']);
        });
    }
}
