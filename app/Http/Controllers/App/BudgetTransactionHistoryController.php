<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BudgetTransactionHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetTransactionHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BudgetTransactionHistory::class);

        $search = $request->string('search')->trim()->toString();

        $histories = BudgetTransactionHistory::query()
            ->with(['subBudget', 'transactionBy'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('transaction_type', 'like', "%{$search}%")
                        ->orWhere('transaction_details', 'like', "%{$search}%")
                        ->orWhereHas('subBudget', fn ($q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (BudgetTransactionHistory $history): array => [
                'id' => $history->id,
                'sub_budget_name' => $history->subBudget?->name,
                'sub_budget_code' => $history->subBudget?->code,
                'transaction_type' => $history->transaction_type,
                'transaction_date' => optional($history->transaction_date)?->format('Y-m-d')
                    ?? (string) $history->transaction_date,
                'transaction_details' => $history->transaction_details,
                'transaction_amount' => $history->transaction_amount,
                'transaction_balance' => $history->transaction_balance,
                'transaction_by' => $history->transactionBy?->name,
            ]);

        return Inertia::render('BudgetTransactionHistories/Index', [
            'histories' => $histories,
            'filters' => ['search' => $search],
            'can' => [
                'deleteAny' => $request->user()->can('deleteAny', BudgetTransactionHistory::class),
            ],
        ]);
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', BudgetTransactionHistory::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:budget_transaction_histories,id'],
        ]);

        BudgetTransactionHistory::query()->whereIn('id', $data['ids'])->delete();

        return back()->with('success', 'History records deleted.');
    }
}
