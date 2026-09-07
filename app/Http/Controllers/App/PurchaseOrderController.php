<?php

namespace App\Http\Controllers\App;

use App\Actions\AdvanceForms\ApproveAdvanceFormByHod;
use App\Actions\AdvanceForms\ApproveAdvanceFormByMdDmd;
use App\Actions\AdvanceForms\GenerateAdvanceForm;
use App\Actions\AdvanceForms\RegenerateAdvanceForm;
use App\Actions\AdvanceForms\RejectAdvanceFormByHod;
use App\Actions\AdvanceForms\RejectAdvanceFormByMdDmd;
use App\Actions\AdvanceForms\SubmitAdvanceForm;
use App\Actions\PurchaseOrders\ClosePurchaseOrder;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\DeletePurchaseOrder;
use App\Actions\PurchaseOrders\SubmitPurchaseOrder;
use App\Actions\PurchaseOrders\UpdatePurchaseOrder;
use App\Actions\PurchaseOrders\UploadSupportingDocument;
use App\Enums\AdvanceFormStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestsStatus;
use App\Enums\UnitsEnum;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PurchaseOrderDetails;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Models\User;
use App\Models\Vendors;
use App\Support\PinnedTabs;
use App\Support\RecordAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrders::class);

        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $paymentMethod = $request->string('payment_method')->toString();
        $vendorId = $request->integer('vendor_id') ?: null;
        $prId = $request->integer('pr_id') ?: null;
        $dateFrom = $request->string('date_from')->trim()->toString() ?: null;
        $dateTo = $request->string('date_to')->trim()->toString() ?: null;
        $sort = $request->string('sort')->trim()->toString() ?: 'newest';

        $validStatuses = collect(PurchaseOrderStatus::cases())->map->value->all();
        $allowedTabs = ['all', ...$validStatuses];
        $pinnedTab = $user->pinnedTab(PinnedTabs::PURCHASE_ORDERS);
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
            'po_no_asc' => ['po_no', 'asc'],
            'po_no_desc' => ['po_no', 'desc'],
            'amount_desc' => ['id', 'desc'],
        ];

        if (! array_key_exists($sort, $sortMap)) {
            $sort = 'newest';
        }

        [$sortColumn, $sortDirection] = $sortMap[$sort];

        $baseQuery = fn () => PurchaseOrders::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('po_no', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseRequest', function ($q) use ($search): void {
                            $q->where(function ($q) use ($search): void {
                                $q->where('pr_no', 'like', "%{$search}%")
                                    ->orWhere('purpose', 'like', "%{$search}%");
                            });
                        });
                });
            })
            ->when($paymentMethod !== '', fn ($query) => $query->where('payment_method', $paymentMethod))
            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId))
            ->when($prId, fn ($query) => $query->where('pr_id', $prId))
            ->when($dateFrom, fn ($query) => $query->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('date', '<=', $dateTo));

        $tabs = [
            [
                'key' => 'all',
                'label' => 'All',
                'badge' => $baseQuery()->count(),
                'tone' => 'neutral',
            ],
            ...collect(PurchaseOrderStatus::cases())->map(fn (PurchaseOrderStatus $status): array => [
                'key' => $status->value,
                'label' => $status->getLabel(),
                'badge' => $baseQuery()->where('status', $status->value)->count(),
                'tone' => match ($status) {
                    PurchaseOrderStatus::Draft => 'neutral',
                    PurchaseOrderStatus::Submitted, PurchaseOrderStatus::WaitingReimbursement, PurchaseOrderStatus::GRNCreated => 'warn',
                    PurchaseOrderStatus::Closed, PurchaseOrderStatus::Reimbursed => 'success',
                    default => 'neutral',
                },
            ])->all(),
        ];

        $orders = $baseQuery()
            ->with(['vendor', 'purchaseRequest', 'advanceForm.user.department.user', 'purchaseOrderDetails'])
            ->when($tab !== 'all', fn ($query) => $query->where('status', $tab))
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PurchaseOrders $order): array => $this->orderSummary($order, $user));

        return Inertia::render('Procure/Index', [
            'records' => $orders,
            'filters' => [
                'search' => $search,
                'tab' => $tab,
                'payment_method' => $paymentMethod,
                'vendor_id' => $vendorId,
                'pr_id' => $prId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
            ],
            'filterOptions' => [
                'vendors' => Vendors::query()->orderBy('name')->get(['id', 'name']),
                'purchaseRequests' => PurchaseRequests::query()
                    ->orderByDesc('id')
                    ->limit(200)
                    ->get(['id', 'pr_no']),
                'sorts' => [
                    ['value' => 'newest', 'label' => 'Newest first'],
                    ['value' => 'oldest', 'label' => 'Oldest first'],
                    ['value' => 'date_desc', 'label' => 'Date (newest)'],
                    ['value' => 'date_asc', 'label' => 'Date (oldest)'],
                    ['value' => 'po_no_asc', 'label' => 'PO no (A–Z)'],
                    ['value' => 'po_no_desc', 'label' => 'PO no (Z–A)'],
                ],
            ],
            'tabs' => $tabs,
            'pinnedTab' => $pinnedTab,
            'can' => [
                'create' => $user->can('create', PurchaseOrders::class),
                'audit' => $user->can('audit', PurchaseOrders::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseOrders::class);

        return Inertia::render('Procure/Form', [
            'order' => null,
            'vendors' => Vendors::query()->orderBy('name')->get(['id', 'name']),
            'purchaseRequests' => $this->approvedPurchaseRequests(),
            'units' => collect(UnitsEnum::cases())->map(fn (UnitsEnum $u) => [
                'value' => $u->value,
                'label' => $u->getLabel(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrders::class);

        $data = $this->validatedHeader($request, requireDetails: true);
        $order = CreatePurchaseOrder::run($data);

        return redirect()
            ->route('app.purchase-orders.show', $order)
            ->with('success', 'Purchase order created.');
    }

    public function show(Request $request, PurchaseOrders $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'vendor',
            'purchaseRequest',
            'purchaseOrderDetails.budgetAccount',
            'purchaseOrderDetails.items',
            'advanceForm.user.department.user',
            'advanceForm.hodApprovedBy',
            'advanceForm.mdDmdApprovedBy',
        ]);

        $user = $request->user();
        $advanceActions = $this->advanceFormActionFlags($purchaseOrder, $user);

        return Inertia::render('Procure/Show', [
            'order' => $this->orderDetail($purchaseOrder),
            'availablePrDetails' => $purchaseOrder->status === PurchaseOrderStatus::Draft
                ? $this->availablePrDetailsFor($purchaseOrder->pr_id, $purchaseOrder->id)
                : [],
            'units' => collect(UnitsEnum::cases())->map(fn (UnitsEnum $u) => [
                'value' => $u->value,
                'label' => $u->getLabel(),
            ])->values(),
            'can' => [
                'update' => $user->can('update', $purchaseOrder),
                'delete' => $user->can('delete', $purchaseOrder) && $purchaseOrder->status === PurchaseOrderStatus::Draft,
                'submit' => $purchaseOrder->status === PurchaseOrderStatus::Draft && $user->can('create', PurchaseOrders::class),
                'close' => $purchaseOrder->status === PurchaseOrderStatus::Submitted
                    && $user->can('close', PurchaseOrders::class)
                    && (
                        $purchaseOrder->payment_method === 'purchase_order'
                        || ($purchaseOrder->payment_method === 'petty_cash' && $purchaseOrder->supporting_document)
                    ),
                'upload_receipt' => $purchaseOrder->payment_method === 'petty_cash'
                    && ! $purchaseOrder->supporting_document
                    && $user->can('create', PurchaseOrders::class)
                    && $purchaseOrder->status !== PurchaseOrderStatus::Closed,
                ...$advanceActions,
                'manage_details' => $purchaseOrder->status === PurchaseOrderStatus::Draft
                    && $user->can('update', $purchaseOrder),
                'audit' => $user->can('audit', PurchaseOrders::class),
            ],
        ]);
    }

    public function audit(Request $request, PurchaseOrders $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);
        $this->authorize('audit', PurchaseOrders::class);

        return Inertia::render('Audit/Show', [
            'title' => $purchaseOrder->displayNumber(),
            'description' => 'Changes to this purchase order, line items, and advance form.',
            'backUrl' => route('app.purchase-orders.show', $purchaseOrder),
            'backLabel' => 'Back to PO',
            'activities' => RecordAudit::paginate(
                RecordAudit::purchaseOrderQuery($purchaseOrder),
                $request->user(),
                fn ($activity) => RecordAudit::purchaseOrderSubjectLabel($purchaseOrder, $activity),
            ),
        ]);
    }

    public function edit(Request $request, PurchaseOrders $purchaseOrder): Response
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder->load(['purchaseOrderDetails']);

        return Inertia::render('Procure/Form', [
            'order' => [
                'id' => $purchaseOrder->id,
                'vendor_id' => $purchaseOrder->vendor_id,
                'po_no' => $purchaseOrder->po_no,
                'date' => $purchaseOrder->date,
                'pr_id' => $purchaseOrder->pr_id,
                'payment_method' => $purchaseOrder->payment_method,
                'is_advance_form_required' => (bool) $purchaseOrder->is_advance_form_required,
                'details' => $purchaseOrder->purchaseOrderDetails->map(fn (PurchaseOrderDetails $d) => [
                    'id' => $d->id,
                    'itemcode' => $d->itemcode,
                    'desc' => $d->desc,
                    'unit_measure' => $d->unit_measure,
                    'qty' => $d->qty,
                    'unit_price' => $d->unit_price,
                    'tax_amount' => $d->tax_amount,
                    'amount' => $d->amount,
                    'budget_account_id' => $d->budget_account_id,
                ])->values(),
            ],
            'vendors' => Vendors::query()->orderBy('name')->get(['id', 'name']),
            'purchaseRequests' => $this->approvedPurchaseRequests($purchaseOrder->pr_id),
            'units' => collect(UnitsEnum::cases())->map(fn (UnitsEnum $u) => [
                'value' => $u->value,
                'label' => $u->getLabel(),
            ])->values(),
        ]);
    }

    public function update(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $data = $this->validatedHeader($request, requireDetails: false);
        UpdatePurchaseOrder::run($purchaseOrder, $data);

        return redirect()
            ->route('app.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);

        DeletePurchaseOrder::run($purchaseOrder);

        return redirect()
            ->route('app.purchase-orders.index')
            ->with('success', 'Purchase order deleted.');
    }

    public function submit(PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('create', PurchaseOrders::class);

        SubmitPurchaseOrder::run($purchaseOrder);

        return back()->with('success', 'PO Submitted successfully');
    }

    public function close(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('close', PurchaseOrders::class);

        $data = $request->validate([
            'grn_number' => [
                Rule::requiredIf($purchaseOrder->payment_method === 'purchase_order'),
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        ClosePurchaseOrder::run($purchaseOrder, $data, $request->user()->id);

        return back()->with('success', 'PO Closed successfully');
    }

    public function uploadReceipt(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('create', PurchaseOrders::class);

        $data = $request->validate([
            'supporting_document' => ['required', 'file', 'max:10240'],
        ]);

        UploadSupportingDocument::run($purchaseOrder, $data['supporting_document']);

        return back()->with('success', 'Document uploaded successfully');
    }

    public function generateAdvanceForm(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('generate_advance_form', PurchaseOrders::class);

        $data = $this->validatedAdvanceForm($request);
        GenerateAdvanceForm::run($purchaseOrder, $data, $request->user()->id);

        return back()->with('success', 'Advance form generated successfully');
    }

    public function regenerateAdvanceForm(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('generate_advance_form', PurchaseOrders::class);

        $data = $this->validatedAdvanceForm($request);
        RegenerateAdvanceForm::run($purchaseOrder, $data, $request->user()->id);

        return back()->with('success', 'Advance form regenerated successfully');
    }

    public function submitAdvanceForm(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('create', PurchaseOrders::class);

        SubmitAdvanceForm::run($purchaseOrder, $request->user()->id);

        return back()->with('success', 'Advance form submitted successfully');
    }

    public function hodApproveAdvanceForm(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        ApproveAdvanceFormByHod::run($purchaseOrder, $request->user());

        return back()->with('success', 'Advance form HOD approved successfully');
    }

    public function hodRejectAdvanceForm(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        RejectAdvanceFormByHod::run($purchaseOrder, $request->user());

        return back()->with('success', 'Advance form HOD rejected successfully');
    }

    public function mdDmdApproveAdvanceForm(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('md_dmd_approve_advance_form', PurchaseOrders::class);

        ApproveAdvanceFormByMdDmd::run($purchaseOrder, $request->user());

        return back()->with('success', 'Advance form MD / DMD approved successfully');
    }

    public function mdDmdRejectAdvanceForm(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('md_dmd_approve_advance_form', PurchaseOrders::class);

        RejectAdvanceFormByMdDmd::run($purchaseOrder, $request->user());

        return back()->with('success', 'Advance form MD / DMD rejected successfully');
    }

    public function storeDetail(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $this->assertDraft($purchaseOrder);

        $data = $this->validatedDetail($request);
        $item = Item::query()->where('item_code', $data['itemcode'])->firstOrFail();

        $pr = PurchaseRequests::query()->findOrFail($purchaseOrder->pr_id);
        $pr->purchaseRequestDetails()->where('item_id', $item->id)->update(['is_utilized' => true]);

        $purchaseOrder->purchaseOrderDetails()->create([
            'item_id' => $item->id,
            'itemcode' => $data['itemcode'],
            'desc' => $item->name,
            'budget_account_id' => $data['budget_account_id'],
            'unit_measure' => $data['unit_measure'],
            'qty' => $data['qty'],
            'unit_price' => $data['unit_price'],
            'tax_amount' => $data['tax_amount'],
            'amount' => $data['amount'],
        ]);

        return back()->with('success', 'Line added.');
    }

    public function updateDetail(Request $request, PurchaseOrders $purchaseOrder, PurchaseOrderDetails $detail): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $this->assertDraft($purchaseOrder);
        $this->ensureDetailBelongs($purchaseOrder, $detail);

        $data = $this->validatedDetail($request, forUpdate: true);

        $detail->update([
            'unit_price' => $data['unit_price'],
            'tax_amount' => $data['tax_amount'],
            'amount' => $data['amount'],
        ]);

        return back()->with('success', 'Line updated.');
    }

    public function destroyDetail(PurchaseOrders $purchaseOrder, PurchaseOrderDetails $detail): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $this->assertDraft($purchaseOrder);
        $this->ensureDetailBelongs($purchaseOrder, $detail);

        $item = Item::query()->where('item_code', $detail->itemcode)->first();
        if ($item) {
            $pr = PurchaseRequests::query()->find($purchaseOrder->pr_id);
            $pr?->purchaseRequestDetails()->where('item_id', $item->id)->update(['is_utilized' => false]);
        }

        $detail->delete();

        return back()->with('success', 'Line removed.');
    }

    private function validatedHeader(Request $request, bool $requireDetails): array
    {
        $rules = [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'date' => ['required', 'date'],
            'pr_id' => ['required', 'integer', 'exists:purchase_requests,id'],
            'payment_method' => ['required', Rule::in(['purchase_order', 'petty_cash'])],
            'is_advance_form_required' => ['nullable', 'boolean'],
            'po_no' => [
                Rule::requiredIf(fn () => $request->input('payment_method') === 'purchase_order'),
                'nullable',
                'string',
                'max:255',
            ],
        ];

        if ($requireDetails) {
            $rules['details'] = ['required', 'array', 'min:1'];
            $rules['details.*.itemcode'] = ['required', 'string', 'exists:items,item_code'];
            $rules['details.*.unit_measure'] = ['required', 'string', 'max:255'];
            $rules['details.*.qty'] = ['required', 'numeric'];
            $rules['details.*.unit_price'] = ['required', 'numeric'];
            $rules['details.*.tax_amount'] = ['required', 'numeric'];
            $rules['details.*.amount'] = ['required', 'numeric'];
            $rules['details.*.budget_account_id'] = ['required', 'integer', 'exists:sub_budget_accounts,id'];
        }

        return $request->validate($rules);
    }

    private function validatedDetail(Request $request, bool $forUpdate = false): array
    {
        if ($forUpdate) {
            return $request->validate([
                'unit_price' => ['required', 'numeric'],
                'tax_amount' => ['required', 'numeric'],
                'amount' => ['required', 'numeric'],
            ]);
        }

        return $request->validate([
            'itemcode' => ['required', 'string', 'exists:items,item_code'],
            'unit_measure' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric'],
            'unit_price' => ['required', 'numeric'],
            'tax_amount' => ['required', 'numeric'],
            'amount' => ['required', 'numeric'],
            'budget_account_id' => ['required', 'integer', 'exists:sub_budget_accounts,id'],
        ]);
    }

    private function validatedAdvanceForm(Request $request): array
    {
        return $request->validate([
            'qoation_no' => ['required', 'string', 'max:255'],
            'expected_delivery' => ['required'],
            'advance_amount' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function assertDraft(PurchaseOrders $purchaseOrder): void
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase orders can manage line items.',
            ]);
        }
    }

    private function ensureDetailBelongs(PurchaseOrders $purchaseOrder, PurchaseOrderDetails $detail): void
    {
        abort_unless((int) $detail->po_id === (int) $purchaseOrder->id, 404);
    }

    /**
     * @return array{
     *     generate_advance_form: bool,
     *     regenerate_advance_form: bool,
     *     submit_advance_form: bool,
     *     hod_approve_advance_form: bool,
     *     md_dmd_approve_advance_form: bool,
     *     view_advance_form: bool
     * }
     */
    private function advanceFormActionFlags(PurchaseOrders $order, User $user): array
    {
        $advanceForm = $order->advanceForm;
        $isHodForGenerator = $advanceForm
            && $advanceForm->user?->department?->user
            && (int) $advanceForm->user->department->user->id === (int) $user->id;
        $canGenerate = $user->can('generate_advance_form', PurchaseOrders::class);
        $eligible = (bool) $order->is_advance_form_required
            && $order->status === PurchaseOrderStatus::Submitted
            && $order->payment_method === 'purchase_order';

        return [
            'generate_advance_form' => ! $order->advance_form_id && $eligible && $canGenerate,
            'regenerate_advance_form' => (bool) $order->advance_form_id
                && $eligible
                && $canGenerate
                && $advanceForm?->status === AdvanceFormStatus::Draft,
            'submit_advance_form' => (bool) $order->advance_form_id
                && $eligible
                && $user->can('create', PurchaseOrders::class)
                && $advanceForm?->status === AdvanceFormStatus::Draft
                && (int) $advanceForm?->generated_by === (int) $user->id,
            'hod_approve_advance_form' => (bool) $order->advance_form_id
                && (bool) $order->is_advance_form_required
                && $order->payment_method === 'purchase_order'
                && $advanceForm?->status === AdvanceFormStatus::Submitted
                && $isHodForGenerator,
            'md_dmd_approve_advance_form' => $advanceForm?->status === AdvanceFormStatus::HOD_Approved
                && $user->can('md_dmd_approve_advance_form', PurchaseOrders::class),
            'view_advance_form' => ! empty($order->advance_form_id),
        ];
    }

    private function orderSummary(PurchaseOrders $order, ?User $user = null): array
    {
        $user ??= auth()->user();

        return [
            'id' => $order->id,
            'po_no' => $order->displayNumber(),
            'date' => $order->date,
            'status' => $order->status?->value,
            'status_label' => $order->status?->getLabel(),
            'payment_method' => $order->payment_method,
            'payment_method_label' => $order->payment_method === 'purchase_order' ? 'Purchase Order' : 'Petty Cash',
            'vendor' => $order->vendor?->name,
            'pr_id' => $order->purchaseRequest?->id,
            'pr_no' => $order->purchaseRequest?->pr_no,
            'purpose' => $order->purchaseRequest?->purpose,
            'total_amount' => (float) $order->purchaseOrderDetails->sum('amount'),
            'grn_number' => $order->grn_number,
            'is_advance_form_required' => (bool) $order->is_advance_form_required,
            'advance_form_status' => $order->advanceForm?->status?->value,
            'advance_form_status_label' => $order->advanceForm?->status?->getLabel(),
            'advance_form_pdf_url' => $order->advance_form_id
                ? route('purchase-orders.advance-form.download', $order)
                : null,
            'advance_form' => $order->advanceForm ? [
                'qoation_no' => $order->advanceForm->qoation_no,
                'expected_delivery' => $order->advanceForm->expected_delivery,
                'advance_percentage' => $order->advanceForm->advance_percentage,
            ] : null,
            'actions' => array_merge([
                'edit' => $user && $user->can('update', $order),
                'submit' => $user
                    && $order->status === PurchaseOrderStatus::Draft
                    && $user->can('create', PurchaseOrders::class),
                'delete' => $user
                    && $order->status === PurchaseOrderStatus::Draft
                    && $user->can('delete', $order),
                'close' => $user
                    && $order->status === PurchaseOrderStatus::Submitted
                    && $user->can('close', PurchaseOrders::class),
            ], $user ? $this->advanceFormActionFlags($order, $user) : []),
        ];
    }

    private function orderDetail(PurchaseOrders $order): array
    {
        return [
            'id' => $order->id,
            'po_no' => $order->displayNumber(),
            'date' => $order->date,
            'grn_number' => $order->grn_number,
            'status' => $order->status?->value,
            'status_label' => $order->status?->getLabel(),
            'payment_method' => $order->payment_method,
            'payment_method_label' => $order->payment_method === 'purchase_order' ? 'Purchase Order' : 'Petty Cash',
            'is_advance_form_required' => (bool) $order->is_advance_form_required,
            'supporting_document' => $order->supporting_document,
            'supporting_document_url' => $order->supporting_document
                ? asset('storage/'.$order->supporting_document)
                : null,
            'vendor' => $order->vendor?->only(['id', 'name']),
            'purchase_request' => $order->purchaseRequest ? [
                'id' => $order->purchaseRequest->id,
                'pr_no' => $order->purchaseRequest->pr_no,
                'purpose' => $order->purchaseRequest->purpose,
            ] : null,
            'total_amount' => (float) $order->purchaseOrderDetails->sum('amount'),
            'tax_total' => (float) $order->purchaseOrderDetails->sum('tax_amount'),
            'advance_form_pdf_url' => $order->advance_form_id
                ? route('purchase-orders.advance-form.download', $order)
                : null,
            'advance_form' => $order->advanceForm ? [
                'id' => $order->advanceForm->id,
                'request_number' => $order->advanceForm->request_number,
                'qoation_no' => $order->advanceForm->qoation_no,
                'expected_delivery' => $order->advanceForm->expected_delivery,
                'advance_percentage' => $order->advanceForm->advance_percentage,
                'advance_amount' => $order->advanceForm->advance_amount,
                'balance_amount' => $order->advanceForm->balance_amount,
                'status' => $order->advanceForm->status?->value,
                'status_label' => $order->advanceForm->status?->getLabel(),
                'generated_by' => $order->advanceForm->user?->name,
                'hod_approved_by' => $order->advanceForm->hodApprovedBy?->name,
                'md_dmd_approved_by' => $order->advanceForm->mdDmdApprovedBy?->name,
            ] : null,
            'details' => $order->purchaseOrderDetails->map(fn (PurchaseOrderDetails $d) => [
                'id' => $d->id,
                'itemcode' => $d->itemcode,
                'desc' => $d->desc,
                'unit_measure' => $d->unit_measure,
                'qty' => $d->qty,
                'unit_price' => $d->unit_price,
                'tax_amount' => $d->tax_amount,
                'amount' => $d->amount,
                'budget_account_id' => $d->budget_account_id,
                'budget_code' => $d->budgetAccount?->code,
            ])->values(),
        ];
    }

    private function approvedPurchaseRequests(?int $includePrId = null): array
    {
        return PurchaseRequests::query()
            ->with(['purchaseRequestDetails.items', 'purchaseRequestDetails.budgetAccount'])
            ->where(function ($query) use ($includePrId): void {
                $query->where('status', PurchaseRequestsStatus::MD_DMD_Approved);
                if ($includePrId) {
                    $query->orWhere('id', $includePrId);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseRequests $pr) => [
                'id' => $pr->id,
                'pr_no' => $pr->pr_no,
                'purpose' => $pr->purpose,
                'date' => $pr->date,
                'details' => $pr->purchaseRequestDetails
                    ->filter(fn (PurchaseRequestDetails $d) => ! $d->is_utilized || ($includePrId && (int) $pr->id === (int) $includePrId))
                    ->map(fn (PurchaseRequestDetails $d) => [
                        'id' => $d->id,
                        'item_id' => $d->item_id,
                        'itemcode' => $d->items?->item_code,
                        'name' => $d->items?->name,
                        'unit' => $d->unit instanceof UnitsEnum ? $d->unit->value : $d->unit,
                        'qty' => $d->amount,
                        'est_cost' => $d->est_cost,
                        'budget_account_id' => $d->budget_account_id,
                        'budget_code' => $d->budgetAccount?->code,
                        'budget_label' => $d->budgetAccount?->getSelectLabel(),
                        'is_utilized' => (bool) $d->is_utilized,
                    ])
                    ->filter(fn (array $d) => $d['itemcode'])
                    ->values(),
            ])
            ->values()
            ->all();
    }

    private function availablePrDetailsFor(int $prId, ?int $poId = null): array
    {
        return PurchaseRequestDetails::query()
            ->with(['items', 'budgetAccount'])
            ->where('pr_id', $prId)
            ->where('is_utilized', false)
            ->get()
            ->map(fn (PurchaseRequestDetails $d) => [
                'id' => $d->id,
                'item_id' => $d->item_id,
                'itemcode' => $d->items?->item_code,
                'name' => $d->items?->name,
                'unit' => $d->unit instanceof UnitsEnum ? $d->unit->value : $d->unit,
                'qty' => $d->amount,
                'budget_account_id' => $d->budget_account_id,
                'budget_code' => $d->budgetAccount?->code,
            ])
            ->filter(fn (array $d) => $d['itemcode'])
            ->values()
            ->all();
    }
}
