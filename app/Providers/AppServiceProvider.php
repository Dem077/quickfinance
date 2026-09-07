<?php

namespace App\Providers;

use App\Models\AdvanceForm;
use App\Models\ChartTemplate;
use App\Models\EmailLog;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Observers\AdvanceFormObserver;
use App\Observers\PurchaseOrdersObserver;
use App\Observers\PurchaseRequestDetailsObserver;
use App\Observers\PurchaseRequestsObserver;
use App\Policies\ActivityPolicy;
use App\Policies\ChartTemplatePolicy;
use App\Policies\EmailLogPolicy;
use App\Policies\PurchaseRequestsPolicy;
use App\Policies\RolePolicy;
use App\Services\EmailStatusRecorder;
use Illuminate\Auth\Events\Login;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ChartTemplate::class, ChartTemplatePolicy::class);
        Gate::policy(PurchaseRequests::class, PurchaseRequestsPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(EmailLog::class, EmailLogPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        PurchaseRequestDetails::observe(PurchaseRequestDetailsObserver::class);
        PurchaseRequests::observe(PurchaseRequestsObserver::class);
        PurchaseOrders::observe(PurchaseOrdersObserver::class);
        AdvanceForm::observe(AdvanceFormObserver::class);

        Event::listen(Login::class, function (Login $event): void {
            if (blank($event->user->signature)) {
                session()->put('remind_signature', true);
            }
        });

        $recorder = app(EmailStatusRecorder::class);
        Event::listen(JobQueued::class, [$recorder, 'handleJobQueued']);
        Event::listen(JobProcessing::class, [$recorder, 'handleJobProcessing']);
        Event::listen(JobProcessed::class, [$recorder, 'handleJobProcessed']);
        Event::listen(MessageSending::class, [$recorder, 'handleMessageSending']);
        Event::listen(MessageSent::class, [$recorder, 'handleMessageSent']);
        Event::listen(JobFailed::class, [$recorder, 'handleJobFailed']);
    }
}
