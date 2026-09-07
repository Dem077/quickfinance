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
    public function created(PurchaseRequests $purchaseRequests): void
    {
        //
    }

    public function updated(PurchaseRequests $purchaseRequests): void
    {
        if ($purchaseRequests->isDirty('status')) {
            if ($purchaseRequests->status === PurchaseRequestsStatus::Closed) {
                $purchaseRequests->closeRelatedPurchaseOrders($purchaseRequests->is_closed_by);
            }

            $this->handleStatusChange($purchaseRequests);
        }
    }

    public function deleted(PurchaseRequests $purchaseRequests): void
    {
        //
    }

    public function restored(PurchaseRequests $purchaseRequests): void
    {
        //
    }

    public function forceDeleted(PurchaseRequests $purchaseRequests): void
    {
        //
    }

    protected function handleStatusChange(PurchaseRequests $purchaseRequest): void
    {
        $status = $purchaseRequest->status;
        $pruser = $purchaseRequest->user;
        $hod = $pruser?->department?->user?->email;
        $finance = User::query()->whereHas('roles.permissions', function ($query) {
            $query->where('name', 'approve_purchase::requests');
        })->pluck('email')->filter()->all();
        $mdDmd = User::query()->whereHas('roles.permissions', function ($query) {
            $query->where('name', 'md_dmd_approve_purchase::requests');
        })->pluck('email')->filter()->all();
        $procurement = User::query()->whereHas('roles.permissions', function ($query) {
            $query->where('name', 'receive_procurement_notification_purchase::orders');
        })->pluck('email')->filter()->all();

        // Reject / cancel emails (with reason) are sent from Actions.

        if ($status === PurchaseRequestsStatus::Submitted && $hod) {
            Mail::to($hod)->queue(new NotificationEmail('Purchase Request '.$purchaseRequest->pr_no));

            return;
        }

        if ($status === PurchaseRequestsStatus::HODApproved) {
            if ($finance !== []) {
                Mail::to($finance)->queue(new NotificationEmail('Purchase Request '.$purchaseRequest->pr_no));
            }
            if ($pruser?->email) {
                Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'approved', '', 'HOD'));
            }

            return;
        }

        if ($status === PurchaseRequestsStatus::Approved) {
            if ($mdDmd !== []) {
                Mail::to($mdDmd)->queue(new NotificationEmail('Purchase Request '.$purchaseRequest->pr_no));
            }
            if ($pruser?->email) {
                Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'approved', '', 'Finance'));
            }

            return;
        }

        if ($status === PurchaseRequestsStatus::MD_DMD_Approved) {
            if ($pruser?->email) {
                Mail::to($pruser->email)->queue(new StatusEmail('Purchase Request '.$purchaseRequest->pr_no, 'approved', '', 'MD / DMD'));
            }
            if ($procurement !== []) {
                Mail::to($procurement)->queue(new ProcurementNotification($purchaseRequest->pr_no));
            }
        }
    }
}
