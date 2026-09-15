<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationRevision;
use Carbon\CarbonImmutable;

/**
 * Capture l'état complet (identité, réponses, accompagnants et leurs
 * réponses) d'une Registration juste avant une modification (T-033 :
 * « historisation de la version précédente »).
 */
final class SnapshotRegistration
{
    public function handle(Registration $registration): RegistrationRevision
    {
        $registration->loadMissing(['answers.formField', 'companions', 'allAnswers.formField']);

        $snapshot = [
            'identity' => [
                'email' => $registration->email,
                'first_name' => $registration->first_name,
                'last_name' => $registration->last_name,
                'phone' => $registration->phone_e164,
            ],
            'answers' => $registration->answers->mapWithKeys(
                fn ($answer) => [$answer->formField->key => $answer->value],
            )->all(),
            'companions' => $registration->companions->map(fn (Attendee $companion): array => [
                'first_name' => $companion->first_name,
                'last_name' => $companion->last_name,
                'answers' => $registration->allAnswers
                    ->where('attendee_id', $companion->id)
                    ->mapWithKeys(fn (RegistrationAnswer $answer): array => [$answer->formField->key => $answer->value])
                    ->all(),
            ])->values()->all(),
        ];

        return RegistrationRevision::query()->create([
            'organization_id' => $registration->organization_id,
            'registration_id' => $registration->id,
            'snapshot' => $snapshot,
            'created_at' => CarbonImmutable::now(),
        ]);
    }
}
