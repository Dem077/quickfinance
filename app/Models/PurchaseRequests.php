<?php

namespace App\Models;

use App\Enums\PurchaseRequestsStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'is_closed',
        'is_closed_by',
        'status',
        'approved_by_hod',
        'supporting_document',
    ];

  
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseRequestDetails(): HasMany
    {
        return $this->HasMany(PurchaseRequestDetails::class, 'pr_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
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

    public static function checkAndUpdateClosedStatus($id)
    {
        $purchaseRequest = self::find($id);
        if (!$purchaseRequest) {
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
        $utilizedDetails = PurchaseRequestDetails::where('is_utilized' , true)->where('pr_id', $purchaseRequest->id)->count();
        
        if ($totalDetails === $utilizedDetails) {
            $purchaseRequest->update([
                'status' => PurchaseRequestsStatus::Closed->value,
                'is_closed_by' => 1,
            ]);
        }
    }
}
