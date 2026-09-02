<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Organization\Models\MembershipRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Critère d'acceptation de T-041 : 10 000 lignes traitées en file d'attente
 * sans bloquer l'interface. En test, QUEUE_CONNECTION=sync exécute le job
 * immédiatement dans le même processus — ce test vérifie donc surtout que
 * le traitement va bien à son terme et reste correct à cette échelle,
 * pas la latence réelle d'un worker Horizon (hors de portée d'un test Pest).
 */
it('traite un import de 10 000 lignes jusqu\'au bout', function (): void {
    Storage::fake('local');
    [, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $csv = "Prénom,Nom,E-mail\n";

    for ($i = 0; $i < 10000; $i++) {
        $csv .= "Prenom{$i},Nom{$i},contact{$i}@example.org\n";
    }

    $path = tempnam(sys_get_temp_dir(), 'contact-import-volume');
    file_put_contents($path, $csv);
    $upload = new UploadedFile($path, 'contacts.csv', 'text/csv', null, true);

    $this->actingAs($admin)->post('/contact-imports', ['file' => $upload]);
    $import = ContactImport::query()->firstOrFail();

    $this->actingAs($admin)->post(route('contact-imports.confirm-mapping', $import), [
        'mapping' => ['Prénom' => 'first_name', 'Nom' => 'last_name', 'E-mail' => 'email'],
        'duplicate_strategy' => 'merge',
    ]);

    $import->refresh();
    expect($import->status->value)->toBe('completed');
    expect($import->total_rows)->toBe(10000);
    expect($import->accepted_count)->toBe(10000);
    expect($import->rejected_count)->toBe(0);
    expect(Contact::query()->count())->toBe(10000);
});
