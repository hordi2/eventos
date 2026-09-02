<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\ReserveFieldOptionCapacity;
use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Capacity\Actions\GetRemainingCapacity;
use App\Support\Capacity\Data\ReservationOutcome;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);
});

/**
 * Chaque étape de la chaîne événement → formulaire → version → champ →
 * option doit explicitement partager la même organisation : les factories
 * imbriquées (Form::factory()->event_id => Event::factory()...) en créeraient
 * sinon une nouvelle à chaque niveau, rejetée par la row-level security dès
 * qu'elle diffère de l'organisation courante.
 */
function makePersistedFieldOption(Organization $organization, ?int $quota): FieldOption
{
    $event = Event::factory()->for($organization)->create();
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::factory()->create([
        'organization_id' => $organization->id,
        'form_id' => $form->id,
    ]);
    $field = FormField::factory()->create([
        'organization_id' => $organization->id,
        'form_version_id' => $version->id,
    ]);

    return FieldOption::factory()->create([
        'organization_id' => $organization->id,
        'form_field_id' => $field->id,
        'quota' => $quota,
    ]);
}

it('réserve une place sur le quota d\'une option tant qu\'il en reste', function (): void {
    $option = makePersistedFieldOption($this->organization, quota: 2);

    $result = app(ReserveFieldOptionCapacity::class)->handle($option, (string) Str::uuid());

    expect($result->outcome)->toBe(ReservationOutcome::Accepted);
});

it('refuse sans liste d\'attente quand le quota d\'une option est atteint', function (): void {
    $option = makePersistedFieldOption($this->organization, quota: 1);
    $action = app(ReserveFieldOptionCapacity::class);

    $action->handle($option, (string) Str::uuid());
    $second = $action->handle($option, (string) Str::uuid());

    expect($second->outcome)->toBe(ReservationOutcome::Rejected);
});

it('expose la capacité restante pour griser une option complète', function (): void {
    $option = makePersistedFieldOption($this->organization, quota: 2);

    app(ReserveFieldOptionCapacity::class)->handle($option, (string) Str::uuid());

    $remaining = app(GetRemainingCapacity::class)->handle('form_field_option', (string) $option->id, $option->quota);

    expect($remaining)->toBe(1);
    expect(app(GetRemainingCapacity::class)->isFull('form_field_option', (string) $option->id, $option->quota))->toBeFalse();
});

it('une option sans quota n\'est jamais complète', function (): void {
    $option = makePersistedFieldOption($this->organization, quota: null);

    $result = app(ReserveFieldOptionCapacity::class)->handle($option, (string) Str::uuid());

    expect($result->outcome)->toBe(ReservationOutcome::Accepted);
    expect(app(GetRemainingCapacity::class)->handle('form_field_option', (string) $option->id, null))->toBeNull();
});
