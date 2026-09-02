<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Organization\Models\MembershipRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function csvUploadFor(string $content, string $filename = 'contacts.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'contact-import-test');
    file_put_contents($path, $content);

    return new UploadedFile($path, $filename, 'text/csv', null, true);
}

it('importe un fichier CSV de bout en bout : dépôt, mappage, traitement', function (): void {
    Storage::fake('local');
    [, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $csv = "Prénom,Nom,E-mail,Consentement WhatsApp\n"
        ."Grace,Mbuyi,grace@example.org,Oui\n"
        ."Jean,Kalala,jean@example.org,Non\n";

    $upload = $this->actingAs($admin)->post('/contact-imports', ['file' => csvUploadFor($csv)]);
    $import = ContactImport::query()->firstOrFail();
    $upload->assertRedirect(route('contact-imports.mapping', $import));

    expect($import->column_mapping)->toMatchArray([
        'Prénom' => 'first_name',
        'Nom' => 'last_name',
        'E-mail' => 'email',
        'Consentement WhatsApp' => 'whatsapp_consent',
    ]);

    $mappingPage = $this->actingAs($admin)->get(route('contact-imports.mapping', $import));
    $mappingPage->assertOk();
    $mappingPage->assertInertia(fn ($page) => $page->has('preview', 2));

    $confirm = $this->actingAs($admin)->post(route('contact-imports.confirm-mapping', $import), [
        'mapping' => $import->column_mapping,
        'duplicate_strategy' => 'merge',
    ]);
    $confirm->assertRedirect(route('contact-imports.show', $import));

    $import->refresh();
    expect($import->status->value)->toBe('completed');
    expect($import->total_rows)->toBe(2);
    expect($import->accepted_count)->toBe(2);
    expect($import->rejected_count)->toBe(0);

    $grace = Contact::query()->where('email', 'grace@example.org')->firstOrFail();
    expect($grace->first_name)->toBe('Grace');
    expect($grace->whatsapp_consent)->toBeTrue();

    $jean = Contact::query()->where('email', 'jean@example.org')->firstOrFail();
    expect($jean->whatsapp_consent)->toBeFalse();

    $report = $this->actingAs($admin)->get(route('contact-imports.show', $import));
    $report->assertOk();
    $report->assertInertia(fn ($page) => $page->has('rows.data', 2));
});

it('rejette une ligne avec un e-mail invalide et l\'explique dans le rapport', function (): void {
    Storage::fake('local');
    [, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $csv = "Prénom,E-mail\nGrace,pas-un-email\n";
    $this->actingAs($admin)->post('/contact-imports', ['file' => csvUploadFor($csv)]);
    $import = ContactImport::query()->firstOrFail();

    $this->actingAs($admin)->post(route('contact-imports.confirm-mapping', $import), [
        'mapping' => ['Prénom' => 'first_name', 'E-mail' => 'email'],
        'duplicate_strategy' => 'merge',
    ]);

    $import->refresh();
    expect($import->rejected_count)->toBe(1);
    $row = $import->rows()->firstOrFail();
    expect($row->status->value)->toBe('rejected');
    expect($row->reason)->toContain('invalide');
});

it('fusionne un doublon détecté par e-mail sans écraser les champs non fournis', function (): void {
    Storage::fake('local');
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $existing = Contact::factory()->for($organization)->create([
        'email' => 'grace@example.org',
        'company' => 'Itaza SARL',
        'first_name' => 'Grace',
    ]);

    $csv = "E-mail,Nom\ngrace@example.org,Mbuyi\n";
    $this->actingAs($admin)->post('/contact-imports', ['file' => csvUploadFor($csv)]);
    $import = ContactImport::query()->firstOrFail();

    $this->actingAs($admin)->post(route('contact-imports.confirm-mapping', $import), [
        'mapping' => ['E-mail' => 'email', 'Nom' => 'last_name'],
        'duplicate_strategy' => 'merge',
    ]);

    expect(Contact::query()->count())->toBe(1);
    $merged = $existing->fresh();
    expect($merged->last_name)->toBe('Mbuyi');
    expect($merged->company)->toBe('Itaza SARL');

    $import->refresh();
    expect($import->duplicate_count)->toBe(1);
});

it('fusionne un doublon détecté par numéro de téléphone quand les noms diffèrent', function (): void {
    Storage::fake('local');
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $existing = Contact::factory()->for($organization)->create([
        'phone_e164' => '+243812345678',
        'first_name' => 'Grace',
        'last_name' => 'Mbuyi',
        'company' => 'Kin Événements',
    ]);

    // Même personne, mais orthographe différente et pas d'e-mail commun :
    // seul le numéro de téléphone permet de la relier au contact existant.
    $csv = "Prénom,Nom,Téléphone\nGrace M.,Mbuyi-Kalala,+243 812 345 678\n";
    $this->actingAs($admin)->post('/contact-imports', ['file' => csvUploadFor($csv)]);
    $import = ContactImport::query()->firstOrFail();

    $this->actingAs($admin)->post(route('contact-imports.confirm-mapping', $import), [
        'mapping' => ['Prénom' => 'first_name', 'Nom' => 'last_name', 'Téléphone' => 'phone_e164'],
        'duplicate_strategy' => 'merge',
    ]);

    expect(Contact::query()->count())->toBe(1);
    $merged = $existing->fresh();
    expect($merged->last_name)->toBe('Mbuyi-Kalala');
    expect($merged->company)->toBe('Kin Événements');

    $import->refresh();
    expect($import->duplicate_count)->toBe(1);
});

it('ignore un doublon détecté quand la stratégie est "ignorer"', function (): void {
    Storage::fake('local');
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    Contact::factory()->for($organization)->create(['email' => 'grace@example.org', 'last_name' => 'Ancien']);

    $csv = "E-mail,Nom\ngrace@example.org,Mbuyi\n";
    $this->actingAs($admin)->post('/contact-imports', ['file' => csvUploadFor($csv)]);
    $import = ContactImport::query()->firstOrFail();

    $this->actingAs($admin)->post(route('contact-imports.confirm-mapping', $import), [
        'mapping' => ['E-mail' => 'email', 'Nom' => 'last_name'],
        'duplicate_strategy' => 'skip',
    ]);

    expect(Contact::query()->count())->toBe(1);
    expect(Contact::query()->firstOrFail()->last_name)->toBe('Ancien');
});

it('refuse le dépôt à un rôle sans capacité updateGuests', function (): void {
    Storage::fake('local');
    [, $viewer] = organizationWithContactRole(MembershipRole::Viewer);

    $this->actingAs($viewer)->post('/contact-imports', ['file' => csvUploadFor("E-mail\na@example.org\n")])
        ->assertForbidden();
});
