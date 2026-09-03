<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\URL;

it('désabonne un contact via un lien signé', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();
    expect($contact->unsubscribed_at)->toBeNull();

    $url = URL::signedRoute('unsubscribe.show', ['organization' => $organization->id, 'contact' => $contact->id]);
    $this->get($url)->assertOk();

    expect($contact->fresh()->unsubscribed_at)->not->toBeNull();
});

it('refuse un lien de désabonnement non signé ou modifié', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();

    $this->get("/unsubscribe/{$organization->id}/{$contact->id}")->assertForbidden();

    $url = URL::signedRoute('unsubscribe.show', ['organization' => $organization->id, 'contact' => $contact->id]);
    $tampered = str_replace((string) $contact->id, (string) ($contact->id + 1), $url);
    $this->get($tampered)->assertForbidden();

    expect($contact->fresh()->unsubscribed_at)->toBeNull();
});

it('reste idempotent si le lien est visité plusieurs fois', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();

    $url = URL::signedRoute('unsubscribe.show', ['organization' => $organization->id, 'contact' => $contact->id]);
    $this->get($url)->assertOk();
    $firstUnsubscribedAt = $contact->fresh()->unsubscribed_at;

    $this->get($url)->assertOk();
    expect($contact->fresh()->unsubscribed_at->equalTo($firstUnsubscribedAt))->toBeTrue();
});
