<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\BelongsToOrganization;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\MultiTenancy\MissingOrganizationContextException;
use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Table et modèle jetables
|--------------------------------------------------------------------------
|
| Aucun module métier n'existe encore (T-010+). On simule ici une vraie table
| cloisonnée par organisation pour valider, une fois pour toutes, que le
| trait BelongsToOrganization et la row-level security PostgreSQL
| fonctionnent réellement avant que du code métier ne s'appuie dessus.
|
*/
function scopedFixtureModel(): Model
{
    return new class extends Model
    {
        use BelongsToOrganization;

        protected $table = 'scoped_fixtures';

        protected $fillable = ['organization_id', 'label'];
    };
}

beforeEach(function (): void {
    Schema::create('scoped_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('organization_id');
        $table->string('label');
        $table->timestamps();
    });

    OrganizationRowLevelSecurity::enable('scoped_fixtures');
});

afterEach(function (): void {
    OrganizationRowLevelSecurity::disable('scoped_fixtures');
    Schema::dropIfExists('scoped_fixtures');
    app(CurrentOrganization::class)->clear();
});

it('rattache automatiquement une création à l\'organisation courante', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $fixture = scopedFixtureModel()::query()->create(['label' => 'test']);

    expect($fixture->organization_id)->toBe($organization->id);
});

it('lève une exception explicite sans contexte d\'organisation', function (): void {
    scopedFixtureModel()::query()->get();
})->throws(MissingOrganizationContextException::class);

it('un utilisateur de l\'organisation A ne peut jamais lire une donnée de l\'organisation B', function (): void {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    app(CurrentOrganization::class)->set($organizationA);
    scopedFixtureModel()::query()->create(['label' => 'appartient à A']);

    app(CurrentOrganization::class)->set($organizationB);
    scopedFixtureModel()::query()->create(['label' => 'appartient à B']);

    $visibleDepuisB = scopedFixtureModel()::query()->pluck('label');

    expect($visibleDepuisB)->toEqual(collect(['appartient à B']));
});

it('la row level security postgresql bloque même une requête sql brute mal filtrée', function (): void {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    app(CurrentOrganization::class)->set($organizationA);
    DB::table('scoped_fixtures')->insert([
        'organization_id' => $organizationA->id,
        'label' => 'donnée A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CurrentOrganization::class)->set($organizationB);

    // Requête brute, sans aucun where organization_id : c'est exactement le
    // scénario qu'un bug applicatif pourrait produire.
    $rows = DB::select('select * from scoped_fixtures');

    expect($rows)->toBeEmpty();
});

it('sans contexte d\'organisation, la row level security bloque tout, y compris en sql brut', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    DB::table('scoped_fixtures')->insert([
        'organization_id' => $organization->id,
        'label' => 'donnée existante',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CurrentOrganization::class)->clear();

    $rows = DB::select('select * from scoped_fixtures');

    expect($rows)->toBeEmpty();
});
