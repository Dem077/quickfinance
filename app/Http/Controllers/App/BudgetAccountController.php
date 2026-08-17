<?php

namespace App\Http\Controllers\App;

use App\Actions\Budgets\TopUpBudgetAccount;
use App\Http\Controllers\Controller;
use App\Models\BudgetAccounts;
use App\Models\Departments;
use App\Models\Location;
use App\Models\PurchaseRequests;
use App\Models\SubBudgetAccounts;
use App\Support\PurchaseRequestBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BudgetAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BudgetAccounts::class);

        $search = $request->string('search')->trim()->toString();
        $type = $request->string('expenditure_type')->trim()->toString();

        $mapAccount = fn (BudgetAccounts $account): array => [
            'id' => $account->id,
            'expenditure_type' => $account->expenditure_type,
            'account' => $account->account,
            'name' => $account->name,
            'total_amount' => $account->subBudgetAccounts->sum(
                fn (SubBudgetAccounts $sub) => $sub->allocations->sum('amount')
            ),
        ];

        $accounts = BudgetAccounts::query()
            ->with(['subBudgetAccounts.allocations'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('expenditure_type', 'like', "%{$search}%")
                        ->orWhere('account', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('expenditure_type', $type))
            ->orderBy('expenditure_type')
            ->orderBy('account')
            ->get()
            ->map($mapAccount);

        $groups = $accounts
            ->groupBy(fn (array $account): string => filled($account['expenditure_type']) ? $account['expenditure_type'] : 'Unspecified')
            ->map(fn ($rows, $groupType): array => [
                'type' => $groupType,
                'count' => $rows->count(),
                'total_amount' => $rows->sum('total_amount'),
                'accounts' => $rows->values()->all(),
            ])
            ->values()
            ->all();

        $typeTabs = BudgetAccounts::query()
            ->select('expenditure_type')
            ->selectRaw('count(*) as badge')
            ->groupBy('expenditure_type')
            ->orderBy('expenditure_type')
            ->get()
            ->map(fn ($row): array => [
                'key' => $row->expenditure_type ?: 'Unspecified',
                'label' => $row->expenditure_type ?: 'Unspecified',
                'badge' => (int) $row->badge,
            ])
            ->values()
            ->all();

        return Inertia::render('BudgetAccounts/Index', [
            'groups' => $groups,
            'filters' => [
                'search' => $search,
                'expenditure_type' => $type,
            ],
            'tabs' => [
                [
                    'key' => '',
                    'label' => 'All',
                    'badge' => BudgetAccounts::query()->count(),
                ],
                ...$typeTabs,
            ],
            'can' => [
                'create' => $request->user()->can('create', BudgetAccounts::class),
                'deleteAny' => $request->user()->can('deleteAny', BudgetAccounts::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', BudgetAccounts::class);

        return Inertia::render('BudgetAccounts/Form', [
            'account' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BudgetAccounts::class);

        $data = $this->validatedAccount($request);

        $account = BudgetAccounts::create($data);

        return redirect()
            ->route('app.budget-accounts.edit', $account)
            ->with('success', 'Budget account created.');
    }

    public function edit(Request $request, BudgetAccounts $budgetAccount): Response
    {
        abort_unless(
            $request->user()->can('view', $budgetAccount)
                || $request->user()->can('update', $budgetAccount),
            403,
        );

        $budgetAccount->load(['subBudgetAccounts.allocations.department', 'subBudgetAccounts.allocations.location']);

        $subBudgetIds = $budgetAccount->subBudgetAccounts->pluck('id');
        $holdsBySubBudget = PurchaseRequestBudget::onHoldBySubBudgetIds($subBudgetIds->all());

        return Inertia::render('BudgetAccounts/Edit', [
            'account' => [
                'id' => $budgetAccount->id,
                'expenditure_type' => $budgetAccount->expenditure_type,
                'account' => $budgetAccount->account,
                'name' => $budgetAccount->name,
            ],
            'subBudgets' => $budgetAccount->subBudgetAccounts->map(function (SubBudgetAccounts $sub) use ($holdsBySubBudget): array {
                $allocated = (float) $sub->total_amount;
                $onHold = (float) ($holdsBySubBudget[$sub->id] ?? 0);

                return [
                    'id' => $sub->id,
                    'code' => $sub->code,
                    'name' => $sub->name,
                    'display_name' => $sub->display_name,
                    'total_amount' => $allocated,
                    'on_hold' => $onHold,
                    'available' => max(0, $allocated - $onHold),
                    'usage_percent' => $allocated > 0
                        ? (int) min(100, round(($onHold / $allocated) * 100))
                        : 0,
                    'allocations' => $sub->allocations->map(fn ($allocation): array => [
                        'id' => $allocation->id,
                        'department_id' => $allocation->department_id,
                        'location_id' => $allocation->location_id,
                        'department' => $allocation->department?->name,
                        'location' => $allocation->location?->name,
                        'amount' => $allocation->amount,
                    ])->values(),
                ];
            })->values(),
            'departments' => Departments::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'can' => [
                'update' => $request->user()->can('update', $budgetAccount),
                'delete' => $request->user()->can('delete', $budgetAccount),
            ],
        ]);
    }

    public function onHoldPurchaseRequests(
        BudgetAccounts $budgetAccount,
        SubBudgetAccounts $subBudget,
    ): JsonResponse {
        $this->authorize('update', $budgetAccount);
        $this->ensureSubBelongsToAccount($budgetAccount, $subBudget);

        $holdingStatuses = PurchaseRequestBudget::holdingStatuses();

        $records = PurchaseRequests::query()
            ->with(['user.department', 'project'])
            ->whereIn('status', $holdingStatuses)
            ->whereHas(
                'purchaseRequestDetails',
                fn ($query) => $query
                    ->where('budget_account_id', $subBudget->id)
                    ->where('is_utilized', false),
            )
            ->withSum([
                'purchaseRequestDetails as hold_amount' => fn ($query) => $query
                    ->where('budget_account_id', $subBudget->id)
                    ->where('is_utilized', false),
            ], 'est_cost')
            ->withSum('purchaseRequestDetails as total_est_cost', 'est_cost')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseRequests $pr): array => [
                'id' => $pr->id,
                'pr_no' => $pr->pr_no,
                'date' => $pr->date,
                'purpose' => $pr->purpose,
                'user' => $pr->user?->name,
                'department' => $pr->user?->department?->name,
                'project' => $pr->project?->name,
                'status' => $pr->status?->value,
                'status_label' => $pr->status?->getLabel(),
                'hold_amount' => (float) ($pr->hold_amount ?? 0),
                'total_est_cost' => (float) ($pr->total_est_cost ?? 0),
            ])
            ->values();

        return response()->json([
            'sub_budget' => [
                'id' => $subBudget->id,
                'code' => $subBudget->code,
                'name' => $subBudget->name,
            ],
            'records' => $records,
        ]);
    }

    public function update(Request $request, BudgetAccounts $budgetAccount): RedirectResponse
    {
        $this->authorize('update', $budgetAccount);

        $budgetAccount->update($this->validatedAccount($request));

        return redirect()
            ->route('app.budget-accounts.edit', $budgetAccount)
            ->with('success', 'Budget account updated.');
    }

    public function destroy(BudgetAccounts $budgetAccount): RedirectResponse
    {
        $this->authorize('delete', $budgetAccount);

        $budgetAccount->delete();

        return redirect()
            ->route('app.budget-accounts.index')
            ->with('success', 'Budget account deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', BudgetAccounts::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:budget_accounts,id'],
        ]);

        BudgetAccounts::query()->whereIn('id', $data['ids'])->delete();

        return back()->with('success', 'Budget accounts deleted.');
    }

    public function storeSubBudget(Request $request, BudgetAccounts $budgetAccount): RedirectResponse
    {
        $this->authorize('update', $budgetAccount);

        $data = $this->validatedSubBudget($request);

        DB::transaction(function () use ($budgetAccount, $data): void {
            $sub = $budgetAccount->subBudgetAccounts()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'display_name' => $data['display_name'] ?? null,
            ]);

            foreach ($data['allocations'] as $allocation) {
                $sub->allocations()->create([
                    'department_id' => $allocation['department_id'],
                    'location_id' => $allocation['location_id'] ?? null,
                    'amount' => $allocation['amount'],
                ]);
            }
        });

        return back()->with('success', 'Sub budget created.');
    }

    public function updateSubBudget(Request $request, BudgetAccounts $budgetAccount, SubBudgetAccounts $subBudget): RedirectResponse
    {
        $this->authorize('update', $budgetAccount);
        $this->ensureSubBelongsToAccount($budgetAccount, $subBudget);

        $data = $this->validatedSubBudget($request);

        DB::transaction(function () use ($subBudget, $data): void {
            $subBudget->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'display_name' => $data['display_name'] ?? null,
            ]);

            $keptIds = [];

            foreach ($data['allocations'] as $allocation) {
                if (! empty($allocation['id'])) {
                    $existing = $subBudget->allocations()->whereKey($allocation['id'])->first();
                    if ($existing) {
                        $existing->update([
                            'department_id' => $allocation['department_id'],
                            'location_id' => $allocation['location_id'] ?? null,
                            'amount' => $allocation['amount'],
                        ]);
                        $keptIds[] = $existing->id;

                        continue;
                    }
                }

                $created = $subBudget->allocations()->create([
                    'department_id' => $allocation['department_id'],
                    'location_id' => $allocation['location_id'] ?? null,
                    'amount' => $allocation['amount'],
                ]);
                $keptIds[] = $created->id;
            }

            $subBudget->allocations()->whereNotIn('id', $keptIds)->delete();
        });

        return back()->with('success', 'Sub budget updated.');
    }

    public function destroySubBudget(BudgetAccounts $budgetAccount, SubBudgetAccounts $subBudget): RedirectResponse
    {
        $this->authorize('update', $budgetAccount);
        $this->ensureSubBelongsToAccount($budgetAccount, $subBudget);

        $subBudget->delete();

        return back()->with('success', 'Sub budget deleted.');
    }

    public function destroySubBudgetsMany(Request $request, BudgetAccounts $budgetAccount): RedirectResponse
    {
        $this->authorize('update', $budgetAccount);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $budgetAccount->subBudgetAccounts()
            ->whereIn('id', $data['ids'])
            ->delete();

        return back()->with('success', 'Sub budgets deleted.');
    }

    public function topUp(Request $request, BudgetAccounts $budgetAccount): RedirectResponse
    {
        $this->authorize('update', $budgetAccount);

        $data = $request->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.id' => ['required', 'integer'],
            'allocations.*.sub_budget_account_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric'],
        ]);

        TopUpBudgetAccount::run($budgetAccount, $data['allocations'], $request->user()->id);

        return back()->with('success', 'Top Up Successful');
    }

    private function validatedAccount(Request $request): array
    {
        return $request->validate([
            'expenditure_type' => ['required', 'string', 'max:255'],
            'account' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function validatedSubBudget(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.id' => ['nullable', 'integer'],
            'allocations.*.department_id' => ['required', 'integer', 'exists:departments,id'],
            'allocations.*.location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'allocations.*.amount' => ['required', 'numeric'],
        ]);
    }

    private function ensureSubBelongsToAccount(BudgetAccounts $budgetAccount, SubBudgetAccounts $subBudget): void
    {
        abort_unless((int) $subBudget->budget_account_id === (int) $budgetAccount->id, 404);
    }
}
