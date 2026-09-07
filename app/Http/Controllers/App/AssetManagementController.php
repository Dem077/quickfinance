<?php

namespace App\Http\Controllers\App;

use App\Actions\AssetManagement\BulkReceiveAssetReceipts;
use App\Actions\AssetManagement\ReceiveAssetReceipt;
use App\Enums\AssetReceiptStatus;
use App\Enums\ItemTypeEnum;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AssetReceipt;
use App\Models\PurchaseOrders;
use App\Models\Vendors;
use App\Support\PinnedTabs;
use App\Services\SnipeIt\SnipeItException;
use App\Services\SnipeIt\SnipeItService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssetManagementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view_any_asset::management'), 403);

        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $vendorId = $request->integer('vendor_id') ?: null;
        $dateFrom = $request->string('date_from')->trim()->toString() ?: null;
        $dateTo = $request->string('date_to')->trim()->toString() ?: null;

        $allowedTabs = ['all', 'pending', 'completed'];
        $pinnedTab = $user->pinnedTab(PinnedTabs::ASSET_MANAGEMENT);
        $tab = PinnedTabs::resolve(
            $request->string('tab')->trim()->toString() ?: null,
            $pinnedTab,
            $allowedTabs,
        );

        $baseQuery = fn () => $this->scopedQuery()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('po_no', 'like', "%{$search}%")
                        ->orWhereHas('purchaseRequest', function ($q) use ($search): void {
                            $q->where('pr_no', 'like', "%{$search}%")
                                ->orWhere('purpose', 'like', "%{$search}%");
                        })
                        ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId))
            ->when($dateFrom, fn ($query) => $query->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('date', '<=', $dateTo));

        $pendingConstraint = fn ($query) => $query->whereHas(
            'assetReceipts',
            fn ($receipts) => $receipts->where('status', AssetReceiptStatus::Pending)
        );
        $completedConstraint = fn ($query) => $query->whereDoesntHave(
            'assetReceipts',
            fn ($receipts) => $receipts->where('status', AssetReceiptStatus::Pending)
        );

        $tabs = [
            [
                'key' => 'all',
                'label' => 'All',
                'badge' => $baseQuery()->count(),
                'tone' => 'neutral',
            ],
            [
                'key' => 'pending',
                'label' => 'Pending',
                'badge' => $baseQuery()->tap($pendingConstraint)->count(),
                'tone' => 'warn',
            ],
            [
                'key' => 'completed',
                'label' => 'Completed',
                'badge' => $baseQuery()->tap($completedConstraint)->count(),
                'tone' => 'success',
            ],
        ];

        $orders = $baseQuery()
            ->with(['vendor', 'purchaseRequest', 'assetReceipts.item'])
            ->when($tab === 'pending', $pendingConstraint)
            ->when($tab === 'completed', $completedConstraint)
            ->latest('date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (PurchaseOrders $order): array {
                $receipts = $order->assetReceipts;
                $pending = $receipts->where('status', AssetReceiptStatus::Pending)->count();
                $received = $receipts->where('status', AssetReceiptStatus::Received)->count();
                $total = $receipts->count();

                return [
                    'id' => $order->id,
                    'po_no' => $order->po_no,
                    'pr_no' => $order->purchaseRequest?->pr_no,
                    'purpose' => $order->purchaseRequest?->purpose,
                    'vendor' => $order->vendor?->name,
                    'date' => $order->date,
                    'status' => $order->status?->value,
                    'status_label' => $order->status?->getLabel(),
                    'asset_status' => $pending > 0 ? 'pending' : 'completed',
                    'asset_status_label' => $pending > 0 ? 'Pending' : 'Completed',
                    'pending_count' => $pending,
                    'received_count' => $received,
                    'total_count' => $total,
                    'progress' => $total > 0 ? (int) round(($received / $total) * 100) : 0,
                ];
            });

        return Inertia::render('AssetManagement/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'tab' => $tab,
                'vendor_id' => $vendorId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'tabs' => $tabs,
            'pinnedTab' => $pinnedTab,
            'filterOptions' => [
                'vendors' => Vendors::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function show(Request $request, PurchaseOrders $purchaseOrder, SnipeItService $snipeIt): Response
    {
        abort_unless($request->user()->can('view_asset::management'), 403);
        abort_unless($this->isAssetManagementPo($purchaseOrder), 404);

        $purchaseOrder->syncAssetReceipts();

        $purchaseOrder->load([
            'vendor',
            'purchaseRequest',
            'assetReceipts.item',
            'assetReceipts.purchaseOrderDetail',
            'assetReceipts.purchaseOrder',
            'assetReceipts.receivedByUser',
        ]);

        return Inertia::render('AssetManagement/Show', [
            'order' => [
                'id' => $purchaseOrder->id,
                'po_no' => $purchaseOrder->po_no,
                'pr_id' => $purchaseOrder->purchaseRequest?->id,
                'pr_no' => $purchaseOrder->purchaseRequest?->pr_no,
                'purpose' => $purchaseOrder->purchaseRequest?->purpose,
                'vendor' => $purchaseOrder->vendor?->name,
                'date' => $purchaseOrder->date,
                'status' => $purchaseOrder->status?->value,
                'status_label' => $purchaseOrder->status?->getLabel(),
                'asset_status' => $purchaseOrder->hasPendingAssetReceipts() ? 'pending' : 'completed',
                'asset_status_label' => $purchaseOrder->hasPendingAssetReceipts() ? 'Pending' : 'Completed',
                'pending_count' => $purchaseOrder->assetReceipts->where('status', AssetReceiptStatus::Pending)->count(),
                'received_count' => $purchaseOrder->assetReceipts->where('status', AssetReceiptStatus::Received)->count(),
                'total_count' => $purchaseOrder->assetReceipts->count(),
            ],
            'receipts' => $purchaseOrder->assetReceipts->map(fn (AssetReceipt $receipt): array => $this->receiptPayload($receipt))->values(),
            'snipe' => [
                'enabled' => $snipeIt->isEnabled(),
                'defaults' => [
                    'status_id' => config('snipe-it.default_status_id'),
                    'accessory_category_id' => config('snipe-it.default_accessory_category_id'),
                ],
                'options' => $snipeIt->isEnabled() ? [
                    'models' => $this->optionList($snipeIt->modelOptions()),
                    'statuses' => $this->optionList($snipeIt->statusLabelOptions()),
                    'locations' => $this->optionList($snipeIt->locationOptions()),
                    'suppliers' => $this->optionList($snipeIt->supplierOptions()),
                    'categories' => $this->optionList($snipeIt->categoryOptions()),
                ] : null,
            ],
        ]);
    }

    public function receive(Request $request, PurchaseOrders $purchaseOrder, AssetReceipt $receipt): RedirectResponse
    {
        abort_unless($request->user()->can('view_asset::management'), 403);
        abort_unless((int) $receipt->purchase_order_id === (int) $purchaseOrder->id, 404);
        abort_unless($this->isAssetManagementPo($purchaseOrder), 404);

        $receipt->loadMissing('item');
        $isAccessory = $receipt->isAccessoryLine();

        $data = $isAccessory
            ? $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'snipe_category_id' => ['required', 'integer'],
                'snipe_quantity' => ['required', 'numeric', 'min:1'],
                'snipe_location_id' => ['nullable', 'integer'],
                'order_number' => ['nullable', 'string', 'max:255'],
                'purchase_date' => ['nullable', 'date'],
                'purchase_cost' => ['nullable', 'numeric'],
                'snipe_supplier_id' => ['nullable', 'integer'],
                'model_number' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
            ])
            : $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'snipe_status_id' => ['required', 'integer'],
                'snipe_model_id' => ['required', 'integer'],
                'serial_number' => ['nullable', 'string', 'max:255'],
                'snipe_location_id' => ['nullable', 'integer'],
                'order_number' => ['nullable', 'string', 'max:255'],
                'purchase_date' => ['nullable', 'date'],
                'purchase_cost' => ['nullable', 'numeric'],
                'snipe_supplier_id' => ['nullable', 'integer'],
                'notes' => ['nullable', 'string'],
                'cao_asset_code' => ['required', 'string', 'max:255'],
                'finance_old_asset_tag' => ['nullable', 'string', 'max:255'],
                'asset_class' => ['nullable', 'string', 'max:255'],
                'mac_address' => ['nullable', 'string', 'max:255', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
            ]);

        try {
            $created = ReceiveAssetReceipt::run($receipt, $data);
        } catch (SnipeItException $exception) {
            throw ValidationException::withMessages([
                'snipe' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Item marked as received ('.$created->summaryLabel().').');
    }

    public function bulkReceive(Request $request, PurchaseOrders $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->can('view_asset::management'), 403);
        abort_unless($this->isAssetManagementPo($purchaseOrder), 404);

        $typeValue = $request->validate([
            'type' => ['required', 'in:asset,accessory'],
        ])['type'];

        $type = $typeValue === 'accessory' ? ItemTypeEnum::Accessory : ItemTypeEnum::Asset;
        $repeaterKey = $type === ItemTypeEnum::Accessory ? 'accessories' : 'assets';

        $data = $type === ItemTypeEnum::Accessory
            ? $request->validate([
                'snipe_category_id' => ['required', 'integer'],
                'snipe_location_id' => ['nullable', 'integer'],
                'order_number' => ['nullable', 'string', 'max:255'],
                'purchase_date' => ['nullable', 'date'],
                'snipe_supplier_id' => ['nullable', 'integer'],
                'notes' => ['nullable', 'string'],
                'accessories' => ['required', 'array', 'min:1'],
                'accessories.*.asset_receipt_id' => ['required', 'integer'],
                'accessories.*.name' => ['required', 'string', 'max:255'],
                'accessories.*.snipe_quantity' => ['required', 'numeric', 'min:1'],
                'accessories.*.purchase_cost' => ['nullable', 'numeric'],
                'accessories.*.line_label' => ['nullable', 'string'],
            ])
            : $request->validate([
                'snipe_status_id' => ['required', 'integer'],
                'snipe_model_id' => ['required', 'integer'],
                'snipe_location_id' => ['nullable', 'integer'],
                'order_number' => ['nullable', 'string', 'max:255'],
                'purchase_date' => ['nullable', 'date'],
                'purchase_cost' => ['nullable', 'numeric'],
                'snipe_supplier_id' => ['nullable', 'integer'],
                'cao_asset_code' => ['required', 'string', 'max:255'],
                'finance_old_asset_tag' => ['nullable', 'string', 'max:255'],
                'asset_class' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
                'assets' => ['required', 'array', 'min:1'],
                'assets.*.asset_receipt_id' => ['required', 'integer'],
                'assets.*.name' => ['required', 'string', 'max:255'],
                'assets.*.serial_number' => ['required', 'string', 'max:255'],
                'assets.*.mac_address' => ['nullable', 'string', 'max:255', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
                'assets.*.unit_label' => ['nullable', 'string'],
            ]);

        $ids = collect($data[$repeaterKey])->pluck('asset_receipt_id')->all();
        $records = AssetReceipt::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereIn('id', $ids)
            ->with('item')
            ->get();

        $result = BulkReceiveAssetReceipts::run($records, $data, $type, $repeaterKey);

        if ($result['succeeded'] > 0 && $result['failures'] === []) {
            return back()->with('success', $result['succeeded'].' item(s) created in Snipe-IT.');
        }

        if ($result['succeeded'] > 0) {
            return back()->with('success', $result['succeeded'].' succeeded. Failures: '.implode(' | ', $result['failures']));
        }

        throw ValidationException::withMessages([
            'bulk' => implode(' | ', $result['failures']) ?: 'Bulk receive failed.',
        ]);
    }

    public function checkSerial(Request $request, SnipeItService $snipeIt): JsonResponse
    {
        abort_unless($request->user()->can('view_asset::management'), 403);

        $data = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:255'],
            'exclude_hardware_id' => ['nullable', 'integer'],
        ]);

        try {
            $result = $snipeIt->checkSerialNumber(
                (string) ($data['serial_number'] ?? ''),
                $data['exclude_hardware_id'] ?? null,
            );
        } catch (SnipeItException $exception) {
            return response()->json([
                'ok' => false,
                'available' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'available' => $result->isAvailable,
            'message' => $result->message,
        ]);
    }

    private function scopedQuery()
    {
        return PurchaseOrders::query()
            ->where('payment_method', 'purchase_order')
            ->whereIn('status', [
                PurchaseOrderStatus::Submitted,
                PurchaseOrderStatus::Closed,
            ])
            ->whereHas('purchaseOrderDetails', fn ($q) => $q->whereSnipeItItem());
    }

    private function isAssetManagementPo(PurchaseOrders $purchaseOrder): bool
    {
        return $this->scopedQuery()->whereKey($purchaseOrder->id)->exists();
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function receiptPayload(AssetReceipt $receipt): array
    {
        $baseName = $receipt->name ?? $receipt->asset_description ?? $receipt->item?->name;
        $unitLabel = $receipt->unitLabel();
        $purchaseDate = $this->formatDate($receipt->purchase_date ?? $receipt->purchaseOrder?->date);

        return [
            'id' => $receipt->id,
            'type' => $receipt->item?->type?->value,
            'type_label' => $receipt->item?->type?->getLabel(),
            'is_accessory' => $receipt->isAccessoryLine(),
            'item_name' => $receipt->item?->name,
            'unit_label' => $unitLabel,
            'status' => $receipt->status?->value,
            'status_label' => $receipt->status?->getLabel(),
            'asset_tag' => $receipt->asset_tag,
            'snipe_quantity' => $receipt->snipe_quantity,
            'name' => $receipt->name,
            'serial_number' => $receipt->serial_number,
            'snipe_model_id' => $receipt->snipe_model_id,
            'snipe_category_id' => $receipt->snipe_category_id,
            'snipe_it_hardware_id' => $receipt->snipe_it_hardware_id,
            'snipe_it_accessory_id' => $receipt->snipe_it_accessory_id,
            'received_at' => optional($receipt->received_at)?->toIso8601String(),
            'received_at_label' => optional($receipt->received_at)?->format('d/m/Y h:i A'),
            'received_by' => $receipt->receivedByUser?->name,
            'is_pending' => $receipt->status === AssetReceiptStatus::Pending,
            'defaults' => $receipt->isAccessoryLine()
                ? [
                    'name' => $baseName,
                    'snipe_category_id' => $receipt->snipe_category_id ?? config('snipe-it.default_accessory_category_id'),
                    'snipe_quantity' => $receipt->snipe_quantity ?? $receipt->purchaseOrderDetail?->assetLineQuantity() ?? 1,
                    'snipe_location_id' => $receipt->snipe_location_id,
                    'order_number' => $receipt->order_number ?? $receipt->invoice_number ?? $receipt->purchaseOrder?->po_no,
                    'purchase_date' => $purchaseDate,
                    'purchase_cost' => $receipt->purchase_cost ?? $receipt->purchaseOrderDetail?->amount,
                    'snipe_supplier_id' => $receipt->snipe_supplier_id,
                    'model_number' => $receipt->model_number,
                    'notes' => $receipt->notes,
                ]
                : [
                    'name' => $baseName && $unitLabel ? "{$baseName} ({$unitLabel})" : $baseName,
                    'serial_number' => $receipt->serial_number,
                    'snipe_model_id' => $receipt->snipe_model_id,
                    'snipe_status_id' => $receipt->snipe_status_id ?? config('snipe-it.default_status_id'),
                    'snipe_location_id' => $receipt->snipe_location_id,
                    'snipe_supplier_id' => $receipt->snipe_supplier_id,
                    'order_number' => $receipt->order_number ?? $receipt->invoice_number ?? $receipt->purchaseOrder?->po_no,
                    'purchase_date' => $purchaseDate,
                    'purchase_cost' => $receipt->purchase_cost ?? $receipt->defaultUnitPurchaseCost(),
                    'notes' => $receipt->notes,
                    'cao_asset_code' => $receipt->cao_asset_code,
                    'finance_old_asset_tag' => $receipt->finance_old_asset_tag,
                    'asset_class' => $receipt->asset_class,
                    'mac_address' => $receipt->mac_address,
                ],
        ];
    }

    /**
     * @param  array<int|string, string>  $options
     * @return array<int, array{id: int|string, label: string}>
     */
    private function optionList(array $options): array
    {
        return collect($options)
            ->map(fn ($label, $id) => ['id' => $id, 'label' => $label])
            ->values()
            ->all();
    }
}
