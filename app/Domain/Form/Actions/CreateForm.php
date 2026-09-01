<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CreateForm
{
    public function __construct(
        private readonly WriteFormFields $writeFormFields,
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

        $form = Form::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'created_by' => $creator->id,
            'name' => $data['name'],
        ]);

        $version = FormVersion::query()->create([
            'organization_id' => $organization->id,
            'form_id' => $form->id,
            'version_number' => 1,
            'status' => FormVersionStatus::Draft,
        ]);

        $this->writeFormFields->handle($version, $data['fields'] ?? []);

        return $form->refresh();
    }
}
