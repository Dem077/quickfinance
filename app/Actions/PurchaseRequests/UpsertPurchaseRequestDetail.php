<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Support\PurchaseRequestBudget;
use Illuminate\Validation\ValidationException;

class UpsertPurchaseRequestDetail extends Action
{
    /**
     * @param  array{item_id: int, unit: string, budget_account_id: int, amount: float|int, est_cost: float|int}  $data
     */
    public function handle(PurchaseRequests $pr, array $data, ?PurchaseRequestDetails $detail = null, ?int $departmentId = null): PurchaseRequestDetails
    {
        $departmentId ??= $pr->user?->department_id;

        if (! $departmentId) {
            throw ValidationException::withMessages([
                'budget_account_id' => 'Requester has no department for budget allocation.',
            ]);
        }

        PurchaseRequestBudget::assertLineFitsAvailable(
            (int) $data['budget_account_id'],
            (float) $data['est_cost'],
            $departmentId,
        );

        $payload = [
            'item_id' => $data['item_id'],
            'unit' => $data['unit'],
            'budget_account_id' => $data['budget_account_id'],
            'amount' => $data['amount'],
            'est_cost' => $data['est_cost'],
        ];

        if ($detail) {
            $detail->update($payload);

            return $detail->fresh();
        }

        return PurchaseRequestDetails::create([
            ...$payload,
            'pr_id' => $pr->id,
        ]);
    }
}
