<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\FormRequiresPaidPlanException;
use App\Domain\Form\InvalidFormVersionTransitionException;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Organization\Actions\GetEffectivePlan;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

final class PublishFormVersion
{
    public function __construct(
        private readonly GetEffectivePlan $getEffectivePlan,
    ) {}

    public function handle(Form $form, User $publisher): Form
    {
        Gate::forUser($publisher)->authorize('update', $form);

        $version = $form->latestVersion();

        if ($version === null || $version->status !== FormVersionStatus::Draft) {
            throw InvalidFormVersionTransitionException::cannotPublish($version?->status->value ?? 'aucune version');
        }

        $this->ensurePlanAllowsFields($form, $version);

        $previouslyPublished = $form->currentVersion;
        $previouslyPublished?->update(['status' => FormVersionStatus::Archived]);

        $version->update(['status' => FormVersionStatus::Published, 'published_at' => CarbonImmutable::now()]);
        $form->update(['current_version_id' => $version->id]);

        return $form->refresh();
    }

    /**
     * Questions avancées (FieldType::isPremium) : libres d'essai, publiables
     * seulement sur un plan payant. Vérifié ici, au seul moment où un invité
     * peut les voir.
     */
    private function ensurePlanAllowsFields(Form $form, FormVersion $version): void
    {
        $organization = Organization::query()->findOrFail($form->organization_id);

        if ($this->getEffectivePlan->handle($organization) !== PlanTier::Free) {
            return;
        }

        $premiumLabels = $version->fields()->get()
            ->filter(fn (FormField $field): bool => $field->type->isPremium())
            ->map(fn (FormField $field): string => $field->label)
            ->values()
            ->all();

        if ($premiumLabels !== []) {
            throw FormRequiresPaidPlanException::forFields($premiumLabels);
        }
    }
}
