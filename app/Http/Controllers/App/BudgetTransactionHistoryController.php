<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BudgetTransactionHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BudgetTransactionHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BudgetTransactionHistory::class);

        $search = $request->string('search')->trim()->toString();
        $tab = $request->string('tab')->trim()->toString() ?: 'all';
        $userId = $request->integer('user_id') ?: null;
        $dateFrom = $request->string('date_from')->trim()->toString() ?: null;
        $dateTo = $request->string('date_to')->trim()->toString() ?: null;

        $baseQuery = fn () => BudgetTransactionHistory::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('transaction_type', 'like', "%{$search}%")
                        ->orWhere('transaction_details', 'like', "%{$search}%")
                        ->orWhereHas('subBudget', fn ($q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('transactionBy', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($userId, fn ($query) => $query->where('transaction_by', $userId))
            ->when($dateFrom, fn ($query) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('transaction_date', '<=', $dateTo));

        $types = BudgetTransactionHistory::query()
            ->select('transaction_type')
            ->whereNotNull('transaction_type')
            ->where('transaction_type', '!=', '')
            ->distinct()
            ->orderBy('transaction_type')
            ->pluck('transaction_type')
            ->values();

        if ($tab !== 'all' && ! $types->contains($tab)) {
            $tab = 'all';
        }

        $tabs = [
            [
                'key' => 'all',
                'label' => 'All',
                'badge' => $baseQuery()->count(),
                'tone' => 'neutral',
            ],
            ...$types->map(fn (string $type): array => [
                'key' => $type,
                'label' => Str::headline($type),
                'badge' => $baseQuery()->where('transaction_type', $type)->count(),
                'tone' => $this->typeTone($type),
            ])->all(),
        ];

        $histories = $baseQuery()
            ->with(['subBudget', 'transactionBy'])
            ->when($tab !== 'all', fn ($query) => $query->where('transaction_type', $tab))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (BudgetTransactionHistory $history): array => [
                'id' => $history->id,
                'sub_budget_name' => $history->subBudget?->getSelectLabel() ?: $history->subBudget?->name,
                'sub_budget_code' => $history->subBudget?->code,
                'transaction_type' => $history->transaction_type,
                'transaction_type_label' => $history->transaction_type
                    ? Str::headline($history->transaction_type)
                    : 'Transaction',
                'transaction_date' => optional($history->transaction_date)?->toDateString()
                    ?? (string) $history->transaction_date,
                'transaction_date_label' => optional($history->transaction_date)?->format('d/m/Y')
                    ?? (string) $history->transaction_date,
                'transaction_details' => $history->transaction_details,
                'transaction_amount' => (float) $history->transaction_amount,
                'transaction_balance' => (float) $history->transaction_balance,
                'transaction_by' => $history->transactionBy?->name,
                'direction' => $this->typeDirection($history->transaction_type),
                'day' => optional($history->transaction_date)?->toDateString()
                    ?? optional($history->created_at)?->toDateString(),
            ]);

        $userIds = BudgetTransactionHistory::query()
            ->whereNotNull('transaction_by')
            ->distinct()
            ->orderBy('transaction_by')
            ->limit(300)
            ->pluck('transaction_by');

        return Inertia::render('BudgetTransactionHistories/Index', [
            'histories' => $histories,
            'filters' => [
                'search' => $search,
                'tab' => $tab,
                'user_id' => $userId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'tabs' => $tabs,
            'filterOptions' => [
                'users' => User::query()
                    ->whereIn('id', $userIds)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
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

    private function typeTone(string $type): string
    {
        return match (Str::lower($type)) {
            'top up', 'topup' => 'success',
            'transfer' => 'brand',
            'purchase order', 'petty cash reimbursement' => 'warn',
            default => 'neutral',
        };
    }

    private function typeDirection(?string $type): string
    {
        return match (Str::lower((string) $type)) {
            'top up', 'topup' => 'in',
            'purchase order', 'petty cash reimbursement' => 'out',
            default => 'neutral',
        };
    }
}
