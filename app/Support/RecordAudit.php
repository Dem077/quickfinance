<?php

namespace App\Support;

use App\Models\AdvanceForm;
use App\Models\PurchaseOrderDetails;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class RecordAudit
{
    public static function purchaseRequestQuery(PurchaseRequests $purchaseRequest): Builder
    {
        $detailIds = $purchaseRequest->purchaseRequestDetails()->pluck('id');

        return Activity::query()
            ->where(function (Builder $query) use ($purchaseRequest, $detailIds): void {
                $query->where(function (Builder $query) use ($purchaseRequest): void {
                    $query->where('subject_type', $purchaseRequest->getMorphClass())
                        ->where('subject_id', $purchaseRequest->id);
                });

                if ($detailIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $query) use ($detailIds): void {
                        $query->where('subject_type', (new PurchaseRequestDetails)->getMorphClass())
                            ->whereIn('subject_id', $detailIds);
                    });
                }
            });
    }

    public static function purchaseOrderQuery(PurchaseOrders $purchaseOrder): Builder
    {
        $detailIds = $purchaseOrder->purchaseOrderDetails()->pluck('id');
        $advanceFormId = $purchaseOrder->advance_form_id;

        return Activity::query()
            ->where(function (Builder $query) use ($purchaseOrder, $detailIds, $advanceFormId): void {
                $query->where(function (Builder $query) use ($purchaseOrder): void {
                    $query->where('subject_type', $purchaseOrder->getMorphClass())
                        ->where('subject_id', $purchaseOrder->id);
                });

                if ($detailIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $query) use ($detailIds): void {
                        $query->where('subject_type', (new PurchaseOrderDetails)->getMorphClass())
                            ->whereIn('subject_id', $detailIds);
                    });
                }

                if ($advanceFormId) {
                    $query->orWhere(function (Builder $query) use ($advanceFormId): void {
                        $query->where('subject_type', (new AdvanceForm)->getMorphClass())
                            ->where('subject_id', $advanceFormId);
                    });
                }
            });
    }

    public static function purchaseRequestSubjectLabel(PurchaseRequests $purchaseRequest, Activity $activity): string
    {
        if ($activity->subject_type === (new PurchaseRequestDetails)->getMorphClass()) {
            return 'Line item #'.$activity->subject_id;
        }

        return $purchaseRequest->pr_no ?: 'Purchase request';
    }

    public static function purchaseOrderSubjectLabel(PurchaseOrders $purchaseOrder, Activity $activity): string
    {
        if ($activity->subject_type === (new PurchaseOrderDetails)->getMorphClass()) {
            return 'Line item #'.$activity->subject_id;
        }

        if ($activity->subject_type === (new AdvanceForm)->getMorphClass()) {
            return 'Advance form';
        }

        return $purchaseOrder->po_no ?: 'Purchase order';
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function paginate(Builder $query, User $user, callable $subjectLabel): LengthAwarePaginator
    {
        return $query
            ->with(['causer'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $activity): array => ActivityPresenter::summary(
                $activity,
                $user,
                $subjectLabel($activity),
            ));
    }
}
