<?php

namespace App\Support;

use App\Enums\PurchaseRequestsStatus;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Models\SubBudgetDepartmentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchaseRequestBudget
{
    /**
     * Statuses that place non-utilized PR line costs on hold.
     *
     * @return list<string>
     */
    public static function holdingStatuses(): array
    {
        return [
            PurchaseRequestsStatus::Submitted->value,
            PurchaseRequestsStatus::HODApproved->value,
            PurchaseRequestsStatus::DocumentUploaded->value,
            PurchaseRequestsStatus::MD_DMD_Approved->value,
            PurchaseRequestsStatus::Approved->value,
        ];
    }

    /**
     * @param  list<int>|Collection<int, int>  $subBudgetIds
     * @param  list<int>|Collection<int, int>|null  $excludeDetailIds
     * @return array<int, float>
     */
    public static function onHoldBySubBudgetIds(
        array|Collection $subBudgetIds,
        ?int $departmentId = null,
        array|Collection|null $excludeDetailIds = null,
    ): array {
        $subBudgetIds = collect($subBudgetIds)->filter()->unique()->values()->all();
        $excludeDetailIds = collect($excludeDetailIds ?? [])->filter()->unique()->values()->all();

        if ($subBudgetIds === []) {
            return [];
        }

        return PurchaseRequestDetails::query()
            ->whereIn('budget_account_id', $subBudgetIds)
            ->where('is_utilized', false)
            ->when($excludeDetailIds !== [], fn ($query) => $query->whereNotIn('id', $excludeDetailIds))
            ->whereHas('purchaseRequest', function ($query) use ($departmentId): void {
                $query->whereIn('status', self::holdingStatuses());

                if ($departmentId) {
                    $query->whereHas('user', fn ($userQuery) => $userQuery->where('department_id', $departmentId));
                }
            })
            ->selectRaw('budget_account_id, SUM(est_cost) as on_hold')
            ->groupBy('budget_account_id')
            ->pluck('on_hold', 'budget_account_id')
            ->map(fn ($value): float => (float) $value)
            ->all();
    }

    public static function allocatedForDepartment(int $subBudgetId, int $departmentId): float
    {
        return (float) SubBudgetDepartmentAllocation::query()
            ->where('sub_budget_account_id', $subBudgetId)
            ->where('department_id', $departmentId)
            ->sum('amount');
    }

    public static function availableForDepartment(
        int $subBudgetId,
        int $departmentId,
        array|Collection|null $excludeDetailIds = null,
    ): float {
        $allocated = self::allocatedForDepartment($subBudgetId, $departmentId);
        $onHold = (float) (self::onHoldBySubBudgetIds([$subBudgetId], $departmentId, $excludeDetailIds)[$subBudgetId] ?? 0);

        return max(0, $allocated - $onHold);
    }

    /**
     * Ensure this draft PR can be submitted without exceeding available budget.
     */
    public static function assertCanSubmit(PurchaseRequests $pr): void
    {
        $pr->loadMissing(['user', 'purchaseRequestDetails.budgetAccount']);

        $departmentId = $pr->user?->department_id;

        if (! $departmentId) {
            throw ValidationException::withMessages([
                'budget' => 'Requester has no department for budget allocation.',
            ]);
        }

        if ($pr->purchaseRequestDetails->isEmpty()) {
            throw ValidationException::withMessages([
                'budget' => 'Add at least one line item before submitting.',
            ]);
        }

        $neededByBudget = $pr->purchaseRequestDetails
            ->groupBy('budget_account_id')
            ->map(fn (Collection $lines): float => (float) $lines->sum('est_cost'));

        $holds = self::onHoldBySubBudgetIds($neededByBudget->keys()->all(), $departmentId);

        foreach ($neededByBudget as $budgetId => $needed) {
            if (! $budgetId) {
                throw ValidationException::withMessages([
                    'budget' => 'Every line item must have a budget code before submitting.',
                ]);
            }

            $allocated = self::allocatedForDepartment((int) $budgetId, $departmentId);

            if ($allocated <= 0) {
                $label = $pr->purchaseRequestDetails
                    ->firstWhere('budget_account_id', $budgetId)
                    ?->budgetAccount
                    ?->getSelectLabel() ?? "budget #{$budgetId}";

                throw ValidationException::withMessages([
                    'budget' => "No allocation for {$label} in your department.",
                ]);
            }

            $onHold = (float) ($holds[$budgetId] ?? 0);
            $available = max(0, $allocated - $onHold);

            if ($needed > $available + 0.00001) {
                $label = $pr->purchaseRequestDetails
                    ->firstWhere('budget_account_id', $budgetId)
                    ?->budgetAccount
                    ?->getSelectLabel() ?? "budget #{$budgetId}";

                throw ValidationException::withMessages([
                    'budget' => sprintf(
                        'Insufficient budget for %s. Needed MVR %s, available MVR %s.',
                        $label,
                        number_format($needed, 2),
                        number_format($available, 2),
                    ),
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertLineFitsAvailable(
        int $budgetAccountId,
        float $estCost,
        int $departmentId,
        ?string $estCostErrorKey = 'est_cost',
        ?string $budgetErrorKey = 'budget_account_id',
        array|Collection|null $excludeDetailIds = null,
    ): void {
        $allocation = SubBudgetDepartmentAllocation::query()
            ->where('sub_budget_account_id', $budgetAccountId)
            ->where('department_id', $departmentId)
            ->first();

        if (! $allocation) {
            throw ValidationException::withMessages([
                $budgetErrorKey => 'This budget code is not allocated to the requester department.',
            ]);
        }

        $available = self::availableForDepartment($budgetAccountId, $departmentId, $excludeDetailIds);

        if ($estCost > $available + 0.00001) {
            throw ValidationException::withMessages([
                $estCostErrorKey => sprintf(
                    "You don't have enough available funds for this budget code (available MVR %s).",
                    number_format($available, 2),
                ),
            ]);
        }
    }
}
