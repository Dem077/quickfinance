<?php

namespace App\Observers;

use App\Enums\AdvanceFormStatus;
use App\Mail\NotificationEmail;
use App\Mail\StatusEmail;
use App\Models\AdvanceForm;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AdvanceFormObserver
{
    public function updated(AdvanceForm $advanceForm): void
    {
        if ($advanceForm->isDirty('status')) {
            $this->handleStatusChange($advanceForm);
        }
    }

    protected function handleStatusChange(AdvanceForm $advanceForm): void
    {
        $advanceForm->loadMissing(['user.department.user']);

        $creator = $advanceForm->user;
        $hodEmail = $creator?->department?->user?->email;
        $mdDmdEmails = $this->emailsForPermission('md_dmd_approve_advance_form_purchase::orders');
        $procurementEmails = $this->emailsForPermission('view_any_purchase::orders', 'view_purchase::orders');
        $label = 'Advance Form '.$advanceForm->request_number;

        match ($advanceForm->status) {
            AdvanceFormStatus::Submitted => $this->notify($hodEmail, new NotificationEmail($label)),

            AdvanceFormStatus::HOD_Approved => $this->notifyMany($mdDmdEmails, new NotificationEmail($label)),

            AdvanceFormStatus::HOD_Rejected => $this->notify($creator?->email, new StatusEmail($label, 'rejected', '', 'HOD')),

            AdvanceFormStatus::DMD_MD_Approved => $this->notifyMany($procurementEmails, new NotificationEmail($label)),

            AdvanceFormStatus::DMD_MD_Rejected => $this->notify($creator?->email, new StatusEmail($label, 'rejected', '', 'MD / DMD')),

            default => null,
        };

        if ($advanceForm->status === AdvanceFormStatus::HOD_Approved && $creator?->email) {
            Mail::to($creator->email)->queue(new StatusEmail($label, 'approved', '', 'HOD'));
        }

        if ($advanceForm->status === AdvanceFormStatus::DMD_MD_Approved && $creator?->email) {
            Mail::to($creator->email)->queue(new StatusEmail($label, 'approved', '', 'MD / DMD'));
        }
    }

    /**
     * @param  string|array<int, string>|null  $recipients
     */
    protected function notify(string|array|null $recipients, NotificationEmail|StatusEmail $mailable): void
    {
        if (empty($recipients)) {
            return;
        }

        Mail::to($recipients)->queue($mailable);
    }

    /**
     * @param  array<int, string>  $recipients
     */
    protected function notifyMany(array $recipients, NotificationEmail $mailable): void
    {
        $recipients = array_values(array_filter($recipients));

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->queue($mailable);
    }

    /**
     * @return array<int, string>
     */
    protected function emailsForPermission(string ...$permissions): array
    {
        return User::query()
            ->whereHas('roles.permissions', function ($query) use ($permissions) {
                $query->where(function ($permissionQuery) use ($permissions) {
                    foreach ($permissions as $permission) {
                        $permissionQuery->orWhere('name', $permission);
                    }
                });
            })
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
