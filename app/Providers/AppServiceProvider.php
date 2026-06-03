<?php

namespace App\Providers;

use App\Models\AdvanceForm;
use App\Models\PurchaseOrders;
use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Observers\AdvanceFormObserver;
use App\Observers\PurchaseOrdersObserver;
use App\Observers\PurchaseRequestDetailsObserver;
use App\Observers\PurchaseRequestsObserver;
use App\Policies\ActivityPolicy;
use App\Policies\PurchaseRequestsPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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
        Gate::policy(PurchaseRequests::class, PurchaseRequestsPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        PurchaseRequestDetails::observe(PurchaseRequestDetailsObserver::class);
        PurchaseRequests::observe(PurchaseRequestsObserver::class);
        PurchaseOrders::observe(PurchaseOrdersObserver::class);
        AdvanceForm::observe(AdvanceFormObserver::class);

        Event::listen(Login::class, function (Login $event): void {
            if (blank($event->user->signature)) {
                session()->put('remind_signature', true);
            }
        });
    }
}
