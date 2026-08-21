<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Locally the dashboard is open (Horizon's base provider skips this gate
     * entirely when App::environment('local')); everywhere else, only users
     * with the `admin` role (see Day 3's RBAC) may view it.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return (bool) optional($user)->isAdmin();
        });
    }
}
