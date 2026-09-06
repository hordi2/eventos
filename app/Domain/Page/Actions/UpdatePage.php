<?php

declare(strict_types=1);

namespace App\Domain\Page\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UpdatePage
{
    /**
     * @param  list<array{time: string, title: string, description: ?string}>  $programItems
     * @param  list<array{question: string, answer: string}>  $faqItems
     */
    public function handle(
        Organization $organization,
        int $eventId,
        ?string $metaDescription,
        array $programItems,
        array $faqItems,
        User $user,
    ): Page {
        Gate::forUser($user)->authorize('updateEvents', $organization);

        return Page::query()->updateOrCreate(
            ['event_id' => $eventId],
            [
                'organization_id' => $organization->id,
                'meta_description' => $metaDescription,
                'program_items' => $programItems,
                'faq_items' => $faqItems,
            ],
        );
    }
}
