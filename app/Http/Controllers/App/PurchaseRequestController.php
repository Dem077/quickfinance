<?php

namespace App\Http\Controllers\App;

use App\Actions\PurchaseRequests\ApprovePurchaseRequestByFinance;
use App\Actions\PurchaseRequests\ApprovePurchaseRequestByHod;
use App\Actions\PurchaseRequests\ApprovePurchaseRequestByMdDmd;
use App\Actions\PurchaseRequests\CancelPurchaseRequest;
use App\Actions\PurchaseRequests\ClosePurchaseRequest;
use App\Actions\PurchaseRequests\CreatePurchaseRequest;
use App\Actions\PurchaseRequests\RejectPurchaseRequestByFinance;
use App\Actions\PurchaseRequests\RejectPurchaseRequestByHod;
use App\Actions\PurchaseRequests\RejectPurchaseRequestByMdDmd;
use App\Actions\PurchaseRequests\SendPurchaseRequestBackToDraft;
use App\Actions\PurchaseRequests\SubmitPurchaseRequestForApproval;
use App\Actions\PurchaseRequests\UpsertPurchaseRequestDetail;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestsStatus;
use App\Enums\UnitsEnum;
use App\Http\Controllers\Controller;
use App\Models\Departments;
use App\Models\Item;
use App\Models\Location;
use App\Models\Project;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Models\SubBudgetAccounts;
use App\Models\User;
use App\Support\PurchaseRequestBudget;
use App\Support\PurchaseRequestStatusTabs;
use App\Support\RecordAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseRequests::class);

        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $tabs = PurchaseRequestStatusTabs::forUser($user);
        $tab = PurchaseRequestStatusTabs::resolveActiveTab($user, $request->string('tab')->trim()->toString() ?: null);

        $projectId = $request->integer('project_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;
        $dateFrom = $request->string('date_from')->trim()->toString() ?: null;
        $dateTo = $request->string('date_to')->trim()->toString() ?: null;
        $sort = $request->string('sort')->trim()->toString() ?: 'newest';

        $sortMap = [
            'newest' => ['id', 'desc'],
            'oldest' => ['id', 'asc'],
            'date_desc' => ['date', 'desc'],
            'date_asc' => ['date', 'asc'],
            'pr_no_asc' => ['pr_no', 'asc'],
            'pr_no_desc' => ['pr_no', 'desc'],
        ];

        if (! array_key_exists($sort, $sortMap)) {
            $sort = 'newest';
        }

        [$sortColumn, $sortDirection] = $sortMap[$sort];

        $records = PurchaseRequests::query()
            ->visibleTo($user)
            ->with(['user.department.user', 'project'])
            ->withSum('purchaseRequestDetails as total_est_cost', 'est_cost')
            ->tap(fn ($query) => PurchaseRequestStatusTabs::apply($query, $user, $tab))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('pr_no', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('project', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($departmentId, fn ($query) => $query->whereHas(
                'user',
                fn ($q) => $q->where('department_id', $departmentId),
            ))
            ->when($dateFrom, fn ($query) => $query->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('date', '<=', $dateTo))
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PurchaseRequests $pr): array => $this->listRow($pr, $user));

        return Inertia::render('PurchaseRequests/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'tab' => $tab,
                'project_id' => $projectId,
                'department_id' => $departmentId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
            ],
            'filterOptions' => [
                'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
                'departments' => Departments::query()->orderBy('name')->get(['id', 'name']),
                'sorts' => [
                    ['value' => 'newest', 'label' => 'Newest first'],
                    ['value' => 'oldest', 'label' => 'Oldest first'],
                    ['value' => 'date_desc', 'label' => 'Date (newest)'],
                    ['value' => 'date_asc', 'label' => 'Date (oldest)'],
                    ['value' => 'pr_no_asc', 'label' => 'PR no (A–Z)'],
                    ['value' => 'pr_no_desc', 'label' => 'PR no (Z–A)'],
                ],
            ],
            'tabs' => $tabs,
            'can' => [
                'create' => $user->can('create', PurchaseRequests::class),
                'audit' => $user->can('audit', PurchaseRequests::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseRequests::class);

        return Inertia::render('PurchaseRequests/Form', [
            'record' => null,
            'options' => $this->formOptions($request),
        ]);
    }


    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PurchaseRequests::class);

        $data = $this->validatedHeader($request, true);
        $data['details'] = $request->validate([
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'details.*.unit' => ['required', 'string'],
            'details.*.budget_account_id' => ['required', 'integer', 'exists:sub_budget_accounts,id'],
            'details.*.amount' => ['required', 'numeric'],
            'details.*.est_cost' => ['required', 'numeric'],
        ])['details'];

        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('purchase-requests', 'public');
        }

        $pr = CreatePurchaseRequest::run($data, $request->user()->id, $request->user()->department_id);

        return redirect()
            ->route('app.purchase-requests.show', $pr)
            ->with('success', 'Purchase request created.');
    }

    public function show(Request $request, PurchaseRequests $purchaseRequest): Response
    {
        $this->authorize('view', $purchaseRequest);
        abort_unless($this->visibleToUser($request, $purchaseRequest), 403);

        $purchaseRequest->load([
            'user.department.user',
            'project',
            'locations',
            'purchaseRequestDetails.items',
            'purchaseRequestDetails.budgetAccount',
            'hodapprovedby',
            'approvedby',
            'mdDmdApprovedBy',
        ]);

        $user = $request->user();
        $status = $purchaseRequest->status;
        $actions = $this->workflowActions($purchaseRequest, $user);

        $openPos = $purchaseRequest->openPurchaseOrdersForClose()->map(fn ($po) => [
            'po_id' => $po->id,
            'po_no' => $po->po_no,
            'grn_number' => $po->grn_number,
        ])->values();

        $purchaseOrders = $purchaseRequest->purchaseOrders()
            ->with(['vendor', 'purchaseOrderDetails', 'advanceForm'])
            ->where('status', '!=', PurchaseOrderStatus::Draft->value)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseOrders $po): array => [
                'id' => $po->id,
                'po_no' => $po->po_no,
                'date' => $po->date,
                'status' => $po->status?->value,
                'status_label' => $po->status?->getLabel(),
                'payment_method' => $po->payment_method,
                'payment_method_label' => $po->payment_method === 'purchase_order' ? 'Purchase Order' : 'Petty Cash',
                'vendor' => $po->vendor?->name,
                'grn_number' => $po->grn_number,
                'total_amount' => (float) $po->purchaseOrderDetails->sum('amount'),
                'advance_form_status_label' => $po->advanceForm?->status?->getLabel(),
                'can_view' => $user->can('view', $po),
            ])
            ->values();

        return Inertia::render('PurchaseRequests/Show', [
            'record' => $this->showPayload($purchaseRequest),
            'budgetSummary' => $this->budgetSummary($purchaseRequest, $user),
            'openPurchaseOrders' => $openPos,
            'purchaseOrders' => $purchaseOrders,
            'options' => $this->formOptions(
                $request,
                $purchaseRequest->purchaseRequestDetails->pluck('budget_account_id')->filter()->all(),
                $purchaseRequest->user?->department_id,
                $purchaseRequest->purchaseRequestDetails->pluck('id')->all(),
            ),
            'actions' => [
                ...$actions,
                'downloadPdf' => in_array($status, [PurchaseRequestsStatus::Approved, PurchaseRequestsStatus::MD_DMD_Approved], true)
                    && $user->can('send_approval_purchase::requests'),
                'viewDocument' => filled($purchaseRequest->uploaded_document)
                    && Storage::disk('public')->exists($purchaseRequest->uploaded_document),
                'manageLines' => $status === PurchaseRequestsStatus::Draft && $user->can('send_approval_purchase::requests'),
                'editLineBudget' => $status === PurchaseRequestsStatus::HODApproved
                    && $user->can('approve_purchase::requests'),
                'audit' => $user->can('audit', PurchaseRequests::class),
            ],
            'pdfUrl' => route('purchase-requests.download', $purchaseRequest),
            'documentUrl' => $purchaseRequest->uploaded_document
                ? asset('storage/'.$purchaseRequest->uploaded_document)
                : null,
        ]);
    }

    public function audit(Request $request, PurchaseRequests $purchaseRequest): Response
    {
        $this->authorize('view', $purchaseRequest);
        abort_unless($this->visibleToUser($request, $purchaseRequest), 403);
        $this->authorize('audit', PurchaseRequests::class);

        return Inertia::render('Audit/Show', [
            'title' => $purchaseRequest->pr_no,
            'description' => 'Changes to this purchase request and its line items.',
            'backUrl' => route('app.purchase-requests.show', $purchaseRequest),
            'backLabel' => 'Back to PR',
            'activities' => RecordAudit::paginate(
                RecordAudit::purchaseRequestQuery($purchaseRequest),
                $request->user(),
                fn ($activity) => RecordAudit::purchaseRequestSubjectLabel($purchaseRequest, $activity),
            ),
        ]);
    }

    public function edit(Request $request, PurchaseRequests $purchaseRequest): Response
    {
        $this->authorize('update', $purchaseRequest);
        abort_unless($this->visibleToUser($request, $purchaseRequest), 403);
        abort_unless($this->canEditHeader($request->user(), $purchaseRequest), 403);

        $purchaseRequest->load(['locations', 'purchaseRequestDetails']);

        return Inertia::render('PurchaseRequests/Form', [
            'record' => [
                'id' => $purchaseRequest->id,
                'pr_no' => $purchaseRequest->pr_no,
                'date' => $purchaseRequest->date,
                'purpose' => $purchaseRequest->purpose,
                'project_id' => $purchaseRequest->project_id,
                'locations' => $purchaseRequest->locations->pluck('id'),
                'supporting_document' => $purchaseRequest->supporting_document,
                'details' => $purchaseRequest->purchaseRequestDetails->map(fn ($detail) => [
                    'id' => $detail->id,
                    'item_id' => $detail->item_id,
                    'unit' => $detail->unit,
                    'budget_account_id' => $detail->budget_account_id,
                    'amount' => $detail->amount,
                    'est_cost' => $detail->est_cost,
                ])->values()->all(),
            ],
            'options' => $this->formOptions(
                $request,
                $purchaseRequest->purchaseRequestDetails->pluck('budget_account_id')->filter()->all(),
                $purchaseRequest->user?->department_id,
                $purchaseRequest->purchaseRequestDetails->pluck('id')->all(),
            ),
        ]);
    }

    public function update(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        $this->authorize('update', $purchaseRequest);
        abort_unless($this->canEditHeader($request->user(), $purchaseRequest), 403);

        $data = $this->validatedHeader($request, false);

        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('purchase-requests', 'public');
        }

        $purchaseRequest->update([
            'date' => $data['date'],
            'purpose' => $data['purpose'],
            'project_id' => $data['project_id'] ?? null,
            'supporting_document' => $data['supporting_document'] ?? $purchaseRequest->supporting_document,
        ]);
        $purchaseRequest->locations()->sync($data['locations']);

        return redirect()
            ->route('app.purchase-requests.show', $purchaseRequest)
            ->with('success', 'Purchase request updated.');
    }

    public function destroy(PurchaseRequests $purchaseRequest): RedirectResponse
    {
        $this->authorize('delete', $purchaseRequest);
        abort_unless($purchaseRequest->status === PurchaseRequestsStatus::Draft, 403);

        $purchaseRequest->delete();

        return redirect()
            ->route('app.purchase-requests.index')
            ->with('success', 'Purchase request deleted.');
    }

    public function submit(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        abort_unless($request->user()->can('send_approval_purchase::requests'), 403);

        try {
            SubmitPurchaseRequestForApproval::run($purchaseRequest);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Submitted for approval successfully');
    }

    public function hodApprove(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        ApprovePurchaseRequestByHod::run($purchaseRequest, $request->user()->id);

        return back()->with('success', 'PR Approved successfully');
    }

    public function hodReject(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        $data = $request->validate(['cancel_remark' => ['required', 'string', 'max:255']]);
        RejectPurchaseRequestByHod::run($purchaseRequest, $request->user()->id, $data['cancel_remark']);

        return back()->with('success', 'PR Rejected successfully');
    }

    public function financeApprove(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        abort_unless($request->user()->can('approve_purchase::requests'), 403);
        ApprovePurchaseRequestByFinance::run($purchaseRequest, $request->user()->id);

        return back()->with('success', 'PR Approved successfully');
    }

    public function financeReject(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        abort_unless($request->user()->can('approve_purchase::requests'), 403);
        $data = $request->validate(['cancel_remark' => ['required', 'string', 'max:255']]);
        RejectPurchaseRequestByFinance::run($purchaseRequest, $request->user()->id, $data['cancel_remark']);

        return back()->with('success', 'PR Rejected successfully');
    }

    public function cancel(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        abort_unless(
            $request->user()->can('approve_purchase::requests') || $request->user()->can('cancel_purchase::requests'),
            403
        );
        $data = $request->validate(['cancel_remark' => ['required', 'string', 'max:255']]);
        CancelPurchaseRequest::run($purchaseRequest, $request->user()->id, $data['cancel_remark']);

        return back()->with('success', 'PR Canceled successfully');
    }

    public function sendBack(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        $purchaseRequest->loadMissing('user.department.user');

        $user = $request->user();
        $isHod = (int) $user->id === (int) ($purchaseRequest->user?->department?->user?->id);
        $canFinanceSendBack = $purchaseRequest->status === PurchaseRequestsStatus::HODApproved
            && $user->can('approve_purchase::requests');
        $canHodSendBack = $purchaseRequest->status === PurchaseRequestsStatus::Submitted && $isHod;

        abort_unless($canFinanceSendBack || $canHodSendBack, 403);

        SendPurchaseRequestBackToDraft::run($purchaseRequest);

        return back()->with('success', 'PR sent back to draft');
    }

    public function mdDmdApprove(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        abort_unless($request->user()->can('md_dmd_approve_purchase::requests'), 403);
        ApprovePurchaseRequestByMdDmd::run($purchaseRequest, $request->user()->id);

        return back()->with('success', 'PR approved by MD / DMD successfully');
    }

    public function mdDmdReject(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        abort_unless($request->user()->can('md_dmd_approve_purchase::requests'), 403);
        $data = $request->validate(['cancel_remark' => ['required', 'string', 'max:255']]);
        RejectPurchaseRequestByMdDmd::run($purchaseRequest, $request->user()->id, $data['cancel_remark']);

        return back()->with('success', 'PR rejected by MD / DMD successfully');
    }

    public function close(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        abort_unless($request->user()->can('close_purchase::requests'), 403);

        $data = $request->validate([
            'purchase_order_grns' => ['nullable', 'array'],
            'purchase_order_grns.*.po_id' => ['required_with:purchase_order_grns', 'integer'],
            'purchase_order_grns.*.grn_number' => ['required_with:purchase_order_grns', 'string', 'max:255'],
        ]);

        ClosePurchaseRequest::run($purchaseRequest, $request->user()->id, $data['purchase_order_grns'] ?? []);

        return back()->with('success', 'PR Closed successfully');
    }

    public function storeDetail(Request $request, PurchaseRequests $purchaseRequest): RedirectResponse
    {
        abort_unless($purchaseRequest->status === PurchaseRequestsStatus::Draft, 403);
        abort_unless($request->user()->can('send_approval_purchase::requests'), 403);

        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'unit' => ['required', 'string'],
            'budget_account_id' => ['required', 'integer', 'exists:sub_budget_accounts,id'],
            'amount' => ['required', 'numeric'],
            'est_cost' => ['required', 'numeric'],
        ]);

        UpsertPurchaseRequestDetail::run($purchaseRequest, $data, null, $purchaseRequest->user?->department_id);

        return back()->with('success', 'Line added.');
    }

    public function updateDetail(Request $request, PurchaseRequests $purchaseRequest, PurchaseRequestDetails $detail): RedirectResponse
    {
        abort_unless((int) $detail->pr_id === (int) $purchaseRequest->id, 404);

        $user = $request->user();
        $canManageDraft = $purchaseRequest->status === PurchaseRequestsStatus::Draft
            && $user->can('send_approval_purchase::requests');
        $canEditBudget = $purchaseRequest->status === PurchaseRequestsStatus::HODApproved
            && $user->can('approve_purchase::requests');

        abort_unless($canManageDraft || $canEditBudget, 403);

        if ($canEditBudget && ! $canManageDraft) {
            $data = $request->validate([
                'budget_account_id' => ['required', 'integer', 'exists:sub_budget_accounts,id'],
            ]);

            UpsertPurchaseRequestDetail::run($purchaseRequest, [
                'item_id' => $detail->item_id,
                'unit' => $detail->unit,
                'budget_account_id' => $data['budget_account_id'],
                'amount' => $detail->amount,
                'est_cost' => $detail->est_cost,
            ], $detail, $purchaseRequest->user?->department_id);

            return back()->with('success', 'Budget updated.');
        }

        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'unit' => ['required', 'string'],
            'budget_account_id' => ['required', 'integer', 'exists:sub_budget_accounts,id'],
            'amount' => ['required', 'numeric'],
            'est_cost' => ['required', 'numeric'],
        ]);

        UpsertPurchaseRequestDetail::run($purchaseRequest, $data, $detail, $purchaseRequest->user?->department_id);

        return back()->with('success', 'Line updated.');
    }

    public function destroyDetail(Request $request, PurchaseRequests $purchaseRequest, PurchaseRequestDetails $detail): RedirectResponse
    {
        abort_unless($purchaseRequest->status === PurchaseRequestsStatus::Draft, 403);
        abort_unless((int) $detail->pr_id === (int) $purchaseRequest->id, 404);
        abort_unless($request->user()->can('send_approval_purchase::requests'), 403);

        $detail->delete();

        return back()->with('success', 'Line deleted.');
    }

    private function validatedHeader(Request $request, bool $creating): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'locations' => ['required', 'array', 'min:1'],
            'locations.*' => ['integer', 'exists:locations,id'],
            'supporting_document' => [$creating ? 'nullable' : 'nullable', 'file', 'max:10240'],
        ]);
    }

    /**
     * @param  list<int|string>|null  $alwaysIncludeBudgetIds
     * @param  list<int|string>|null  $excludeDetailIds
     */
    private function formOptions(
        Request $request,
        ?array $alwaysIncludeBudgetIds = null,
        ?int $departmentId = null,
        ?array $excludeDetailIds = null,
    ): array {
        $departmentId ??= $request->user()->department_id;
        $alwaysInclude = collect($alwaysIncludeBudgetIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $budgets = SubBudgetAccounts::query()
            ->when($departmentId, fn ($q) => $q->whereHas('allocations', fn ($a) => $a->where('department_id', $departmentId)))
            ->with(['allocations' => fn ($q) => $q->when($departmentId, fn ($a) => $a->where('department_id', $departmentId))])
            ->orderBy('code')
            ->get();

        $holds = $departmentId
            ? PurchaseRequestBudget::onHoldBySubBudgetIds($budgets->pluck('id'), $departmentId, $excludeDetailIds)
            : [];

        $budgets = $budgets
            ->map(function (SubBudgetAccounts $budget) use ($holds, $departmentId) {
                $allocated = (float) $budget->allocations->sum('amount');
                $onHold = (float) ($holds[$budget->id] ?? 0);
                $available = $departmentId ? max(0, $allocated - $onHold) : $allocated;

                return [
                    'id' => $budget->id,
                    'label' => $budget->getSelectLabel(),
                    'allocated' => $allocated,
                    'on_hold' => $onHold,
                    'available' => $available,
                ];
            })
            ->filter(fn (array $budget) => $budget['available'] > 0.00001 || $alwaysInclude->contains($budget['id']))
            ->values();

        return [
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'items' => Item::query()->orderBy('item_code')->get(['id', 'item_code', 'name']),
            'budgets' => $budgets,
            'units' => collect(UnitsEnum::cases())->map(fn (UnitsEnum $u) => [
                'value' => $u->value,
                'label' => $u->getLabel(),
            ]),
        ];
    }

    private function listRow(PurchaseRequests $pr, User $user): array
    {
        return [
            'id' => $pr->id,
            'pr_no' => $pr->pr_no,
            'date' => $pr->date,
            'purpose' => $pr->purpose,
            'user' => $pr->user?->name,
            'department' => $pr->user?->department?->name,
            'project' => $pr->project?->name,
            'status' => $pr->status?->value,
            'status_label' => $pr->status?->getLabel(),
            'total_est_cost' => (float) ($pr->total_est_cost ?? 0),
            'actions' => $this->workflowActions($pr, $user),
        ];
    }

    /**
     * @return array{
     *     submit: bool,
     *     hodApprove: bool,
     *     hodReject: bool,
     *     financeApprove: bool,
     *     financeReject: bool,
     *     cancel: bool,
     *     sendBack: bool,
     *     mdDmdApprove: bool,
     *     mdDmdReject: bool,
     *     close: bool,
     *     edit: bool,
     *     delete: bool
     * }
     */
    private function workflowActions(PurchaseRequests $pr, User $user): array
    {
        $status = $pr->status;
        $hodId = $pr->user?->department?->user?->id;
        $isHod = (int) $hodId === (int) $user->id;

        return [
            'submit' => $status === PurchaseRequestsStatus::Draft && $user->can('send_approval_purchase::requests'),
            'hodApprove' => $status === PurchaseRequestsStatus::Submitted && $isHod,
            'hodReject' => $status === PurchaseRequestsStatus::Submitted && $isHod,
            'financeApprove' => $status === PurchaseRequestsStatus::HODApproved && $user->can('approve_purchase::requests'),
            'financeReject' => $status === PurchaseRequestsStatus::HODApproved && $user->can('approve_purchase::requests'),
            'cancel' => $status === PurchaseRequestsStatus::HODApproved && $user->can('approve_purchase::requests'),
            'sendBack' => (
                ($status === PurchaseRequestsStatus::HODApproved && $user->can('approve_purchase::requests'))
                || ($status === PurchaseRequestsStatus::Submitted && $isHod)
            ),
            'mdDmdApprove' => $status === PurchaseRequestsStatus::Approved && $user->can('md_dmd_approve_purchase::requests'),
            'mdDmdReject' => $status === PurchaseRequestsStatus::Approved && $user->can('md_dmd_approve_purchase::requests'),
            'close' => $status === PurchaseRequestsStatus::MD_DMD_Approved && $user->can('close_purchase::requests'),
            'editHeader' => $status === PurchaseRequestsStatus::Draft && $user->can('send_approval_purchase::requests'),
            'delete' => $status === PurchaseRequestsStatus::Draft && $user->can('delete', $pr),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     budget_account_id: int|null,
     *     can_open: bool,
     *     allocated: float,
     *     on_hold: float,
     *     this_pr: float,
     *     available: float,
     *     usage_percent: int
     * }>
     */
    private function budgetSummary(PurchaseRequests $pr, User $user): array
    {
        $departmentId = $pr->user?->department_id;
        $budgetIds = $pr->purchaseRequestDetails
            ->pluck('budget_account_id')
            ->filter()
            ->unique()
            ->values();

        if ($budgetIds->isEmpty()) {
            return [];
        }

        $accounts = SubBudgetAccounts::query()
            ->whereIn('id', $budgetIds)
            ->with([
                'budgetAccount',
                'allocations' => fn ($query) => $query->when(
                    $departmentId,
                    fn ($allocationQuery) => $allocationQuery->where('department_id', $departmentId),
                ),
            ])
            ->get()
            ->keyBy('id');

        $holdsByBudget = PurchaseRequestBudget::onHoldBySubBudgetIds($budgetIds->all(), $departmentId);

        return $budgetIds->map(function ($budgetId) use ($pr, $accounts, $holdsByBudget, $user) {
            $account = $accounts->get($budgetId);
            $parent = $account?->budgetAccount;
            $allocated = (float) ($account?->allocations->sum('amount') ?? 0);
            $onHold = (float) ($holdsByBudget[$budgetId] ?? 0);
            $thisPr = (float) $pr->purchaseRequestDetails
                ->where('budget_account_id', $budgetId)
                ->sum('est_cost');

            // Draft (and other non-holding) PRs are not in on_hold yet — still count them for this screen.
            $statusValue = $pr->status?->value;
            $thisPrAlreadyHeld = $statusValue
                && in_array($statusValue, PurchaseRequestBudget::holdingStatuses(), true);
            $available = max(0, $allocated - $onHold - ($thisPrAlreadyHeld ? 0 : $thisPr));
            $committed = $onHold + ($thisPrAlreadyHeld ? 0 : $thisPr);

            return [
                'id' => (int) $budgetId,
                'label' => $account?->getSelectLabel() ?? 'Budget #'.$budgetId,
                'budget_account_id' => $parent?->id,
                'can_open' => $parent
                    ? ($user->can('view', $parent) || $user->can('update', $parent))
                    : false,
                'allocated' => $allocated,
                'on_hold' => $onHold,
                'this_pr' => $thisPr,
                'available' => $available,
                'usage_percent' => $allocated > 0
                    ? (int) min(100, round(($committed / $allocated) * 100))
                    : 0,
            ];
        })->values()->all();
    }

    private function showPayload(PurchaseRequests $pr): array
    {
        return [
            'id' => $pr->id,
            'pr_no' => $pr->pr_no,
            'date' => $pr->date,
            'purpose' => $pr->purpose,
            'status' => $pr->status?->value,
            'status_label' => $pr->status?->getLabel(),
            'cancel_remark' => $pr->cancel_remark,
            'user' => $pr->user?->name,
            'department' => $pr->user?->department?->name,
            'project_id' => $pr->project_id,
            'project' => $pr->project?->name,
            'location_ids' => $pr->locations->pluck('id')->values()->all(),
            'locations' => $pr->locations->pluck('name'),
            'supporting_document' => $pr->supporting_document,
            'hod' => $pr->hodapprovedby?->name,
            'finance' => $pr->approvedby?->name,
            'md_dmd' => $pr->mdDmdApprovedBy?->name,
            'details' => $pr->purchaseRequestDetails->map(fn (PurchaseRequestDetails $d) => [
                'id' => $d->id,
                'item_id' => $d->item_id,
                'item' => $d->items?->name,
                'item_code' => $d->items?->item_code,
                'unit' => $d->unit instanceof UnitsEnum ? $d->unit->value : $d->unit,
                'budget_account_id' => $d->budget_account_id,
                'budget' => $d->budgetAccount?->getSelectLabel(),
                'amount' => $d->amount,
                'est_cost' => $d->est_cost,
                'is_utilized' => (bool) $d->is_utilized,
            ]),
        ];
    }

    private function statusOptions(): array
    {
        return collect(PurchaseRequestsStatus::cases())
            ->reject(fn (PurchaseRequestsStatus $s) => $s === PurchaseRequestsStatus::DocumentUploaded)
            ->map(fn (PurchaseRequestsStatus $s) => [
                'value' => $s->value,
                'label' => $s->getLabel(),
            ])
            ->values()
            ->all();
    }

    private function visibleToUser(Request $request, PurchaseRequests $purchaseRequest): bool
    {
        return PurchaseRequests::query()
            ->visibleTo($request->user())
            ->whereKey($purchaseRequest->id)
            ->exists();
    }

    private function canEditHeader(User $user, PurchaseRequests $pr): bool
    {
        return $pr->status === PurchaseRequestsStatus::Draft
            && $user->can('send_approval_purchase::requests');
    }
}
