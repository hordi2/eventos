<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\RegistrationDraft;
use Illuminate\Support\Str;

final class StartRegistrationDraft
{
    public function handle(int $organizationId, int $eventId, int $formVersionId): RegistrationDraft
    {
        return RegistrationDraft::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $eventId,
            'form_version_id' => $formVersionId,
            'resume_token' => Str::random(40),
        ]);
    }
}
