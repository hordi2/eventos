<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Venue;
use App\Domain\Event\Policies\EventPolicy;
use App\Domain\Event\Policies\VenuePolicy;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Policies\FormPolicy;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Policies\OrganizationPolicy;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentOrganization::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Venue::class, VenuePolicy::class);
        Gate::policy(Form::class, FormPolicy::class);
    }
}
