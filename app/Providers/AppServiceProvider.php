<?php

namespace App\Providers;

use App\Models\PurchaseRequestDetails;
use App\Models\PurchaseRequests;
use App\Observers\PurchaseRequestDetailsObserver;
use App\Policies\ActivityPolicy;
use App\Policies\PurchaseRequestsPolicy;
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
    }
}
