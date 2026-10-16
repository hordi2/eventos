<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventTemplate;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Templates\CreateEventFromTemplate;
use Carbon\CarbonImmutable;
use Database\Seeders\StarterEventTemplateSeeder;

it('monte chaque modèle de démarrage en événement brouillon sur un compte au plan Gratuit', function (): void {
    $this->seed(StarterEventTemplateSeeder::class);

    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $templates = EventTemplate::query()->published()->get();
    expect($templates)->toHaveCount(6);

    foreach ($templates as $template) {
        $event = app(CreateEventFromTemplate::class)->handle(
            $template,
            $organization,
            $admin,
            "Test — {$template->name}",
            CarbonImmutable::now()->addWeek(),
        );

        expect($event->status->value)->toBe('draft');
    }

    app(CurrentOrganization::class)->clear();
});

it('ne crée aucun doublon quand le seeder est rejoué', function (): void {
    $this->seed(StarterEventTemplateSeeder::class);
    $this->seed(StarterEventTemplateSeeder::class);

    expect(EventTemplate::query()->count())->toBe(6);
});
