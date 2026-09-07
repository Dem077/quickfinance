<?php

namespace App\Models;

use App\Enums\AssetReceiptStatus;
use App\Enums\ItemTypeEnum;
use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class PurchaseOrders extends Model
{
    use LogsModelActivity;

    protected $fillable = [
        'vendor_id',
        'po_no',
        'date',
        'pr_id',
        'payment_method',
        'grn_number',
        'is_submitted',
        'is_closed',
        'is_closed_by',
        'supporting_document',
        'advance_form_id',
        'is_advance_form_required',
        'is_reimbursed',
        'budget_deducted_at',
        'status',
    ];

    protected $casts = [
        'status' => PurchaseOrderStatus::class,
        'is_advance_form_required' => 'boolean',
        'budget_deducted_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendors::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequests::class, 'pr_id');
    }

    public function displayNumber(): string
    {
        if (filled($this->po_no)) {
            return (string) $this->po_no;
        }

        if ($this->payment_method === 'petty_cash') {
            return 'Pending PTC';
        }

        return '—';
    }

    public function purchaseOrderDetails(): HasMany
    {
        return $this->hasMany(PurchaseOrderDetails::class, 'po_id');
    }

    public function assetReceipts(): HasMany
    {
        return $this->hasMany(AssetReceipt::class, 'purchase_order_id')
            ->orderBy('purchase_order_detail_id')
            ->orderBy('unit_index');
    }

    public function syncAssetReceipts(): void
    {
        if ($this->payment_method !== 'purchase_order') {
            return;
        }

        $this->loadMissing('purchaseOrderDetails.items');

        foreach ($this->purchaseOrderDetails as $detail) {
            $item = $detail->resolvedItem();

            if (! $item || ! $item->type->syncsToSnipeIt()) {
                continue;
            }

            if ($item->type === ItemTypeEnum::Accessory) {
                AssetReceipt::firstOrCreate(
                    [
                        'purchase_order_detail_id' => $detail->id,
                        'unit_index' => 1,
                    ],
                    [
                        'purchase_order_id' => $this->id,
                        'item_id' => $item->id,
                        'status' => AssetReceiptStatus::Pending,
                        'snipe_quantity' => $detail->assetLineQuantity(),
                    ]
                );

                AssetReceipt::query()
                    ->where('purchase_order_detail_id', $detail->id)
                    ->where('unit_index', '>', 1)
                    ->where('status', AssetReceiptStatus::Pending)
                    ->delete();

                continue;
            }

            $quantity = $detail->assetLineQuantity();

            for ($unitIndex = 1; $unitIndex <= $quantity; $unitIndex++) {
                AssetReceipt::firstOrCreate(
                    [
                        'purchase_order_detail_id' => $detail->id,
                        'unit_index' => $unitIndex,
                    ],
                    [
                        'purchase_order_id' => $this->id,
                        'item_id' => $item->id,
                        'status' => AssetReceiptStatus::Pending,
                    ]
                );
            }

            AssetReceipt::query()
                ->where('purchase_order_detail_id', $detail->id)
                ->where('unit_index', '>', $quantity)
                ->where('status', AssetReceiptStatus::Pending)
                ->delete();
        }
    }

    public function hasPendingAssetReceipts(): bool
    {
        return $this->assetReceipts()
            ->where('status', AssetReceiptStatus::Pending)
            ->exists();
    }

    public function advanceForm(): BelongsTo
    {
        return $this->belongsTo(AdvanceForm::class, 'advance_form_id');
    }

    public function pettyCashReimbursment(): HasMany
    {
        return $this->hasMany(PettyCashReimbursment::class, 'po_id');
    }

    public function hasBudgetBeenDeducted(): bool
    {
        return $this->budget_deducted_at !== null;
    }

    /**
     * Deduct PO line amounts from the PR raiser department allocations and write history.
     * Used when a normal purchase order is closed.
     */
    public function deductDepartmentAllocations(?int $by = null): void
    {
        if ($this->payment_method !== 'purchase_order' || $this->hasBudgetBeenDeducted()) {
            return;
        }

        $this->loadMissing(['purchaseOrderDetails.budgetAccount', 'purchaseRequest.user']);

        $departmentId = $this->purchaseRequest?->user?->department_id;
        if (! $departmentId) {
            return;
        }

        $by ??= $this->is_closed_by ?? Auth::id();
        $deducted = false;

        foreach ($this->purchaseOrderDetails as $detail) {
            if (! $detail->budget_account_id) {
                continue;
            }

            $amount = (float) $detail->amount;
            if ($amount <= 0) {
                continue;
            }

            $allocation = SubBudgetDepartmentAllocation::query()
                ->where('sub_budget_account_id', $detail->budget_account_id)
                ->where('department_id', $departmentId)
                ->orderByDesc('amount')
                ->orderBy('id')
                ->first();

            if (! $allocation) {
                continue;
            }

            $allocation->update([
                'amount' => (float) $allocation->amount - $amount,
            ]);

            BudgetTransactionHistory::createtransaction(
                $detail->budget_account_id,
                'Purchase Order',
                $amount,
                (float) SubBudgetDepartmentAllocation::query()
                    ->where('sub_budget_account_id', $detail->budget_account_id)
                    ->sum('amount'),
                'Purchase Order Closed for PO ('.$this->po_no.' | Item: '.$detail->desc.')',
                $by,
            );

            $deducted = true;
        }

        if ($deducted) {
            $this->forceFill(['budget_deducted_at' => now()])->saveQuietly();
        }
    }

    /**
     * Release PR on-hold for items covered by this petty cash procure (mark PR lines utilized).
     * Allocation deduction happens later on petty cash reimbursement finance approval.
     */
    public function releasePurchaseRequestPendingHold(): void
    {
        if ($this->payment_method !== 'petty_cash' || ! $this->pr_id) {
            return;
        }

        $this->loadMissing('purchaseOrderDetails');

        $pr = PurchaseRequests::query()->find($this->pr_id);
        if (! $pr) {
            return;
        }

        foreach ($this->purchaseOrderDetails as $detail) {
            $item = $detail->resolvedItem();

            if (! $item && $detail->itemcode) {
                $item = Item::query()->where('item_code', $detail->itemcode)->first();
            }

            if (! $item) {
                continue;
            }

            $pr->purchaseRequestDetails()
                ->where('item_id', $item->id)
                ->update(['is_utilized' => true]);
        }
    }
}
