<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Form\Support\EventForms;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateForm
{
    public function __construct(
        private readonly WriteFormFields $writeFormFields,
        private readonly WriteConditionalRules $writeConditionalRules,
        private readonly EventForms $eventForms,
    ) {}

    /**
     * $eventId n'est jamais typé Event : Domain/Form ne dépend d'aucun
     * modèle d'un autre module de Domain/ (section 3 du CLAUDE.md, vérifié
     * par un test d'architecture).
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, int $eventId, User $creator, array $data): Form
    {
        Gate::forUser($creator)->authorize('create', [Form::class, $organization]);

        // Une règle refusée ne doit jamais laisser un formulaire à moitié créé.
        return DB::transaction(function () use ($organization, $eventId, $creator, $data): Form {
            $form = Form::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $eventId,
                'created_by' => $creator->id,
                'name' => $data['name'],
                'slug' => $this->eventForms->uniqueSlug($eventId, $data['name']),
                // Le premier formulaire répond au lien de l'événement.
                'is_default' => ! $this->eventForms->hasDefault($eventId),
            ]);

            $version = FormVersion::query()->create([
                'organization_id' => $organization->id,
                'form_id' => $form->id,
                'version_number' => 1,
                'status' => FormVersionStatus::Draft,
            ]);

            $this->writeFormFields->handle($version, $data['fields'] ?? []);
            $this->writeConditionalRules->handle($version, $data['rules'] ?? []);

            return $form->refresh();
        });
    }
}
