<?php

namespace App\Actions\PurchaseRequests;

use App\Actions\Action;
use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Support\PurchaseRequestBudget;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchaseRequest extends Action
{
    /**
     * @param  array{
     *     date: string,
     *     purpose: string,
     *     project_id?: int|null,
     *     locations: array<int>,
     *     supporting_document?: string|null,
     *     details: array<int, array{item_id: int, unit: string, budget_account_id: int, amount: float|int, est_cost: float|int}>
     * }  $data
     */
    public function handle(array $data, int $userId, ?int $departmentId): PurchaseRequests
    {
        if (empty($data['details'])) {
            throw ValidationException::withMessages([
                'details' => 'At least one line item is required.',
            ]);
        }

        if (! $departmentId) {
            throw ValidationException::withMessages([
                'details.0.budget_account_id' => 'Your user has no department for budget allocation.',
            ]);
        }

        foreach ($data['details'] as $index => $detail) {
            $this->assertBudget($detail, $departmentId, $index);
        }

        $neededByBudget = collect($data['details'])
            ->groupBy('budget_account_id')
            ->map(fn ($lines) => (float) collect($lines)->sum('est_cost'));

        foreach ($neededByBudget as $budgetId => $needed) {
            $available = PurchaseRequestBudget::availableForDepartment((int) $budgetId, $departmentId);

            if ($needed > $available + 0.00001) {
                $index = collect($data['details'])->search(
                    fn (array $detail): bool => (int) $detail['budget_account_id'] === (int) $budgetId,
                );

                throw ValidationException::withMessages([
                    "details.{$index}.est_cost" => sprintf(
                        "Combined lines exceed available funds for this budget code (available MVR %s).",
                        number_format($available, 2),
                    ),
                ]);
            }
        }

        return DB::transaction(function () use ($data, $userId): PurchaseRequests {
            $pr = PurchaseRequests::create([
                'pr_no' => $this->nextPrNo(),
                'date' => $data['date'],
                'purpose' => $data['purpose'],
                'project_id' => $data['project_id'] ?? null,
                'user_id' => $userId,
                'status' => PurchaseRequestsStatus::Draft,
                'supporting_document' => $data['supporting_document'] ?? null,
            ]);

            $pr->locations()->sync($data['locations']);

            foreach ($data['details'] as $detail) {
                PurchaseRequestDetails::create([
                    'pr_id' => $pr->id,
                    'item_id' => $detail['item_id'],
                    'unit' => $detail['unit'],
                    'budget_account_id' => $detail['budget_account_id'],
                    'amount' => $detail['amount'],
                    'est_cost' => $detail['est_cost'],
                ]);
            }

            return $pr->fresh(['purchaseRequestDetails', 'locations']);
        });
    }

    private function nextPrNo(): string
    {
        $year = date('Y');
        $count = PurchaseRequests::whereYear('created_at', $year)->count() + 1;

        do {
            $prNo = sprintf('PR/AGRO/%s/%04d', $year, $count);
            $exists = PurchaseRequests::where('pr_no', $prNo)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $prNo;
    }

    /**
     * @param  array{budget_account_id: int, est_cost: float|int}  $detail
     */
    private function assertBudget(array $detail, ?int $departmentId, int $index): void
    {
        if (! $departmentId) {
            throw ValidationException::withMessages([
                "details.{$index}.budget_account_id" => 'Your user has no department for budget allocation.',
            ]);
        }

        PurchaseRequestBudget::assertLineFitsAvailable(
            (int) $detail['budget_account_id'],
            (float) $detail['est_cost'],
            $departmentId,
            "details.{$index}.est_cost",
            "details.{$index}.budget_account_id",
        );
    }
}
