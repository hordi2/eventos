<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * Critère d'acceptation de T-040 : recherche sur 10 000 contacts en
 * < 500 ms. Les lignes sont insérées en masse (DB::table()->insert(), par
 * lots) plutôt qu'une par une via la factory Eloquent — sans quoi la seule
 * préparation du test dominerait largement le temps mesuré.
 */
it('recherche parmi 10 000 contacts en moins de 500 ms', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $now = now();
    $rows = [];

    for ($i = 0; $i < 10000; $i++) {
        $rows[] = [
            'organization_id' => $organization->id,
            'first_name' => 'Prénom'.$i,
            'last_name' => 'Nom'.$i,
            'email' => "contact{$i}@example.org",
            'engagement_score' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (count($rows) === 1000) {
            DB::table('contacts')->insert($rows);
            $rows = [];
        }
    }

    // Une entrée précise à retrouver au milieu des 10 000.
    DB::table('contacts')->insert([
        'organization_id' => $organization->id,
        'first_name' => 'Grace',
        'last_name' => 'Mbuyi',
        'email' => 'grace.mbuyi@example.org',
        'engagement_score' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(Contact::query()->count())->toBe(10001);

    $start = microtime(true);
    $results = Contact::query()
        ->where(function ($q): void {
            $q->where('first_name', 'ilike', '%Grace%')
                ->orWhere('last_name', 'ilike', '%Grace%')
                ->orWhere('email', 'ilike', '%Grace%');
        })
        ->limit(50)
        ->get();
    $elapsedMs = (microtime(true) - $start) * 1000;

    expect($results)->toHaveCount(1);
    expect($results->first()->email)->toBe('grace.mbuyi@example.org');
    expect($elapsedMs)->toBeLessThan(500);
})->group('performance');
