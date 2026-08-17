<?php

namespace App\Actions\PettyCash;

use App\Actions\Action;
use App\Enums\PettyCashStatus;
use App\Models\PettyCashReimbursment;
use Illuminate\Validation\ValidationException;

class AddPettyCashPvNumbers extends Action
{
    /**
     * @param  array<int, string>  $pvNumbers
     */
    public function handle(PettyCashReimbursment $reimbursment, array $pvNumbers, int $actorId): PettyCashReimbursment
    {
        if (! in_array($reimbursment->status, [PettyCashStatus::DepApproved, PettyCashStatus::FinApproved], true)) {
            throw ValidationException::withMessages([
                'status' => 'PV numbers can only be added after department approval.',
            ]);
        }

        if ($reimbursment->hasPvNumbers()) {
            throw ValidationException::withMessages([
                'pv_numbers' => 'PV numbers have already been added.',
            ]);
        }

        $cleaned = array_values(array_filter(array_map(
            fn ($value) => is_string($value) ? trim($value) : '',
            $pvNumbers
        )));

        if ($cleaned === []) {
            throw ValidationException::withMessages([
                'pv_numbers' => 'At least one PV number is required.',
            ]);
        }

        $reimbursment->update([
            'pv_number' => json_encode($cleaned),
            'verified_by' => $actorId,
        ]);

        return $reimbursment->fresh();
    }
}
