<?php

namespace App\Http\Controllers\App;

use App\Actions\PettyCash\AddPettyCashPvNumbers;
use App\Actions\PettyCash\ApprovePettyCashByDepartment;
use App\Actions\PettyCash\ApprovePettyCashByFinance;
use App\Actions\PettyCash\CreatePettyCashReimbursment;
use App\Actions\PettyCash\RejectPettyCashByDepartment;
use App\Actions\PettyCash\RejectPettyCashByFinance;
use App\Actions\PettyCash\SubmitPettyCashReimbursment;
use App\Actions\PettyCash\UpdatePettyCashReimbursment;
use App\Enums\PettyCashStatus;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Departments;
use App\Models\PettyCashReimbursment;
use App\Models\PettyCashReimbursmentDetail;
use App\Models\PurchaseOrderDetails;
use App\Models\PurchaseOrders;
use App\Models\SubBudgetAccounts;
use App\Models\Vendors;
use App\Support\PinnedTabs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PettyCashReimbursmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PettyCashReimbursment::class);

        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $departmentId = $request->integer('department_id') ?: null;
        $dateFrom = $request->string('date_from')->trim()->toString() ?: null;
        $dateTo = $request->string('date_to')->trim()->toString() ?: null;
        $sort = $request->string('sort')->trim()->toString() ?: 'newest';

        $validStatuses = collect(PettyCashStatus::cases())->map->value->all();
        $allowedTabs = ['all', ...$validStatuses];
        $pinnedTab = $user->pinnedTab(PinnedTabs::PETTY_CASH);
        $tab = PinnedTabs::resolve(
            $request->string('tab')->trim()->toString() ?: null,
            $pinnedTab,
            $allowedTabs,
        );

        $sortMap = [
            'newest' => ['id', 'desc'],
            'oldest' => ['id', 'asc'],
            'date_desc' => ['date', 'desc'],
            'date_asc' => ['date', 'asc'],
            'form_no_asc' => ['form_no', 'asc'],
            'form_no_desc' => ['form_no', 'desc'],
            'amount_desc' => ['details_total', 'desc'],
            'amount_asc' => ['details_total', 'asc'],
        ];

        if (! array_key_exists($sort, $sortMap)) {
            $sort = 'newest';
        }

        [$sortColumn, $sortDirection] = $sortMap[$sort];

        $baseQuery = fn () => $this->scopedQuery($user)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('form_no', 'like', "%{$search}%")
                        ->orWhere('id', $search)
                        ->orWhere('pv_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($departmentId, fn (Builder $query) => $query->whereHas(
                'user',
                fn (Builder $q) => $q->where('department_id', $departmentId),
            ))
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('date', '<=', $dateTo));

        $tabStatuses = [
            PettyCashStatus::Draft,
            PettyCashStatus::Submitted,
            PettyCashStatus::DepApproved,
            PettyCashStatus::Dep_Reject,
            PettyCashStatus::FinApproved,
            PettyCashStatus::Fin_Reject,
            PettyCashStatus::Rembursed,
        ];

        $tabs = [
            [
                'key' => 'all',
                'label' => 'All',
                'badge' => $baseQuery()->count(),
                'tone' => 'neutral',
            ],
            ...collect($tabStatuses)->map(fn (PettyCashStatus $status): array => [
                'key' => $status->value,
                'label' => $status->getLabel(),
                'badge' => $baseQuery()->where('status', $status->value)->count(),
                'tone' => match ($status) {
                    PettyCashStatus::Draft => 'neutral',
                    PettyCashStatus::Submitted, PettyCashStatus::DepApproved, PettyCashStatus::FinApproved => 'warn',
                    PettyCashStatus::Rembursed => 'success',
                    PettyCashStatus::Dep_Reject, PettyCashStatus::Fin_Reject => 'danger',
                },
            ])->all(),
        ];

        $records = $baseQuery()
            ->with(['user.department.user', 'pettyCashReimbursmentDetails'])
            ->withSum('pettyCashReimbursmentDetails as details_total', 'amount')
            ->withCount('pettyCashReimbursmentDetails as details_count')
            ->when($tab !== 'all', fn (Builder $query) => $query->where('status', $tab))
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'id', fn (Builder $query) => $query->orderByDesc('id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PettyCashReimbursment $record): array => $this->listPayload($record, $user));

        return Inertia::render('PettyCash/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'tab' => $tab,
                'department_id' => $departmentId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
            ],
            'filterOptions' => [
                'departments' => Departments::query()->orderBy('name')->get(['id', 'name']),
                'sorts' => [
                    ['value' => 'newest', 'label' => 'Newest first'],
                    ['value' => 'oldest', 'label' => 'Oldest first'],
                    ['value' => 'date_desc', 'label' => 'Date (newest)'],
                    ['value' => 'date_asc', 'label' => 'Date (oldest)'],
                    ['value' => 'form_no_asc', 'label' => 'Form no (A–Z)'],
                    ['value' => 'form_no_desc', 'label' => 'Form no (Z–A)'],
                    ['value' => 'amount_desc', 'label' => 'Amount (high–low)'],
                    ['value' => 'amount_asc', 'label' => 'Amount (low–high)'],
                ],
            ],
            'tabs' => $tabs,
            'pinnedTab' => $pinnedTab,
            'can' => [
                'create' => $user->can('create', PettyCashReimbursment::class),
                'deleteAny' => $user->can('deleteAny', PettyCashReimbursment::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PettyCashReimbursment::class);

        return Inertia::render('PettyCash/Form', [
            'reimbursment' => null,
            'formNoPreview' => PettyCashReimbursment::generateNextFormNo(),
            'options' => $this->formOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PettyCashReimbursment::class);

        $header = $this->validatedHeader($request, creating: true);
        $details = $this->validatedDetails($request);

        $reimbursment = CreatePettyCashReimbursment::run([
            'date' => $header['date'],
            'details' => $details['details'],
            'supporting_documents' => $this->storeSupportingDocument($request),
        ], $request->user()->id);

        return redirect()
            ->route('app.petty-cash.show', $reimbursment)
            ->with('success', 'Petty cash reimbursement created.');
    }

    public function show(Request $request, PettyCashReimbursment $pettyCashReimbursment): Response
    {
        $this->authorize('view', $pettyCashReimbursment);
        $this->ensureInScopedQuery($request, $pettyCashReimbursment);

        $pettyCashReimbursment->load([
            'user.department.user',
            'VerifiedBy',
            'ApprovedBy',
            'pettyCashReimbursmentDetails.vendor',
            'pettyCashReimbursmentDetails.items',
            'pettyCashReimbursmentDetails.subBudget',
            'pettyCashReimbursmentDetails.purchaseOrder.purchaseRequest',
        ]);

        $user = $request->user();

        return Inertia::render('PettyCash/Show', [
            'reimbursment' => $this->showPayload($pettyCashReimbursment),
            'options' => $this->formOptions($request),
            'can' => $this->actionCapabilities($user, $pettyCashReimbursment),
        ]);
    }

    public function edit(Request $request, PettyCashReimbursment $pettyCashReimbursment): Response
    {
        $this->authorize('update', $pettyCashReimbursment);
        $this->ensureInScopedQuery($request, $pettyCashReimbursment);

        $pettyCashReimbursment->load(['pettyCashReimbursmentDetails']);
        $includePoIds = $pettyCashReimbursment->pettyCashReimbursmentDetails
            ->pluck('po_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Inertia::render('PettyCash/Form', [
            'reimbursment' => [
                'id' => $pettyCashReimbursment->id,
                'date' => (string) $pettyCashReimbursment->date,
                'form_no' => $pettyCashReimbursment->form_no,
                'status' => $pettyCashReimbursment->status->value,
                'supporting_documents' => $pettyCashReimbursment->supporting_documents,
                'is_draft' => $pettyCashReimbursment->status === PettyCashStatus::Draft,
                'details' => $pettyCashReimbursment->pettyCashReimbursmentDetails->map(fn (PettyCashReimbursmentDetail $detail): array => [
                    'id' => $detail->id,
                    'date' => (string) $detail->date,
                    'is_from_pr' => $detail->po_id !== null,
                    'po_id' => $detail->po_id ?: '',
                    'Vendor_id' => $detail->Vendor_id,
                    'bill_no' => $detail->bill_no,
                    'item_id' => $detail->item_id ?: '',
                    'details' => $detail->details ?? '',
                    'sub_budget_id' => $detail->sub_budget_id,
                    'amount' => $detail->amount,
                ])->values(),
            ],
            'formNoPreview' => $pettyCashReimbursment->form_no,
            'options' => $this->formOptions($request, $includePoIds),
        ]);
    }

    public function update(Request $request, PettyCashReimbursment $pettyCashReimbursment): RedirectResponse
    {
        $this->authorize('update', $pettyCashReimbursment);

        $data = $this->validatedHeader($request, creating: false, reimbursment: $pettyCashReimbursment);
        unset($data['supporting_documents']);

        if ($request->hasFile('supporting_documents')) {
            $data['supporting_documents'] = $this->storeSupportingDocument($request);
        }

        if ($pettyCashReimbursment->status === PettyCashStatus::Draft) {
            $data['details'] = $this->validatedDetails($request)['details'];
        }

        UpdatePettyCashReimbursment::run($pettyCashReimbursment, $data);

        return redirect()
            ->route('app.petty-cash.show', $pettyCashReimbursment)
            ->with('success', 'Petty cash reimbursement updated.');
    }

    public function destroy(PettyCashReimbursment $pettyCashReimbursment): RedirectResponse
    {
        $this->authorize('delete', $pettyCashReimbursment);

        $pettyCashReimbursment->pettyCashReimbursmentDetails()->delete();
        $pettyCashReimbursment->delete();

        return redirect()
            ->route('app.petty-cash.index')
            ->with('success', 'Petty cash reimbursement deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', PettyCashReimbursment::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:petty_cash_reimbursments,id'],
        ]);

        $records = PettyCashReimbursment::query()->whereIn('id', $data['ids'])->get();
        foreach ($records as $record) {
            $record->pettyCashReimbursmentDetails()->delete();
            $record->delete();
        }

        return back()->with('success', 'Petty cash reimbursements deleted.');
    }

    public function submit(Request $request, PettyCashReimbursment $pettyCashReimbursment): RedirectResponse
    {
        $this->authorize('update', $pettyCashReimbursment);

        SubmitPettyCashReimbursment::run($pettyCashReimbursment);

        return back()->with('success', 'Petty cash reimbursement submitted.');
    }

    public function approveDepartment(Request $request, PettyCashReimbursment $pettyCashReimbursment): RedirectResponse
    {
        $this->authorize('view', $pettyCashReimbursment);

        ApprovePettyCashByDepartment::run($pettyCashReimbursment, $request->user()->id);

        return back()->with('success', 'Approved by department HOD.');
    }

    public function rejectDepartment(Request $request, PettyCashReimbursment $pettyCashReimbursment): RedirectResponse
    {
        $this->authorize('view', $pettyCashReimbursment);

        RejectPettyCashByDepartment::run($pettyCashReimbursment, $request->user()->id);

        return back()->with('success', 'Rejected by department HOD — returned to draft.');
    }

    public function addPv(Request $request, PettyCashReimbursment $pettyCashReimbursment): RedirectResponse
    {
        abort_unless($request->user()->can('pv_approve_petty::cash::reimbursment'), 403);

        $data = $request->validate([
            'pv_numbers' => ['required', 'array', 'min:1'],
            'pv_numbers.*' => ['required', 'string', 'max:255'],
        ]);

        AddPettyCashPvNumbers::run($pettyCashReimbursment, $data['pv_numbers'], $request->user()->id);

        return back()->with('success', 'PV numbers saved.');
    }

    public function approveFinance(Request $request, PettyCashReimbursment $pettyCashReimbursment): RedirectResponse
    {
        abort_unless($request->user()->can('fin_hod_approve_petty::cash::reimbursment'), 403);

        ApprovePettyCashByFinance::run($pettyCashReimbursment, $request->user()->id);

        return back()->with('success', 'Finance approved — reimbursed.');
    }

    public function rejectFinance(Request $request, PettyCashReimbursment $pettyCashReimbursment): RedirectResponse
    {
        abort_unless($request->user()->can('fin_hod_approve_petty::cash::reimbursment'), 403);

        RejectPettyCashByFinance::run($pettyCashReimbursment);

        return back()->with('success', 'Rejected by finance — returned to draft.');
    }

    public function storeDetail(Request $request, PettyCashReimbursment $pettyCashReimbursment): RedirectResponse
    {
        $this->authorize('update', $pettyCashReimbursment);
        $this->assertDraft($pettyCashReimbursment);

        $detail = $this->validatedSingleDetail($request);

        PettyCashReimbursmentDetail::create([
            'petty_cash_reimb_id' => $pettyCashReimbursment->id,
            ...$detail,
        ]);

        return back()->with('success', 'Line item added.');
    }

    public function updateDetail(
        Request $request,
        PettyCashReimbursment $pettyCashReimbursment,
        PettyCashReimbursmentDetail $detail,
    ): RedirectResponse {
        $this->authorize('update', $pettyCashReimbursment);
        $this->assertDraft($pettyCashReimbursment);
        $this->ensureDetailBelongs($pettyCashReimbursment, $detail);

        $detail->update($this->validatedSingleDetail($request));

        return back()->with('success', 'Line item updated.');
    }

    public function destroyDetail(
        PettyCashReimbursment $pettyCashReimbursment,
        PettyCashReimbursmentDetail $detail,
    ): RedirectResponse {
        $this->authorize('update', $pettyCashReimbursment);
        $this->assertDraft($pettyCashReimbursment);
        $this->ensureDetailBelongs($pettyCashReimbursment, $detail);

        $detail->delete();

        return back()->with('success', 'Line item deleted.');
    }

    private function scopedQuery($user): Builder
    {
        $query = PettyCashReimbursment::query();

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        $isHod = Departments::where('hod', $user->id)->exists();

        if ($isHod) {
            return $query->where('status', '!=', PettyCashStatus::Draft->value);
        }

        if ($user->can('pv_approve_petty::cash::reimbursment')) {
            return $query
                ->where('status', '!=', PettyCashStatus::Draft->value)
                ->where('status', '!=', PettyCashStatus::Submitted->value);
        }

        return $query->where('user_id', $user->id);
    }

    private function ensureInScopedQuery(Request $request, PettyCashReimbursment $record): void
    {
        $exists = $this->scopedQuery($request->user())->whereKey($record->id)->exists();
        abort_unless($exists, 404);
    }

    private function validatedHeader(Request $request, bool $creating, ?PettyCashReimbursment $reimbursment = null): array
    {
        $dateRules = $creating || ($reimbursment?->status === PettyCashStatus::Draft)
            ? ['required', 'date']
            : ['sometimes', 'date'];

        return $request->validate([
            'date' => $dateRules,
            'supporting_documents' => ['nullable', 'file', 'max:10240'],
        ]);
    }

    /**
     * @return array{details: array<int, array<string, mixed>>}
     */
    private function validatedDetails(Request $request): array
    {
        $request->validate([
            'details' => ['required', 'array', 'min:1'],
            'details.*.date' => ['required', 'date'],
            'details.*.is_from_pr' => ['required', 'boolean'],
            'details.*.Vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'details.*.bill_no' => ['required', 'string', 'max:255'],
            'details.*.sub_budget_id' => ['required', 'integer', 'exists:sub_budget_accounts,id'],
            'details.*.amount' => ['required', 'numeric', 'min:0.01'],
            'details.*.po_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'details.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'details.*.details' => ['nullable', 'string', 'max:255'],
        ]);

        $details = [];

        foreach ($request->input('details', []) as $index => $row) {
            $fromPr = filter_var($row['is_from_pr'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($fromPr) {
                if (empty($row['po_id']) || empty($row['item_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "details.{$index}.po_id" => 'PO and item are required when the line is from a PR.',
                    ]);
                }

                $details[] = [
                    'date' => $row['date'],
                    'Vendor_id' => (int) $row['Vendor_id'],
                    'bill_no' => $row['bill_no'],
                    'sub_budget_id' => (int) $row['sub_budget_id'],
                    'amount' => $row['amount'],
                    'po_id' => (int) $row['po_id'],
                    'item_id' => (int) $row['item_id'],
                    'details' => '',
                ];

                continue;
            }

            if (blank($row['details'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "details.{$index}.details" => 'Description is required when the line is not from a PR.',
                ]);
            }

            $details[] = [
                'date' => $row['date'],
                'Vendor_id' => (int) $row['Vendor_id'],
                'bill_no' => $row['bill_no'],
                'sub_budget_id' => (int) $row['sub_budget_id'],
                'amount' => $row['amount'],
                'po_id' => null,
                'item_id' => null,
                'details' => $row['details'],
            ];
        }

        return ['details' => $details];
    }

    private function validatedSingleDetail(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'is_from_pr' => ['required', 'boolean'],
            'Vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'bill_no' => ['required', 'string', 'max:255'],
            'sub_budget_id' => ['required', 'integer', 'exists:sub_budget_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'po_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'details' => ['nullable', 'string', 'max:255'],
        ]);

        $fromPr = filter_var($data['is_from_pr'], FILTER_VALIDATE_BOOLEAN);

        if ($fromPr) {
            if (empty($data['po_id']) || empty($data['item_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'po_id' => 'PO and item are required when the line is from a PR.',
                ]);
            }

            return [
                'date' => $data['date'],
                'Vendor_id' => (int) $data['Vendor_id'],
                'bill_no' => $data['bill_no'],
                'sub_budget_id' => (int) $data['sub_budget_id'],
                'amount' => $data['amount'],
                'po_id' => (int) $data['po_id'],
                'item_id' => (int) $data['item_id'],
                'details' => '',
            ];
        }

        if (blank($data['details'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'details' => 'Description is required when the line is not from a PR.',
            ]);
        }

        return [
            'date' => $data['date'],
            'Vendor_id' => (int) $data['Vendor_id'],
            'bill_no' => $data['bill_no'],
            'sub_budget_id' => (int) $data['sub_budget_id'],
            'amount' => $data['amount'],
            'po_id' => null,
            'item_id' => null,
            'details' => $data['details'],
        ];
    }

    private function storeSupportingDocument(Request $request): ?string
    {
        if (! $request->hasFile('supporting_documents')) {
            return null;
        }

        return $request->file('supporting_documents')->store('petty-cash-supporting', 'public');
    }

    private function formOptions(Request $request, array $includePoIds = []): array
    {
        $departmentId = $request->user()?->department_id;

        $budgets = SubBudgetAccounts::with(['allocations' => function ($query) use ($departmentId): void {
            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }
        }])
            ->get()
            ->filter(function ($row) use ($departmentId) {
                return $departmentId
                    ? $row->allocations->firstWhere('department_id', $departmentId)
                    : $row->allocations->isNotEmpty();
            })
            ->map(function (SubBudgetAccounts $row) use ($departmentId): array {
                $allocation = $departmentId
                    ? $row->allocations->firstWhere('department_id', $departmentId)
                    : $row->allocations->first();

                return [
                    'id' => $row->id,
                    'label' => $row->getSelectLabel(),
                    'allocated_amount' => $allocation?->amount,
                ];
            })
            ->values();

        $purchaseOrders = PurchaseOrders::query()
            ->where('payment_method', 'petty_cash')
            ->where(function ($query) use ($includePoIds): void {
                $query->where('status', PurchaseOrderStatus::WaitingReimbursement->value);
                if ($includePoIds !== []) {
                    $query->orWhereIn('id', $includePoIds);
                }
            })
            ->with(['purchaseRequest', 'purchaseOrderDetails.items'])
            ->get()
            ->map(function (PurchaseOrders $po): array {
                $prNo = $po->purchaseRequest?->pr_no ?? 'N/A';

                return [
                    'id' => $po->id,
                    'label' => "{$prNo} ({$po->po_no})",
                    'vendor_id' => $po->vendor_id,
                    'items' => $po->purchaseOrderDetails
                        ->filter(fn ($d) => $d->items !== null)
                        ->map(fn (PurchaseOrderDetails $d): array => [
                            'item_id' => $d->item_id,
                            'name' => $d->items?->name,
                            'amount' => $d->amount,
                            'budget_account_id' => $d->budget_account_id,
                        ])
                        ->values(),
                ];
            })
            ->values();

        $vendors = Vendors::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Vendors $vendor): array => [
                'id' => $vendor->id,
                'name' => $vendor->name ?? 'Vendor #'.$vendor->id,
            ]);

        return [
            'budgets' => $budgets,
            'purchaseOrders' => $purchaseOrders,
            'vendors' => $vendors,
        ];
    }

    private function listPayload(PettyCashReimbursment $record, $user): array
    {
        $actions = $this->actionCapabilities($user, $record);

        return [
            'id' => $record->id,
            'form_no' => $record->form_no,
            'date' => (string) $record->date,
            'requested_by' => $record->user?->name,
            'department' => $record->user?->department?->name,
            'pv_numbers' => $this->formatPvNumbers($record->pv_number),
            'line_count' => (int) ($record->details_count ?? $record->pettyCashReimbursmentDetails->count()),
            'total_amount' => (float) ($record->details_total ?? $record->pettyCashReimbursmentDetails->sum('amount')),
            'status' => $record->status->value,
            'status_label' => $record->status->getLabel(),
            'actions' => [
                'edit' => $actions['update'],
                'submit' => $actions['submit'],
                'delete' => $actions['delete'],
                'dep_approve' => $actions['dep_approve'],
                'dep_reject' => $actions['dep_reject'],
                'add_pv' => $actions['add_pv'],
                'fin_approve' => $actions['fin_approve'],
                'fin_reject' => $actions['fin_reject'],
            ],
        ];
    }

    private function showPayload(PettyCashReimbursment $record): array
    {
        return [
            'id' => $record->id,
            'form_no' => $record->form_no,
            'date' => (string) $record->date,
            'status' => $record->status->value,
            'status_label' => $record->status->getLabel(),
            'requested_by' => $record->user?->name,
            'department' => $record->user?->department?->name,
            'pv_numbers' => $this->formatPvNumbers($record->pv_number),
            'has_pv_numbers' => $record->hasPvNumbers(),
            'verified_by' => $record->VerifiedBy?->name,
            'approved_by' => $record->ApprovedBy?->name,
            'supporting_documents' => $record->supporting_documents,
            'supporting_documents_url' => $record->supporting_documents
                ? Storage::disk('public')->url($record->supporting_documents)
                : null,
            'total_amount' => $record->pettyCashReimbursmentDetails->sum('amount'),
            'pdf_url' => $record->status === PettyCashStatus::Rembursed
                ? route('petty-cash.preview', $record)
                : null,
            'details' => $record->pettyCashReimbursmentDetails->map(function (PettyCashReimbursmentDetail $detail): array {
                $po = $detail->purchaseOrder;
                $prNo = $po?->purchaseRequest?->pr_no ?? 'N/A';
                $poNo = $po?->po_no ?? 'N/A';

                return [
                    'id' => $detail->id,
                    'date' => (string) $detail->date,
                    'Vendor_id' => $detail->Vendor_id,
                    'vendor_name' => $detail->vendor?->name,
                    'bill_no' => $detail->bill_no,
                    'sub_budget_id' => $detail->sub_budget_id,
                    'sub_budget_code' => $detail->subBudget?->code,
                    'sub_budget_label' => $detail->subBudget?->getSelectLabel(),
                    'item_id' => $detail->item_id,
                    'details' => $detail->details,
                    'description' => $detail->items?->name ?? $detail->details,
                    'po_id' => $detail->po_id,
                    'po_label' => $po ? "{$poNo} ({$prNo})" : null,
                    'amount' => $detail->amount,
                    'is_from_pr' => $detail->po_id !== null,
                ];
            })->values(),
        ];
    }

    private function actionCapabilities($user, PettyCashReimbursment $record): array
    {
        $hodId = $record->user?->department?->user?->id
            ?? $record->user?->department?->hod;
        $isRequesterHod = (int) $hodId === (int) $user->id;
        $status = $record->status;
        $hasPv = $record->hasPvNumbers();

        return [
            'update' => $user->can('update', $record),
            'delete' => $user->can('delete', $record),
            'manage_details' => $status === PettyCashStatus::Draft && $user->can('update', $record),
            'submit' => $status === PettyCashStatus::Draft && $user->can('update', $record),
            'dep_approve' => $status === PettyCashStatus::Submitted && $isRequesterHod,
            'dep_reject' => $status === PettyCashStatus::Submitted && $isRequesterHod,
            'add_pv' => in_array($status, [PettyCashStatus::DepApproved, PettyCashStatus::FinApproved], true)
                && $user->can('pv_approve_petty::cash::reimbursment')
                && ! $hasPv,
            'fin_approve' => in_array($status, [PettyCashStatus::DepApproved, PettyCashStatus::FinApproved], true)
                && $hasPv
                && $user->can('fin_hod_approve_petty::cash::reimbursment'),
            'fin_reject' => $status === PettyCashStatus::DepApproved
                && $hasPv
                && $user->can('fin_hod_approve_petty::cash::reimbursment'),
            'download_pdf' => $status === PettyCashStatus::Rembursed,
        ];
    }

    private function formatPvNumbers(?string $state): string
    {
        if (blank($state)) {
            return '';
        }

        $decoded = json_decode($state, true);
        if (! is_array($decoded)) {
            return (string) $state;
        }

        if (isset($decoded[0]) && is_array($decoded[0]) && array_key_exists('pv_number', $decoded[0])) {
            return implode(', ', array_column($decoded, 'pv_number'));
        }

        return implode(', ', $decoded);
    }

    private function assertDraft(PettyCashReimbursment $reimbursment): void
    {
        abort_unless($reimbursment->status === PettyCashStatus::Draft, 403, 'Details can only be changed while draft.');
    }

    private function ensureDetailBelongs(PettyCashReimbursment $reimbursment, PettyCashReimbursmentDetail $detail): void
    {
        abort_unless((int) $detail->petty_cash_reimb_id === (int) $reimbursment->id, 404);
    }
}
