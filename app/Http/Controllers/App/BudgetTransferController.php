<?php

namespace App\Http\Controllers\App;

use App\Actions\Budgets\CreateBudgetTransfer;
use App\Http\Controllers\Controller;
use App\Models\BudgetTransfer;
use App\Models\SubBudgetAccounts;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetTransferController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BudgetTransfer::class);

        $search = $request->string('search')->trim()->toString();
        $userId = $request->integer('user_id') ?: null;
        $dateFrom = $request->string('date_from')->trim()->toString() ?: null;
        $dateTo = $request->string('date_to')->trim()->toString() ?: null;

        $transfers = BudgetTransfer::query()
            ->with(['fromBudget', 'toBudget', 'user'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhereHas('fromBudget', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('toBudget', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BudgetTransfer $transfer): array => [
                'id' => $transfer->id,
                'from_budget' => $transfer->fromBudget?->getSelectLabel(),
                'from_code' => $transfer->fromBudget?->code,
                'to_budget' => $transfer->toBudget?->getSelectLabel(),
                'to_code' => $transfer->toBudget?->code,
                'user' => $transfer->user?->name,
                'amount' => (float) $transfer->amount,
                'description' => $transfer->description,
                'created_at' => optional($transfer->created_at)?->toIso8601String(),
                'created_at_label' => optional($transfer->created_at)?->format('d/m/Y h:i A'),
                'day' => optional($transfer->created_at)?->toDateString(),
            ]);

        $userIds = BudgetTransfer::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->orderBy('user_id')
            ->limit(300)
            ->pluck('user_id');

        return Inertia::render('BudgetTransfers/Index', [
            'transfers' => $transfers,
            'filters' => [
                'search' => $search,
                'user_id' => $userId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'filterOptions' => [
                'users' => User::query()
                    ->whereIn('id', $userIds)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'can' => [
                'create' => $request->user()->can('create', BudgetTransfer::class),
                'deleteAny' => $request->user()->can('deleteAny', BudgetTransfer::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', BudgetTransfer::class);

        $budgets = SubBudgetAccounts::query()
            ->with('allocations')
            ->orderBy('code')
            ->get()
            ->map(fn (SubBudgetAccounts $budget): array => [
                'id' => $budget->id,
                'code' => $budget->code,
                'name' => $budget->name,
                'label' => $budget->getSelectLabel(),
                'total_amount' => (float) $budget->total_amount,
            ]);

        return Inertia::render('BudgetTransfers/Create', [
            'budgets' => $budgets,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BudgetTransfer::class);

        $data = $request->validate([
            'from_budget_id' => ['required', 'integer', 'exists:sub_budget_accounts,id'],
            'to_budget_id' => ['required', 'integer', 'exists:sub_budget_accounts,id', 'different:from_budget_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
        ]);

        CreateBudgetTransfer::run([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('app.budget-transfers.index')
            ->with('success', 'Budget transferred successfully');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', BudgetTransfer::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:budget_transfers,id'],
        ]);

        BudgetTransfer::query()->whereIn('id', $data['ids'])->delete();

        return back()->with('success', 'Transfers deleted.');
    }
}
