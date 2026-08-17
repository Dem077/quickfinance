<?php

namespace App\Actions\PettyCash;

use App\Actions\Action;
use App\Enums\PettyCashStatus;
use App\Models\PettyCashReimbursment;
use App\Models\PettyCashReimbursmentDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePettyCashReimbursment extends Action
{
    /**
     * @param  array{
     *     date?: string,
     *     supporting_documents?: string|null,
     *     details?: array<int, array<string, mixed>>
     * }  $data
     */
    public function handle(PettyCashReimbursment $reimbursment, array $data): PettyCashReimbursment
    {
        if (! in_array($reimbursment->status, [PettyCashStatus::Draft, PettyCashStatus::DepApproved], true)) {
            throw ValidationException::withMessages([
                'status' => 'This reimbursement cannot be edited in its current status.',
            ]);
        }

        return DB::transaction(function () use ($reimbursment, $data): PettyCashReimbursment {
            $payload = [];

            if ($reimbursment->status === PettyCashStatus::Draft) {
                if (array_key_exists('date', $data)) {
                    $payload['date'] = $data['date'];
                }
                if (array_key_exists('supporting_documents', $data)) {
                    $payload['supporting_documents'] = $data['supporting_documents'];
                }
            } elseif (array_key_exists('supporting_documents', $data)) {
                $payload['supporting_documents'] = $data['supporting_documents'];
            }

            if ($payload !== []) {
                $reimbursment->update($payload);
            }

            if ($reimbursment->status === PettyCashStatus::Draft && array_key_exists('details', $data)) {
                if (empty($data['details'])) {
                    throw ValidationException::withMessages([
                        'details' => 'At least one reimbursement item is required.',
                    ]);
                }

                $reimbursment->pettyCashReimbursmentDetails()->delete();

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
            }

            return $reimbursment->fresh(['pettyCashReimbursmentDetails']);
        });
    }
}
