<?php

namespace App\Actions\Budgets;

use App\Actions\Action;
use App\Models\BudgetAccounts;
use App\Models\BudgetTransactionHistory;
use App\Models\SubBudgetDepartmentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TopUpBudgetAccount extends Action
{
    /**
     * @param  array<int, array{id: int|string, sub_budget_account_id: int|string, amount: int|float|string}>  $allocations
     */
    public function handle(BudgetAccounts $budgetAccount, array $allocations, int $userId): void
    {
        DB::transaction(function () use ($budgetAccount, $allocations, $userId): void {
            $rows = collect($allocations);

            $rows->each(function (array $row) use ($budgetAccount): void {
                if (empty($row['id'])) {
                    return;
                }

                $allocation = SubBudgetDepartmentAllocation::query()
                    ->whereKey($row['id'])
                    ->whereHas('subBudgetAccount', fn ($query) => $query->where('budget_account_id', $budgetAccount->id))
                    ->first();

                if (! $allocation) {
                    return;
                }

                $allocation->update(['amount' => $row['amount']]);
            });

            $rows
                ->groupBy('sub_budget_account_id')
                ->each(function (Collection $group, $subBudgetId) use ($userId): void {
                    $total = (int) $group->sum('amount');

                    BudgetTransactionHistory::createtransaction(
                        $subBudgetId,
                        'Top UP',
                        $total,
                        $total,
                        'Funds added to account',
                        $userId,
                    );
                });
        });
    }
}
