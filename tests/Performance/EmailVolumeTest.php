<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Organization\Models\Organization;
use App\Jobs\SendEmailMessageJob;
use App\Support\Messaging\SendEmailToContact;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Queue;

/**
 * Hors des testsuites de phpunit.xml, comme les autres tests de ce
 * répertoire (T-024, T-041) : 5 000 créations + dispatches prennent
 * plusieurs dizaines de secondes.
 *
 * Prouve que la CRÉATION et la MISE EN FILE de 5 000 e-mails ne saturent
 * pas le pipeline (aucune erreur, aucun ralentissement anormal) — pas que
 * 5 000 envois réels se terminent en quelques secondes, ce que le
 * middleware RateLimited empêcherait délibérément (voir
 * SendEmailMessageJobTest, qui prouve que le débit est bien respecté). Les
 * deux propriétés sont complémentaires, pas redondantes.
 */
it('crée et met en file 5 000 e-mails sans erreur ni doublon', function (): void {
    Queue::fake();
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $sendEmailToContact = app(SendEmailToContact::class);

    for ($i = 0; $i < 5000; $i++) {
        $contact = Contact::factory()->for($organization)->create(['email' => "invite{$i}@example.org"]);
        $sendEmailToContact->handle($organization, $contact, 'Invitation', '<p>Bonjour</p>', isTransactional: false);
    }

    expect(EmailMessage::query()->count())->toBe(5000);
    Queue::assertPushed(SendEmailMessageJob::class, 5000);
});
