<?php

namespace App\Support;

use App\Models\SubBudgetAccounts;
use Illuminate\Support\Collection;

class DepartmentBudgetOptions
{
    /**
     * Sub-budgets allocated to a department, with that department's remaining funds.
     *
     * @param  list<int|string>|null  $alwaysIncludeBudgetIds
     * @param  list<int|string>|null  $excludeDetailIds
     * @return Collection<int, array{
     *     id: int,
     *     label: string,
     *     department: string|null,
     *     location: string|null,
     *     allocated: float,
     *     allocated_amount: float,
     *     on_hold: float,
     *     available: float
     * }>
     */
    public static function forDepartment(
        ?int $departmentId,
        ?array $alwaysIncludeBudgetIds = null,
        ?array $excludeDetailIds = null,
        bool $requireAvailable = true,
    ): Collection {
        $alwaysInclude = collect($alwaysIncludeBudgetIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $budgets = SubBudgetAccounts::query()
            ->when(
                $departmentId,
                fn ($query) => $query->whereHas(
                    'allocations',
                    fn ($allocationQuery) => $allocationQuery->where('department_id', $departmentId),
                ),
                fn ($query) => $query->whereHas('allocations'),
            )
            ->with([
                'allocations' => fn ($query) => $query
                    ->when($departmentId, fn ($allocationQuery) => $allocationQuery->where('department_id', $departmentId))
                    ->with(['department', 'location']),
            ])
            ->orderBy('code')
            ->get();

        $holds = $departmentId
            ? PurchaseRequestBudget::onHoldBySubBudgetIds($budgets->pluck('id'), $departmentId, $excludeDetailIds)
            : [];

        return $budgets
            ->map(function (SubBudgetAccounts $budget) use ($holds, $departmentId): array {
                $allocation = $budget->allocations->first();
                $allocated = (float) $budget->allocations->sum('amount');
                $onHold = (float) ($holds[$budget->id] ?? 0);
                $available = $departmentId ? max(0, $allocated - $onHold) : $allocated;

                return [
                    'id' => $budget->id,
                    'label' => $budget->getSelectLabel(),
                    'department' => $allocation?->department?->name,
                    'location' => $allocation?->location?->name,
                    'allocated' => $allocated,
                    'allocated_amount' => $allocated,
                    'on_hold' => $onHold,
                    'available' => $available,
                ];
            })
            ->filter(function (array $budget) use ($alwaysInclude, $requireAvailable): bool {
                if ($alwaysInclude->contains($budget['id'])) {
                    return true;
                }

                if ($requireAvailable) {
                    return $budget['available'] > 0.00001;
                }

                return $budget['allocated'] > 0.00001;
            })
            ->values();
    }

    /**
     * @param  list<int|string>|null  $alwaysIncludeBudgetIds
     * @return array<int, string>
     */
    public static function filamentOptions(?int $departmentId, ?array $alwaysIncludeBudgetIds = null): array
    {
        return self::forDepartment($departmentId, $alwaysIncludeBudgetIds, requireAvailable: false)
            ->mapWithKeys(function (array $row): array {
                $label = $row['label'];
                if ($row['department']) {
                    $extra = $row['department'].': '.number_format($row['allocated'], 2);
                    if ($row['location']) {
                        $extra .= ' / '.$row['location'];
                    }
                    $label .= ' ('.$extra.')';
                }

                return [$row['id'] => $label];
            })
            ->all();
    }

    public static function helperText(?int $subBudgetId, ?int $departmentId): string
    {
        if (! $subBudgetId || ! $departmentId) {
            return 'Select a budget account allocated to the department.';
        }

        $account = SubBudgetAccounts::with([
            'allocations' => fn ($query) => $query
                ->where('department_id', $departmentId)
                ->with('department'),
        ])->find($subBudgetId);

        $allocation = $account?->allocations->first();

        if (! $allocation) {
            return 'No allocation found for this department.';
        }

        $departmentName = $allocation->department?->name ?? 'Department';
        $available = PurchaseRequestBudget::availableForDepartment($subBudgetId, $departmentId);

        return sprintf(
            '%s — allocated MVR %s, available MVR %s.',
            $departmentName,
            number_format((float) $allocation->amount, 2),
            number_format($available, 2),
        );
    }
}
