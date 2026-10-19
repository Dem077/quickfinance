<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestsStatus;
use App\Models\Concerns\LogsModelActivity;
use App\Models\Concerns\ScopesPurchaseRequests;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class PurchaseRequests extends Model
{
    use LogsModelActivity;
    use ScopesPurchaseRequests;

    protected $fillable = [
        'pr_no',
        'date',
        'location_id',
        'project_id',
        'purpose',
        'user_id',
        'is_submited',
        'is_approved',
        'is_approved_by_hod',
        'is_canceled',
        'cancel_remark',
        'uploaded_document',
        'approved_canceled_by',
        'approved_by_md_dmd',
        'is_closed',
        'is_closed_by',
        'status',
        'approved_by_hod',
        'supporting_document',
    ];

    protected $casts = [
        'status' => PurchaseRequestsStatus::class,
    ];

    public static function generateNextPrNo(): string
    {
        $year = (int) date('Y');
        $prefix = "PR/AGRO/{$year}/";
        $start = max(1, (int) config('app.pr_sequence_start', 1));
        $startYear = config('app.pr_sequence_start_year');

        if ($startYear !== null && (int) $startYear !== $year) {
            $start = 1;
        }

        $maxSequence = self::query()
            ->where('pr_no', 'like', $prefix.'%')
            ->pluck('pr_no')
            ->map(function (?string $prNo) use ($prefix): int {
                if (! $prNo || ! str_starts_with($prNo, $prefix)) {
                    return 0;
                }

                $sequence = substr($prNo, strlen($prefix));

                return is_numeric($sequence) ? (int) $sequence : 0;
            })
            ->max() ?? 0;

        $count = max($maxSequence + 1, $start);

        do {
            $prNo = sprintf('PR/AGRO/%s/%04d', $year, $count);
            $exists = self::where('pr_no', $prNo)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $prNo;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseRequestDetails(): HasMany
    {
        return $this->HasMany(PurchaseRequestDetails::class, 'pr_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrders::class, 'pr_id');
    }

    public function openPurchaseOrdersForClose(): Collection
    {
        return $this->purchaseOrders()
            ->where('payment_method', 'purchase_order')
            ->where('status', '!=', PurchaseOrderStatus::Closed)
            ->orderBy('po_no')
            ->get();
    }

    public function applyGrnNumbersForClose(array $purchaseOrderGrns): void
    {
        foreach ($purchaseOrderGrns as $item) {
            if (empty($item['po_id']) || empty($item['grn_number'])) {
                continue;
            }

            $this->purchaseOrders()
                ->where('payment_method', 'purchase_order')
                ->where('id', $item['po_id'])
                ->update(['grn_number' => $item['grn_number']]);
        }
    }

    public function closeRelatedPurchaseOrders(?int $closedBy = null): void
    {
        $closedBy ??= Auth::id();

        $this->purchaseOrders()
            ->with('purchaseOrderDetails')
            ->get()
            ->each(function (PurchaseOrders $purchaseOrder) use ($closedBy): void {
                // Petty cash: clear PR pending/on-hold for PO items — do not deduct allocation.
                if ($purchaseOrder->payment_method === 'petty_cash') {
                    $purchaseOrder->releasePurchaseRequestPendingHold();

                    return;
                }

                // Normal PO: close so observer deducts each line amount from department budget.
                if (
                    $purchaseOrder->payment_method === 'purchase_order'
                    && $purchaseOrder->status !== PurchaseOrderStatus::Closed
                ) {
                    $purchaseOrder->syncAssetReceipts();

                    $purchaseOrder->update([
                        'status' => PurchaseOrderStatus::Closed,
                        'is_closed' => true,
                        'is_closed_by' => $closedBy,
                    ]);
                }
            });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'location_purchase_request', 'purchase_request_id', 'location_id');
    }

    public function approvedby(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_canceled_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function hodapprovedby(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_hod');
    }

    public function mdDmdApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_md_dmd');
    }

    public static function checkAndUpdateClosedStatus($id)
    {
        $purchaseRequest = self::find($id);
        if (! $purchaseRequest) {
            return;
        }

        $orders = $purchaseRequest->purchaseOrders()->get();

        if ($orders->isEmpty()) {
            return;
        }

        // PR only auto-closes when every related PO has finished — not when lines are merely ordered.
        $allFinished = $orders->every(function (PurchaseOrders $purchaseOrder): bool {
            if ($purchaseOrder->payment_method === 'petty_cash') {
                return $purchaseOrder->status === PurchaseOrderStatus::Reimbursed;
            }

            return $purchaseOrder->status === PurchaseOrderStatus::Closed;
        });

        if ($allFinished) {
            $purchaseRequest->update([
                'status' => PurchaseRequestsStatus::Closed->value,
                'is_closed_by' => 1,
            ]);
        }
    }
}
