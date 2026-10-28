<?php

namespace App\Actions\Budgets;

use App\Actions\Action;
use App\Models\BudgetAccounts;
use App\Models\BudgetTransactionHistory;
use App\Models\SubBudgetDepartmentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TopUpBudgetAccount extends Action
{
    /**
     * @param  array<int, array{id: int|string, sub_budget_account_id?: int|string, amount: int|float|string}>  $allocations
     */
    public function handle(BudgetAccounts $budgetAccount, array $allocations, int $userId): void
    {
        $rows = collect($allocations)
            ->filter(fn (array $row): bool => ! empty($row['id']) && (float) ($row['amount'] ?? 0) > 0);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Enter an amount to add for at least one allocation.',
            ]);
        }

        DB::transaction(function () use ($budgetAccount, $rows, $userId): void {
            $addedBySub = [];

            $rows->each(function (array $row) use ($budgetAccount, &$addedBySub): void {
                $allocation = SubBudgetDepartmentAllocation::query()
                    ->whereKey($row['id'])
                    ->whereHas('subBudgetAccount', fn ($query) => $query->where('budget_account_id', $budgetAccount->id))
                    ->lockForUpdate()
                    ->first();

                if (! $allocation) {
                    return;
                }

                $add = round((float) $row['amount'], 3);
                $allocation->update([
                    'amount' => round((float) $allocation->amount + $add, 3),
                ]);

                $subBudgetId = (int) $allocation->sub_budget_account_id;
                $addedBySub[$subBudgetId] = ($addedBySub[$subBudgetId] ?? 0) + $add;
            });

            foreach ($addedBySub as $subBudgetId => $added) {
                $balance = (float) SubBudgetDepartmentAllocation::query()
                    ->where('sub_budget_account_id', $subBudgetId)
                    ->sum('amount');

                BudgetTransactionHistory::createtransaction(
                    $subBudgetId,
                    'Top UP',
                    $added,
                    $balance,
                    'Funds added to account',
                    $userId,
                );
            }
        });
    }
}
