<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\InvalidFormVersionTransitionException;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersionStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

final class PublishFormVersion
{
    public function handle(Form $form, User $publisher): Form
    {
        Gate::forUser($publisher)->authorize('update', $form);

        $version = $form->latestVersion();

        if ($version === null || $version->status !== FormVersionStatus::Draft) {
            throw InvalidFormVersionTransitionException::cannotPublish($version?->status->value ?? 'aucune version');
        }

        $previouslyPublished = $form->currentVersion;
        $previouslyPublished?->update(['status' => FormVersionStatus::Archived]);

        $version->update(['status' => FormVersionStatus::Published, 'published_at' => CarbonImmutable::now()]);
        $form->update(['current_version_id' => $version->id]);

        return $form->refresh();
    }
}
