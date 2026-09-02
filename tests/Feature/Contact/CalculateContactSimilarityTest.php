<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Support\CalculateContactSimilarity;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;

it('attribue un score de 100 sur un e-mail identique', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $existing = Contact::factory()->for($organization)->create(['email' => 'grace@example.org']);

    $score = app(CalculateContactSimilarity::class)->score(['email' => 'Grace@Example.org'], $existing);

    expect($score)->toBe(100);
    expect(app(CalculateContactSimilarity::class)->isDuplicate($score))->toBeTrue();
});

it('attribue un score élevé sur un nom quasi identique', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $existing = Contact::factory()->for($organization)->create(['first_name' => 'Grace', 'last_name' => 'Mbuyi', 'email' => 'autre@example.org']);

    $score = app(CalculateContactSimilarity::class)->score(['first_name' => 'Grace', 'last_name' => 'Mbuyi'], $existing);

    expect($score)->toBeGreaterThanOrEqual(85);
});

it('attribue un score faible sur des noms différents', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $existing = Contact::factory()->for($organization)->create(['first_name' => 'Grace', 'last_name' => 'Mbuyi']);

    $score = app(CalculateContactSimilarity::class)->score(['first_name' => 'Jean', 'last_name' => 'Kalala'], $existing);

    expect(app(CalculateContactSimilarity::class)->isDuplicate($score))->toBeFalse();
});
