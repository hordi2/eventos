<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Policies\ContactPolicy;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Venue;
use App\Domain\Event\Policies\EventPolicy;
use App\Domain\Event\Policies\VenuePolicy;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Listeners\ConfirmPromotedRegistration;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Policies\FormPolicy;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Policies\OrganizationPolicy;
use App\Listeners\LinkRegistrationToContact;
use App\Listeners\SendConfirmationEmail;
use App\Support\Capacity\Events\WaitlistEntryPromoted;
use App\Support\Messaging\TwilioWhatsappProvider;
use App\Support\Messaging\WhatsappProvider;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentOrganization::class);
        $this->app->bind(WhatsappProvider::class, TwilioWhatsappProvider::class);
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
        Gate::policy(Contact::class, ContactPolicy::class);

        EventFacade::listen(WaitlistEntryPromoted::class, ConfirmPromotedRegistration::class);
        // Ordre important : LinkRegistrationToContact doit s'exécuter en
        // premier, c'est lui qui renseigne contact_id (T-045).
        EventFacade::listen(RegistrationCreated::class, LinkRegistrationToContact::class);
        EventFacade::listen(RegistrationCreated::class, SendConfirmationEmail::class);

        // Débit par défaut prudent (T-043) : Postmark autorise bien plus,
        // mais rien dans le CDC n'impose un chiffre précis — à ajuster
        // depuis un seul endroit si le prestataire ou le forfait change.
        RateLimiter::for('email-sends', fn (): Limit => Limit::perMinute(120));

        // Plus prudent encore que l'e-mail : le débit WhatsApp effectif
        // dépend du palier de qualité Twilio (souvent ~1 msg/s pour un
        // numéro récent) — à relever une fois le palier réel connu.
        RateLimiter::for('whatsapp-sends', fn (): Limit => Limit::perMinute(60));
    }
}
