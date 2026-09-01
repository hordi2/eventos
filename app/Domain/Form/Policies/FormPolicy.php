<?php

declare(strict_types=1);

namespace App\Domain\Form\Policies;

use App\Domain\Form\Models\Form;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

final class FormPolicy
{
    public function create(User $user, Organization $organization): bool
    {
        return $user->can('createEvents', $organization);
    }

    public function update(User $user, Form $form): bool
    {
        return $user->can('updateEvents', $form->organization);
    }

    public function delete(User $user, Form $form): bool
    {
        return $user->can('deleteEvents', $form->organization);
    }
}
