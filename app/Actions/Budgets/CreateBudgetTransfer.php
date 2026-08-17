<?php

namespace App\Actions\Budgets;

use App\Actions\Action;
use App\Models\BudgetTransactionHistory;
use App\Models\BudgetTransfer;
use App\Models\SubBudgetAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBudgetTransfer extends Action
{
    /**
     * @param  array{from_budget_id: int, to_budget_id: int, amount: int|float, description?: ?string, user_id: int}  $data
     */
    public function handle(array $data): BudgetTransfer
    {
        if ((int) $data['from_budget_id'] === (int) $data['to_budget_id']) {
            throw ValidationException::withMessages([
                'to_budget_id' => 'Source and destination budgets must be different.',
            ]);
        }

        return DB::transaction(function () use ($data): BudgetTransfer {
            $fromBudget = SubBudgetAccounts::query()
                ->with('allocations')
                ->findOrFail($data['from_budget_id']);

            $toBudget = SubBudgetAccounts::query()
                ->with('allocations')
                ->findOrFail($data['to_budget_id']);

            $amount = (float) $data['amount'];

            if ($fromBudget->total_amount < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient balance on the source budget.',
                ]);
            }

            if ($toBudget->allocations->isEmpty()) {
                throw ValidationException::withMessages([
                    'to_budget_id' => 'Destination budget has no department allocations.',
                ]);
            }

            $remaining = $amount;
            foreach ($fromBudget->allocations as $allocation) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min((float) $allocation->amount, $remaining);
                $allocation->update(['amount' => (float) $allocation->amount - $take]);
                $remaining -= $take;
            }

            $toAllocation = $toBudget->allocations->first();
            $toAllocation->update(['amount' => (float) $toAllocation->amount + $amount]);

            $transfer = BudgetTransfer::create([
                'from_budget_id' => $fromBudget->id,
                'to_budget_id' => $toBudget->id,
                'user_id' => $data['user_id'],
                'amount' => $amount,
                'description' => $data['description'] ?? null,
            ]);

            $fromBalance = (float) $fromBudget->fresh()->load('allocations')->total_amount;
            $toBalance = (float) $toBudget->fresh()->load('allocations')->total_amount;

            BudgetTransactionHistory::createtransaction(
                $fromBudget->id,
                'Transfer',
                $amount,
                $fromBalance,
                'Transfer to '.$toBudget->code,
                $data['user_id'],
            );

            BudgetTransactionHistory::createtransaction(
                $toBudget->id,
                'Transfer',
                $amount,
                $toBalance,
                'Transfer from '.$fromBudget->code,
                $data['user_id'],
            );

            return $transfer;
        });
    }
}
