<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Models\RegistrationFile;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Jobs\ScanRegistrationFileJob;
use App\Mail\RejectedRegistrationFileMail;
use App\Models\User;
use App\Support\Antivirus\FileScanner;
use App\Support\Antivirus\ScannerUnavailableException;
use App\Support\Gdpr\AnonymizeContact;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Fakes\FakeFileScanner;
use Tests\TestCase;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256', 'filesystems.registration_files_disk' => 'local']);
    Storage::fake('local');

    $this->scanner = new FakeFileScanner;
    app()->instance(FileScanner::class, $this->scanner);
});

/**
 * Événement d'une organisation au plan payant (question avancée) dont le
 * formulaire demande un justificatif PDF de 2 Mo au plus.
 *
 * @return array{organization: Organization, event: Event, admin: User, base: string}
 */
function fileGuestEvent(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(
        [[
            'key' => 'justificatif',
            'type' => 'file_upload',
            'label' => 'Votre justificatif',
            'is_required' => true,
            'config' => ['show_if' => 'always', 'file_types' => ['pdf'], 'max_size_mb' => 2],
        ]],
        ['type' => EventType::Conference, 'allow_guest_edit' => true],
        ['plan' => PlanTier::PersonalEssential],
    );

    $admin = User::factory()->create();
    Membership::factory()->for($organization)->for($admin)->create(['role' => MembershipRole::Admin]);

    return ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'base' => "/r/{$organization->slug}/{$event->slug}"];
}

function startFileRegistration(TestCase $test, Event $event, string $base): string
{
    $test->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $test->post("{$base}/{$token}/identite", ['email' => 'marie@example.com', 'first_name' => 'Marie', 'last_name' => 'Lusala']);

    return $token;
}

/**
 * Inscription complète de Marie avec un justificatif PDF.
 */
function registerWithFile(TestCase $test, Event $event, string $base): string
{
    $token = startFileRegistration($test, $event, $base);
    $test->post("{$base}/{$token}/reponses", ['_fichiers' => ['justificatif' => UploadedFile::fake()->create('justificatif.pdf', 120, 'application/pdf')]])
        ->assertSessionHasNoErrors();
    $test->post("{$base}/{$token}/recap");

    return $token;
}

function uploadedFile(): RegistrationFile
{
    return RegistrationFile::withoutGlobalScopes()->latest('id')->firstOrFail();
}

function marieFileRegistration(): Registration
{
    return Registration::withoutGlobalScopes()->where('email', 'marie@example.com')->firstOrFail();
}

it('met le fichier en quarantaine, le déclare sain, puis le rattache à l\'inscription', function (): void {
    ['event' => $event, 'base' => $base] = fileGuestEvent();
    $token = startFileRegistration($this, $event, $base);

    $this->get("{$base}/{$token}/reponses")
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('name="_fichiers[justificatif]"', false)
        ->assertSee('accept=".pdf"', false);

    $this->post("{$base}/{$token}/reponses", ['_fichiers' => ['justificatif' => UploadedFile::fake()->create('Justificatif Marie.pdf', 120, 'application/pdf')]])
        ->assertSessionHasNoErrors()
        ->assertRedirect("{$base}/{$token}/recap");

    $file = uploadedFile();
    expect($file->scan_status)->toBe(FileScanStatus::Clean);
    expect($file->original_name)->toBe('Justificatif Marie.pdf');
    expect($file->path)->toStartWith("registration-files/{$event->organization_id}/");
    Storage::disk('local')->assertExists($file->path);
    expect(Storage::disk('local')->allFiles(RegistrationFile::QUARANTINE_DIRECTORY))->toBe([]);

    $this->get("{$base}/{$token}/recap")->assertSee('Justificatif Marie.pdf · Sain');
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    $registration = marieFileRegistration();
    expect(uploadedFile()->registration_id)->toBe($registration->id);
    expect(RegistrationAnswer::withoutGlobalScopes()->where('registration_id', $registration->id)->firstOrFail()->value)
        ->toEqual(['token' => $file->token, 'name' => 'Justificatif Marie.pdf', 'size' => $file->size_bytes]);
});

it('refuse un autre format, un fichier trop lourd ou une réponse sans fichier', function (): void {
    ['event' => $event, 'base' => $base] = fileGuestEvent();
    $token = startFileRegistration($this, $event, $base);

    $this->post("{$base}/{$token}/reponses", ['_fichiers' => ['justificatif' => UploadedFile::fake()->create('photo.png', 50, 'image/png')]])
        ->assertSessionHasErrors(['justificatif' => "Ce type de fichier n'est pas accepté ici. Formats possibles : PDF."]);

    $this->post("{$base}/{$token}/reponses", ['_fichiers' => ['justificatif' => UploadedFile::fake()->create('gros.pdf', 3000, 'application/pdf')]])
        ->assertSessionHasErrors(['justificatif' => 'Ce fichier dépasse 2 Mo.']);

    $this->post("{$base}/{$token}/reponses", [])->assertSessionHasErrors('justificatif');

    expect(RegistrationFile::withoutGlobalScopes()->count())->toBe(0);
});

it('refuse un fichier déclaré infecté avant la fin de la page et le supprime du disque', function (): void {
    ['event' => $event, 'base' => $base] = fileGuestEvent();
    $token = startFileRegistration($this, $event, $base);
    $this->scanner->infection = 'Eicar-Test-Signature';

    $this->from("{$base}/{$token}/reponses")
        ->post("{$base}/{$token}/reponses", ['_fichiers' => ['justificatif' => UploadedFile::fake()->create('facture.pdf', 40, 'application/pdf')]])
        ->assertSessionHasErrors(['justificatif' => "Ce fichier a été refusé par l'analyse antivirus : envoyez-en un autre."]);

    $file = uploadedFile();
    expect($file->scan_status)->toBe(FileScanStatus::Infected);
    expect($file->scan_signature)->toBe('Eicar-Test-Signature');
    Storage::disk('local')->assertMissing($file->path);

    $this->get("{$base}/{$token}/reponses")
        ->assertSee('facture.pdf')
        ->assertSee('Refusé : virus détecté')
        ->assertDontSee('value="'.$file->token.'"', false);
});

it('prévient l\'invité quand le verdict arrive après l\'inscription et le laisse envoyer un autre fichier', function (): void {
    Mail::fake();
    Queue::fake();
    ['organization' => $organization, 'event' => $event, 'base' => $base] = fileGuestEvent();
    $token = startFileRegistration($this, $event, $base);
    $this->post("{$base}/{$token}/reponses", ['_fichiers' => ['justificatif' => UploadedFile::fake()->create('justificatif.pdf', 120, 'application/pdf')]]);

    // Analyse en arrière-plan : l'invité confirme sans attendre le verdict.
    $this->get("{$base}/{$token}/recap")->assertSee('justificatif.pdf · Analyse en cours');
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    $registration = marieFileRegistration();
    $file = uploadedFile();
    $this->scanner->infection = 'Eicar-Test-Signature';
    app()->call([new ScanRegistrationFileJob($file->id, $organization->id), 'handle']);

    Mail::assertQueued(RejectedRegistrationFileMail::class, fn (RejectedRegistrationFileMail $mail): bool => $mail->hasTo('marie@example.com')
        && $mail->fileName === 'justificatif.pdf'
        && $mail->editUrl !== null);

    $editUrl = URL::temporarySignedRoute('guest.registration.edit', now()->addDay(), [$organization->slug, $event->slug, $registration->id]);
    $this->get($editUrl)->assertOk()->assertSee('Refusé : virus détecté');

    $this->post($editUrl, [
        'email' => 'marie@example.com',
        'first_name' => 'Marie',
        'last_name' => 'Lusala',
        '_fichiers' => ['justificatif' => UploadedFile::fake()->create('nouveau.pdf', 80, 'application/pdf')],
    ])->assertOk();

    $replacement = RegistrationFile::withoutGlobalScopes()->where('original_name', 'nouveau.pdf')->firstOrFail();
    expect($replacement->registration_id)->toBe($registration->id);
    expect(RegistrationFile::withoutGlobalScopes()->findOrFail($file->id)->trashed())->toBeTrue();
    expect(RegistrationAnswer::withoutGlobalScopes()->where('registration_id', $registration->id)->firstOrFail()->value['token'])->toBe($replacement->token);
    Queue::assertPushed(ScanRegistrationFileJob::class, 2);
});

it('liste les fichiers reçus, réserve le téléchargement au droit d\'export et le journalise', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'base' => $base] = fileGuestEvent();
    registerWithFile($this, $event, $base);
    $file = uploadedFile();

    $viewer = User::factory()->create();
    Membership::factory()->for($organization)->for($viewer)->create(['role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)->get("/events/{$event->id}/files")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Events/Files')
        ->has('files', 1)
        ->where('files.0.guestName', 'Marie Lusala')
        ->where('files.0.fileName', 'justificatif.pdf')
        ->where('files.0.status', 'clean')
        ->where('files.0.downloadUrl', null));
    $this->actingAs($viewer)->get("/events/{$event->id}/files/{$file->id}/download")->assertForbidden();

    $this->actingAs($admin)->get("/events/{$event->id}/files")->assertInertia(fn ($page) => $page
        ->where('files.0.downloadUrl', route('events.files.download', [$event->id, $file->id]))
        ->where('eventNav.links.files', route('events.files.index', $event->id)));

    $this->actingAs($admin)->get("/events/{$event->id}/files/{$file->id}/download")->assertOk()->assertDownload('justificatif.pdf');

    expect(DB::table('audit_logs')->where('action', 'registration_file.downloaded')->where('subject_id', $file->id)->exists())->toBeTrue();
});

it('réessaie quand ClamAV est injoignable, puis laisse relancer l\'analyse d\'un fichier non analysé', function (): void {
    Queue::fake();
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'base' => $base] = fileGuestEvent();
    registerWithFile($this, $event, $base);
    $file = uploadedFile();
    $job = new ScanRegistrationFileJob($file->id, $organization->id);

    $this->scanner->unavailable = true;
    expect(fn () => app()->call([$job, 'handle']))->toThrow(ScannerUnavailableException::class);
    $job->failed(new ScannerUnavailableException('ClamAV injoignable'));
    expect(uploadedFile()->scan_status)->toBe(FileScanStatus::Failed);

    $this->actingAs($admin)->get("/events/{$event->id}/files/{$file->id}/download")->assertNotFound();
    $this->actingAs($admin)->get("/events/{$event->id}/files")->assertInertia(fn ($page) => $page
        ->where('files.0.statusLabel', 'Analyse impossible')
        ->where('files.0.rescanUrl', route('events.files.rescan', [$event->id, $file->id])));

    $this->actingAs($admin)->post("/events/{$event->id}/files/{$file->id}/rescan")->assertRedirect(route('events.files.index', $event->id));

    expect(uploadedFile()->scan_status)->toBe(FileScanStatus::Pending);
    Queue::assertPushed(ScanRegistrationFileJob::class, 2);
});

it('supprime le fichier joint à l\'effacement RGPD du contact', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'base' => $base] = fileGuestEvent();
    registerWithFile($this, $event, $base);
    $file = uploadedFile();
    $registration = marieFileRegistration();

    app(CurrentOrganization::class)->set($organization);
    app(AnonymizeContact::class)->handle(Contact::query()->findOrFail($registration->contact_id), $admin);

    expect(RegistrationFile::withoutGlobalScopes()->findOrFail($file->id)->trashed())->toBeTrue();
    Storage::disk('local')->assertMissing($file->path);
    expect(RegistrationAnswer::withoutGlobalScopes()->where('registration_id', $registration->id)->firstOrFail()->value)->toBe(['name' => 'Fichier supprimé']);
});

it('supprime 30 jours après l\'envoi un fichier jamais rattaché à une inscription', function (): void {
    ['organization' => $organization, 'event' => $event] = fileGuestEvent();
    app(CurrentOrganization::class)->set($organization);

    $abandoned = RegistrationFile::factory()->create(['organization_id' => $organization->id, 'event_id' => $event->id, 'path' => 'registration-files/quarantaine/abandonne.pdf']);
    $abandoned->forceFill(['created_at' => now()->subDays(31)])->save();
    $recent = RegistrationFile::factory()->create(['organization_id' => $organization->id, 'event_id' => $event->id, 'path' => 'registration-files/quarantaine/recent.pdf']);
    Storage::disk('local')->put($abandoned->path, 'contenu');
    Storage::disk('local')->put($recent->path, 'contenu');
    app(CurrentOrganization::class)->clear();

    $this->artisan('registration-files:purge-unclaimed')->assertSuccessful();

    // La commande rend la main sans organisation courante : la RLS masquerait les lignes.
    app(CurrentOrganization::class)->set($organization);
    expect(RegistrationFile::withoutGlobalScopes()->findOrFail($abandoned->id)->trashed())->toBeTrue();
    expect(RegistrationFile::withoutGlobalScopes()->findOrFail($recent->id)->trashed())->toBeFalse();
    Storage::disk('local')->assertMissing($abandoned->path);
    Storage::disk('local')->assertExists($recent->path);
});
