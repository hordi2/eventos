<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\RegistrationDraft;

final class SaveRegistrationDraft
{
    /**
     * @param  array<string, mixed>|null  $identity
     * @param  array<string, mixed>|null  $answers
     */
    public function handle(RegistrationDraft $draft, ?array $identity = null, ?array $answers = null): RegistrationDraft
    {
        if ($identity !== null) {
            $draft->identity = [...($draft->identity ?? []), ...$identity];
        }

        if ($answers !== null) {
            $draft->answers = [...($draft->answers ?? []), ...$answers];
        }

        $draft->save();

        return $draft;
    }
}
