<?php

declare(strict_types=1);

use App\Http\Controllers\Guest\RegistrationController;
use App\Http\Controllers\Guest\TicketOrderController;
use App\Http\Controllers\Guest\TicketOrderPaymentController;
use App\Http\Controllers\Guest\UnsubscribeController;
use App\Http\Controllers\Organizer\AttendeeController;
use App\Http\Controllers\Organizer\AuditLogController;
use App\Http\Controllers\Organizer\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Organizer\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Organizer\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Organizer\Auth\GoogleAuthController;
use App\Http\Controllers\Organizer\Auth\NewPasswordController;
use App\Http\Controllers\Organizer\Auth\PasswordResetLinkController;
use App\Http\Controllers\Organizer\Auth\RegisteredUserController;
use App\Http\Controllers\Organizer\Auth\VerifyEmailController;
use App\Http\Controllers\Organizer\BadgeController;
use App\Http\Controllers\Organizer\CheckInController;
use App\Http\Controllers\Organizer\ContactController;
use App\Http\Controllers\Organizer\ContactImportController;
use App\Http\Controllers\Organizer\DashboardController;
use App\Http\Controllers\Organizer\EmailTemplateController;
use App\Http\Controllers\Organizer\EventController;
use App\Http\Controllers\Organizer\EventSegmentController;
use App\Http\Controllers\Organizer\FormController;
use App\Http\Controllers\Organizer\MessageAutomationController;
use App\Http\Controllers\Organizer\TagController;
use App\Http\Controllers\Organizer\TicketTypeController;
use App\Http\Controllers\Organizer\WhatsappTemplateController;
use App\Http\Controllers\Webhooks\FlutterwaveWebhookController;
use App\Http\Controllers\Webhooks\PostmarkWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Webhooks\TwilioWhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');

    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::get('verify-email', EmailVerificationPromptController::class)->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)
        ->middleware(['verified', 'resolve-organization'])
        ->name('dashboard');

    Route::middleware(['verified', 'resolve-organization', 'can-organization:viewAuditLog'])->group(function (): void {
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        Route::get('audit-log/export', [AuditLogController::class, 'export'])->name('audit-log.export');
    });

    Route::middleware(['verified', 'resolve-organization'])->group(function (): void {
        Route::middleware('can-organization:createEvents')->group(function (): void {
            Route::get('events/create', [EventController::class, 'create'])->name('events.create');
            Route::post('events', [EventController::class, 'store'])->name('events.store');
        });

        Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::patch('events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::post('events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');

        Route::middleware('can-organization:createEvents')->group(function (): void {
            Route::get('events/{event}/form/create', [FormController::class, 'create'])->name('forms.create');
            Route::post('events/{event}/form', [FormController::class, 'store'])->name('forms.store');
        });

        Route::get('forms/{form}/edit', [FormController::class, 'edit'])->name('forms.edit');
        Route::patch('forms/{form}', [FormController::class, 'update'])->name('forms.update');
        Route::post('forms/{form}/publish', [FormController::class, 'publish'])->name('forms.publish');

        Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('contacts/create', [ContactController::class, 'create'])->name('contacts.create');
        Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::get('contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
        Route::patch('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');

        Route::get('contact-imports/create', [ContactImportController::class, 'create'])->name('contact-imports.create');
        Route::post('contact-imports', [ContactImportController::class, 'store'])->name('contact-imports.store');
        Route::get('contact-imports/{import}/mapping', [ContactImportController::class, 'mapping'])->name('contact-imports.mapping');
        Route::post('contact-imports/{import}/mapping', [ContactImportController::class, 'confirmMapping'])->name('contact-imports.confirm-mapping');
        Route::get('contact-imports/{import}', [ContactImportController::class, 'show'])->name('contact-imports.show');

        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
        Route::post('tags', [TagController::class, 'store'])->name('tags.store');
        Route::patch('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        Route::get('events/{event}/segments', [EventSegmentController::class, 'index'])->name('events.segments.index');
        Route::get('events/{event}/segments/{segment}', [EventSegmentController::class, 'show'])->name('events.segments.show');
        Route::post('events/{event}/segments/{segment}/tag', [EventSegmentController::class, 'applyTag'])->name('events.segments.apply-tag');

        Route::post('attendees/{attendee}/toggle-check-in', [AttendeeController::class, 'toggleCheckIn'])->name('attendees.toggle-check-in');

        Route::get('events/{event}/check-in', [CheckInController::class, 'index'])->name('events.check-in.index');
        Route::post('events/{event}/check-in/scan', [CheckInController::class, 'scan'])->name('events.check-in.scan');
        Route::post('events/{event}/check-in/record', [CheckInController::class, 'record'])->name('events.check-in.record');
        Route::post('events/{event}/check-in/walk-in', [CheckInController::class, 'walkIn'])->name('events.check-in.walk-in');

        Route::get('events/{event}/badges', [BadgeController::class, 'index'])->name('events.badges.index');
        Route::post('events/{event}/badges/logo', [BadgeController::class, 'uploadLogo'])->name('events.badges.logo');
        Route::get('events/{event}/badges/batches/{batch}/download', [BadgeController::class, 'downloadBatch'])->name('events.badges.batches.download');
        Route::get('events/{event}/badges/batches/{batch}', [BadgeController::class, 'batchStatus'])->name('events.badges.batches.show');
        Route::post('events/{event}/badges/batches', [BadgeController::class, 'startBatch'])->name('events.badges.batches.store');
        Route::get('events/{event}/badges/{guestType}/{guestId}', [BadgeController::class, 'single'])->name('events.badges.single');

        Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/create', [EmailTemplateController::class, 'create'])->name('email-templates.create');
        Route::post('email-templates', [EmailTemplateController::class, 'store'])->name('email-templates.store');
        Route::get('email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::patch('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::delete('email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->name('email-templates.destroy');
        Route::get('email-templates/{emailTemplate}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
        Route::post('email-templates/{emailTemplate}/test-send', [EmailTemplateController::class, 'sendTest'])
            ->middleware('throttle:10,1')
            ->name('email-templates.test-send');

        Route::get('events/{event}/automations', [MessageAutomationController::class, 'index'])->name('events.automations.index');
        Route::post('events/{event}/automations', [MessageAutomationController::class, 'store'])->name('events.automations.store');
        Route::post('message-automations/{messageAutomation}/cancel', [MessageAutomationController::class, 'cancel'])->name('message-automations.cancel');

        Route::get('events/{event}/ticket-types', [TicketTypeController::class, 'index'])->name('events.ticket-types.index');
        Route::post('events/{event}/ticket-types', [TicketTypeController::class, 'store'])->name('events.ticket-types.store');
        Route::patch('ticket-types/{ticketType}', [TicketTypeController::class, 'update'])->name('ticket-types.update');
        Route::delete('ticket-types/{ticketType}', [TicketTypeController::class, 'destroy'])->name('ticket-types.destroy');
        Route::post('ticket-types/{ticketType}/price-tiers', [TicketTypeController::class, 'storeTier'])->name('ticket-types.price-tiers.store');
        Route::patch('price-tiers/{priceTier}', [TicketTypeController::class, 'updateTier'])->name('price-tiers.update');
        Route::delete('price-tiers/{priceTier}', [TicketTypeController::class, 'destroyTier'])->name('price-tiers.destroy');

        Route::get('whatsapp-templates', [WhatsappTemplateController::class, 'index'])->name('whatsapp-templates.index');
        Route::post('whatsapp-templates', [WhatsappTemplateController::class, 'store'])->name('whatsapp-templates.store');
        Route::patch('whatsapp-templates/{whatsappTemplate}', [WhatsappTemplateController::class, 'update'])->name('whatsapp-templates.update');
        Route::delete('whatsapp-templates/{whatsappTemplate}', [WhatsappTemplateController::class, 'destroy'])->name('whatsapp-templates.destroy');
        Route::get('whatsapp-templates/{whatsappTemplate}/preview', [WhatsappTemplateController::class, 'preview'])->name('whatsapp-templates.preview');
        Route::post('whatsapp-templates/{whatsappTemplate}/test-send', [WhatsappTemplateController::class, 'sendTest'])
            ->middleware('throttle:10,1')
            ->name('whatsapp-templates.test-send');
    });
});

// Page RSVP publique (T-031) : jamais d'authentification, jamais de
// création de compte demandée à l'invité. resolve-guest-event résout
// organisation + événement depuis le slug et pose le contexte multi-tenant.
Route::middleware('resolve-guest-event')
    ->prefix('r/{organization}/{event}')
    ->name('guest.registration.')
    ->group(function (): void {
        Route::get('mot-de-passe', [RegistrationController::class, 'passwordShow'])->name('password.show');
        Route::post('mot-de-passe', [RegistrationController::class, 'passwordVerify'])->name('password.verify');

        Route::get('/', [RegistrationController::class, 'start'])->name('start');

        Route::get('{token}/identite', [RegistrationController::class, 'identityShow'])->name('identity.show');
        Route::post('{token}/identite', [RegistrationController::class, 'identityStore'])->name('identity.store');

        Route::get('{token}/reponses', [RegistrationController::class, 'answersShow'])->name('answers.show');
        Route::post('{token}/reponses', [RegistrationController::class, 'answersStore'])->name('answers.store');

        Route::get('{token}/recap', [RegistrationController::class, 'reviewShow'])->name('review.show');
        Route::post('{token}/recap', [RegistrationController::class, 'reviewConfirm'])->name('review.confirm');

        Route::get('{token}/confirmation', [RegistrationController::class, 'confirmation'])->name('confirmation');
        Route::get('{token}/deja-inscrit', [RegistrationController::class, 'duplicate'])->name('duplicate');

        // Lien signé (T-033) : la signature protège {registration} contre
        // toute manipulation, inutile d'y ajouter un jeton non devinable.
        Route::middleware('signed')->group(function (): void {
            Route::match(['GET', 'POST'], 'inscriptions/{registration}/modifier', [RegistrationController::class, 'edit'])->name('edit');
            Route::match(['GET', 'POST'], 'inscriptions/{registration}/annuler', [RegistrationController::class, 'cancel'])->name('cancel');
        });
    });

// Panier et paiement des billets (T-058/T-059, M5.4/M5.3) : même principe
// que la page RSVP ci-dessus (resolve-guest-event, jamais d'authentification).
Route::middleware('resolve-guest-event')
    ->prefix('billets/{organization}/{event}')
    ->name('guest.ticketing.')
    ->group(function (): void {
        Route::get('/', [TicketOrderController::class, 'show'])->name('show');
        Route::post('/', [TicketOrderController::class, 'store'])->name('store');

        Route::prefix('{order}')->name('payment.')->group(function (): void {
            Route::get('paiement', [TicketOrderPaymentController::class, 'show'])->name('show');
            Route::post('paiement/carte', [TicketOrderPaymentController::class, 'stripe'])->name('stripe');
            Route::post('paiement/mobile-money', [TicketOrderPaymentController::class, 'mobileMoney'])->name('mobile-money');
            Route::post('paiement/arrivee', [TicketOrderPaymentController::class, 'onSite'])->name('on-site');
            Route::get('statut', [TicketOrderPaymentController::class, 'status'])->name('status');
            Route::get('confirmation', [TicketOrderPaymentController::class, 'confirmation'])->name('confirmation');
            Route::get('billet/{ticket}', [TicketOrderPaymentController::class, 'downloadTicket'])->name('ticket');
        });
    });

// Lien de désabonnement (T-043) : signé, jamais authentifié — comme les
// liens de modification/annulation d'inscription ci-dessus.
Route::get('unsubscribe/{organization}/{contact}', UnsubscribeController::class)
    ->middleware('signed')
    ->name('unsubscribe.show');

// Webhook Postmark (T-043) : public, protégé par Basic Auth (voir
// PostmarkWebhookController), jamais par CSRF — un webhook ne porte pas de
// jeton de session (exclusion dans bootstrap/app.php). Limité en débit
// comme tout endpoint public (§7 du CLAUDE.md).
Route::post('webhooks/postmark', PostmarkWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.postmark');

// Webhook Twilio (statut de livraison WhatsApp) : public, protégé par la
// signature X-Twilio-Signature (voir TwilioWhatsappWebhookController),
// jamais par CSRF — même raisonnement que le webhook Postmark ci-dessus.
Route::post('webhooks/twilio-whatsapp', TwilioWhatsappWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.twilio-whatsapp');

// Webhook Stripe (confirmation/échec de paiement, T-052) : public, protégé
// par la signature Stripe-Signature (voir StripeWebhookController), jamais
// par CSRF — même raisonnement que les webhooks Postmark/Twilio ci-dessus.
Route::post('webhooks/stripe', StripeWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.stripe');

// Webhook Flutterwave (confirmation/échec Mobile Money, T-053) : public,
// protégé par la signature flutterwave-signature (voir
// FlutterwaveWebhookController), jamais par CSRF — même raisonnement que
// les webhooks Postmark/Twilio/Stripe ci-dessus.
Route::post('webhooks/flutterwave', FlutterwaveWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.flutterwave');
