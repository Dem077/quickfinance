<?php

namespace App\Observers;

use App\Enums\PurchaseRequestsStatus;
use App\Mail\NotificationEmail;
use App\Mail\ProcurementNotification;
use App\Mail\StatusEmail;
use App\Models\PurchaseRequests;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class PurchaseRequestsObserver
{
    /**
     * Handle the PurchaseRequests "created" event.
     */
    public function created(PurchaseRequests $purchaseRequests): void
    {
        //
    }

    /**
     * Handle the PurchaseRequests "updated" event.
     */
    public function updated(PurchaseRequests $purchaseRequests): void
    {
        if ($purchaseRequests->isDirty('status')) {
            $this->handleStatusChange($purchaseRequests);
        }
    }

    /**
     * Handle the PurchaseRequests "deleted" event.
     */
    public function deleted(PurchaseRequests $purchaseRequests): void
    {
        //
    }

    /**
     * Handle the PurchaseRequests "restored" event.
     */
    public function restored(PurchaseRequests $purchaseRequests): void
    {
        //
    }

    /**
     * Handle the PurchaseRequests "force deleted" event.
     */
    public function forceDeleted(PurchaseRequests $purchaseRequests): void
    {
        //
    }

    protected function handleStatusChange(PurchaseRequests $purchaseRequest): void
    {
        $status = $purchaseRequest->status;
        $pruser = $purchaseRequest->user;
        $hod = $pruser->department->user->email;
        $finance = User::WhereHas('roles.permissions', function ($query) {
            $query->where('name', 'approve_purchase::requests');
        })->pluck('email')->toArray();
        $md_dmd = User::WhereHas('roles.permissions', function ($query) {
            $query->where('name', 'md_dmd_approve_purchase::requests');
        })->pluck('email')->toArray();
        $proce = User::WhereHas('roles.permissions', function ($query) {
            $query->where('name', 'view_any_purchase::orders')->orwhere('name', 'view_purchase::orders');
        })->pluck('email')->toArray();

        match ($status) {
            PurchaseRequestsStatus::Submitted->value => Mail::to($hod)->queue(new NotificationEmail('Purchase Request '.$purchaseRequest->pr_no)),

            PurchaseRequestsStatus::HODApproved->value => Mail::to($finance)->queue(new NotificationEmail('Purchase Request '.$purchaseRequest->pr_no)),

            PurchaseRequestsStatus::Approved->value => Mail::to($md_dmd)->queue(new NotificationEmail('Purchase Request '.$purchaseRequest->pr_no)),

            PurchaseRequestsStatus::HODApproved->value => Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'approved', '', 'HOD')),

            PurchaseRequestsStatus::HODRejected->value => Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'rejected', '', 'HOD')),

            PurchaseRequestsStatus::Approved->value => Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'approved', '', 'Finance')),

            PurchaseRequestsStatus::Rejected->value => Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'rejected', '', 'Finance')),

            PurchaseRequestsStatus::MD_DMD_Approved->value => Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'approved', '', 'MD / DMD')),

            PurchaseRequestsStatus::MD_DMD_Rejected->value => Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'rejected', '', 'MD / DMD')),

            PurchaseRequestsStatus::Canceled->value => Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'canceled', $purchaseRequest->cancel_remark, '')),

            // For Procurement User Notification - to be implemented
            PurchaseRequestsStatus::MD_DMD_Approved->value => Mail::to($proce)->queue(new ProcurementNotification($purchaseRequest->pr_no)),

            default => null,
        };
    }
}
