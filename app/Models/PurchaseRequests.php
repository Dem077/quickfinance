<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestsStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class PurchaseRequests extends Model
{
    protected $fillable = [
        'pr_no',
        'date',
        'location_id',
        'project_id',
        'purpose',
        'user_id',
        'is_submited',
        'is_approved',
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
            ->where('payment_method', 'purchase_order')
            ->where('status', '!=', PurchaseOrderStatus::Closed)
            ->get()
            ->each(fn (PurchaseOrders $purchaseOrder) => $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Closed,
                'is_closed' => true,
                'is_closed_by' => $closedBy,
            ]));
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

        // Get all details
        $details = $purchaseRequest->purchaseRequestDetails;

        // Only proceed if there are details
        if ($details->isEmpty()) {
            return;
        }

        // Alternative method to check if all details are utilized
        $totalDetails = $details->count();
        $utilizedDetails = PurchaseRequestDetails::where('is_utilized', true)->where('pr_id', $purchaseRequest->id)->count();

        if ($totalDetails === $utilizedDetails) {
            $purchaseRequest->update([
                'status' => PurchaseRequestsStatus::Closed->value,
                'is_closed_by' => 1,
            ]);
        }
    }
}
