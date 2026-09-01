<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Http\Middleware\ResolveCurrentOrganization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\Request;

it('résout l\'organisation courante depuis l\'adhésion de l\'utilisateur connecté', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $user->memberships()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::Owner,
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app('session.store'));

    app(ResolveCurrentOrganization::class)->handle($request, fn () => response('ok'));

    expect(app(CurrentOrganization::class)->id())->toBe($organization->id);
});

it('privilégie l\'organisation choisie en session sur la première adhésion trouvée', function (): void {
    $user = User::factory()->create();
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $user->memberships()->create(['organization_id' => $organizationA->id, 'role' => MembershipRole::Owner]);
    $user->memberships()->create(['organization_id' => $organizationB->id, 'role' => MembershipRole::Admin]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('current_organization_id', $organizationB->id);

    app(ResolveCurrentOrganization::class)->handle($request, fn () => response('ok'));

    expect(app(CurrentOrganization::class)->id())->toBe($organizationB->id);
});

it('ne définit aucune organisation courante pour un visiteur non authentifié', function (): void {
    $request = Request::create('/');
    $request->setUserResolver(fn () => null);
    $request->setLaravelSession(app('session.store'));

    app(ResolveCurrentOrganization::class)->handle($request, fn () => response('ok'));

    expect(app(CurrentOrganization::class)->has())->toBeFalse();
});
