<?php

namespace App\Http\Controllers\App;

use App\Actions\Budgets\CreateBudgetTransfer;
use App\Http\Controllers\Controller;
use App\Models\BudgetTransfer;
use App\Models\SubBudgetAccounts;
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

        $transfers = BudgetTransfer::query()
            ->with(['fromBudget', 'toBudget', 'user'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('fromBudget', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('toBudget', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BudgetTransfer $transfer): array => [
                'id' => $transfer->id,
                'from_budget' => $transfer->fromBudget?->getSelectLabel(),
                'to_budget' => $transfer->toBudget?->getSelectLabel(),
                'user' => $transfer->user?->name,
                'amount' => $transfer->amount,
                'description' => $transfer->description,
                'created_at' => optional($transfer->created_at)?->toDateTimeString(),
            ]);

        return Inertia::render('BudgetTransfers/Index', [
            'transfers' => $transfers,
            'filters' => ['search' => $search],
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
                'label' => $budget->getSelectLabel().' — MVR '.number_format($budget->total_amount, 2),
                'total_amount' => $budget->total_amount,
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
