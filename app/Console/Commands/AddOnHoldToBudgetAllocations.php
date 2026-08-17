<?php

namespace App\Console\Commands;

use App\Enums\PurchaseOrderStatus;
use App\Models\BudgetTransactionHistory;
use App\Models\PurchaseOrderDetails;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\SubBudgetAccounts;
use App\Models\SubBudgetDepartmentAllocation;
use App\Models\User;
use App\Support\PurchaseRequestBudget;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddOnHoldToBudgetAllocations extends Command
{
    protected $signature = 'budgets:add-on-hold-to-allocations
                            {--dry-run : Preview changes without updating data}
                            {--force : Skip the confirmation prompt}
                            {--user= : User ID recorded on budget transaction history}
                            {--skip-top-up : Only clear petty-cash holds and deduct closed POs}';

    protected $description = 'Align budgets with PO rules: clear petty-cash PR holds, deduct closed purchase-order lines, then top up remaining on-hold into raiser department allocations';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->resolveActorUserId();

        if (! $userId) {
            $this->error('No user found for transaction history. Pass --user=ID.');

            return self::FAILURE;
        }

        $pettyCashPos = $this->pettyCashOrdersNeedingHoldCleared();
        $closedPosToDeduct = $this->closedPurchaseOrdersNeedingDeduction();

        $this->info('Step 1 — Petty cash: clear PR on-hold for PO items (no allocation deduct)');
        if ($pettyCashPos->isEmpty()) {
            $this->line('  None.');
        } else {
            $this->table(
                ['PO / Form', 'PR', 'Status', 'Lines'],
                $pettyCashPos->map(fn (PurchaseOrders $po): array => [
                    $po->po_no,
                    $po->purchaseRequest?->pr_no ?? $po->pr_id,
                    $po->status?->getLabel() ?? $po->status?->value,
                    $po->purchaseOrderDetails->count(),
                ])->all(),
            );
        }

        $this->newLine();
        $this->info('Step 2 — Purchase orders: deduct closed PO line amounts from department allocation');
        if ($closedPosToDeduct->isEmpty()) {
            $this->line('  None.');
        } else {
            $this->table(
                ['PO', 'PR', 'Lines', 'Total'],
                $closedPosToDeduct->map(fn (PurchaseOrders $po): array => [
                    $po->po_no,
                    $po->purchaseRequest?->pr_no ?? $po->pr_id,
                    $po->purchaseOrderDetails->count(),
                    number_format((float) $po->purchaseOrderDetails->sum('amount'), 2),
                ])->all(),
            );
        }

        // Exclude PR lines already covered by petty cash POs from on-hold top-up.
        $clearedItemKeys = $this->allPettyCashCoveredPrItemKeys();
        $holds = $this->currentHoldsByBudgetAndDepartment($clearedItemKeys);

        $this->newLine();
        $this->info('Step 3 — Top up remaining on-hold into raiser department allocations');

        $planned = [];
        $rows = [];

        if ($this->option('skip-top-up')) {
            $this->line('  Skipped (--skip-top-up).');
        } elseif ($holds->isEmpty()) {
            $this->line('  No remaining on-hold amounts.');
        } else {
            foreach ($holds as $hold) {
                $subBudget = SubBudgetAccounts::query()->find($hold['sub_budget_id']);
                $allocation = $this->resolveAllocation($hold['sub_budget_id'], $hold['department_id']);
                $before = $allocation ? (float) $allocation->amount : 0.0;
                $after = $before + $hold['on_hold'];

                $rows[] = [
                    $subBudget?->code ?? $hold['sub_budget_id'],
                    $subBudget?->name ?? '—',
                    $hold['department_id'],
                    $hold['department_name'],
                    number_format($hold['on_hold'], 2),
                    number_format($before, 2),
                    number_format($after, 2),
                    $allocation ? 'update #'.$allocation->id : 'create',
                ];

                $planned[] = [
                    'sub_budget_id' => $hold['sub_budget_id'],
                    'department_id' => $hold['department_id'],
                    'on_hold' => $hold['on_hold'],
                    'allocation' => $allocation,
                    'before' => $before,
                    'after' => $after,
                ];
            }

            $this->table(
                ['Code', 'Sub budget', 'Dept ID', 'Department', 'On hold', 'Before', 'After', 'Action'],
                $rows,
            );

            $this->info(sprintf(
                'Top-up totals: %d pairs · on hold MVR %s',
                count($planned),
                number_format(collect($planned)->sum('on_hold'), 2),
            ));
        }

        if ($dryRun) {
            $this->warn('Dry run only — no data was changed.');

            return self::SUCCESS;
        }

        if (
            $pettyCashPos->isEmpty()
            && $closedPosToDeduct->isEmpty()
            && $planned === []
        ) {
            $this->info('Nothing to apply.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Apply these budget changes?', false)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($pettyCashPos, $closedPosToDeduct, $planned, $userId): void {
            foreach ($pettyCashPos as $po) {
                $po->releasePurchaseRequestPendingHold();
            }

            foreach ($closedPosToDeduct as $po) {
                $po->deductDepartmentAllocations($userId);
            }

            foreach ($planned as $item) {
                if ($item['allocation']) {
                    $item['allocation']->update(['amount' => $item['after']]);
                } else {
                    SubBudgetDepartmentAllocation::query()->create([
                        'sub_budget_account_id' => $item['sub_budget_id'],
                        'department_id' => $item['department_id'],
                        'location_id' => null,
                        'amount' => $item['after'],
                    ]);
                }

                BudgetTransactionHistory::createtransaction(
                    $item['sub_budget_id'],
                    'Top UP',
                    (int) round($item['after']),
                    (int) round($item['after']),
                    'Added current on-hold amount (MVR '.number_format($item['on_hold'], 2).') to department allocation',
                    $userId,
                );
            }
        });

        $this->info('Budget changes applied successfully.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, PurchaseOrders>
     */
    private function pettyCashOrdersNeedingHoldCleared(): Collection
    {
        return PurchaseOrders::query()
            ->with(['purchaseOrderDetails', 'purchaseRequest'])
            ->where('payment_method', 'petty_cash')
            ->whereNotNull('pr_id')
            ->whereHas('purchaseOrderDetails')
            ->get()
            ->filter(function (PurchaseOrders $po): bool {
                foreach ($po->purchaseOrderDetails as $detail) {
                    $item = $detail->resolvedItem();
                    if (! $item) {
                        continue;
                    }

                    $pending = PurchaseRequestDetails::query()
                        ->where('pr_id', $po->pr_id)
                        ->where('item_id', $item->id)
                        ->where('is_utilized', false)
                        ->exists();

                    if ($pending) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @return Collection<int, PurchaseOrders>
     */
    private function closedPurchaseOrdersNeedingDeduction(): Collection
    {
        return PurchaseOrders::query()
            ->with(['purchaseOrderDetails', 'purchaseRequest.user'])
            ->where('payment_method', 'purchase_order')
            ->where('status', PurchaseOrderStatus::Closed)
            ->whereNull('budget_deducted_at')
            ->whereHas('purchaseOrderDetails')
            ->get();
    }

    /**
     * @param  array<string, true>  $excludePrItemKeys
     * @return Collection<int, array{sub_budget_id: int, department_id: int, department_name: string, on_hold: float}>
     */
    private function currentHoldsByBudgetAndDepartment(array $excludePrItemKeys = []): Collection
    {
        $details = PurchaseRequestDetails::query()
            ->where('is_utilized', false)
            ->whereNotNull('budget_account_id')
            ->whereHas('purchaseRequest', function ($query): void {
                $query->whereIn('status', PurchaseRequestBudget::holdingStatuses())
                    ->whereHas('user', fn ($userQuery) => $userQuery->whereNotNull('department_id'));
            })
            ->with(['purchaseRequest.user.department'])
            ->get()
            ->reject(function (PurchaseRequestDetails $detail) use ($excludePrItemKeys): bool {
                return isset($excludePrItemKeys[$detail->pr_id.':'.$detail->item_id]);
            });

        return $details
            ->groupBy(fn (PurchaseRequestDetails $detail): string => $detail->budget_account_id.'|'.$detail->purchaseRequest->user->department_id)
            ->map(function ($group) {
                /** @var PurchaseRequestDetails $first */
                $first = $group->first();
                $department = $first->purchaseRequest->user->department;

                return [
                    'sub_budget_id' => (int) $first->budget_account_id,
                    'department_id' => (int) $department->id,
                    'department_name' => $department->name ?? '—',
                    'on_hold' => round((float) $group->sum('est_cost'), 2),
                ];
            })
            ->filter(fn (array $row): bool => $row['on_hold'] > 0)
            ->sortBy(['sub_budget_id', 'department_id'])
            ->values();
    }

    /**
     * @return array<string, true>
     */
    private function allPettyCashCoveredPrItemKeys(): array
    {
        $keys = [];

        $details = PurchaseOrderDetails::query()
            ->whereHas('purchaseOrder', fn ($query) => $query->where('payment_method', 'petty_cash')->whereNotNull('pr_id'))
            ->with(['purchaseOrder', 'items'])
            ->get();

        foreach ($details as $detail) {
            $po = $detail->purchaseOrder;
            $item = $detail->resolvedItem();
            if (! $po?->pr_id || ! $item) {
                continue;
            }
            $keys[$po->pr_id.':'.$item->id] = true;
        }

        return $keys;
    }

    private function resolveActorUserId(): ?int
    {
        $option = $this->option('user');
        if (filled($option)) {
            return User::query()->whereKey((int) $option)->value('id');
        }

        return User::query()->orderBy('id')->value('id');
    }

    private function resolveAllocation(int $subBudgetId, int $departmentId): ?SubBudgetDepartmentAllocation
    {
        return SubBudgetDepartmentAllocation::query()
            ->where('sub_budget_account_id', $subBudgetId)
            ->where('department_id', $departmentId)
            ->orderByDesc('amount')
            ->orderBy('id')
            ->first();
    }
}
