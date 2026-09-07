<?php

declare(strict_types=1);

namespace App\Domain\Community\Actions;

use App\Domain\Community\Models\CommunityCategory;
use App\Domain\Community\Models\CommunityTopic;
use App\Models\User;

final class CreateCommunityTopic
{
    public function handle(User $author, CommunityCategory $category, string $title, string $body): CommunityTopic
    {
        return CommunityTopic::query()->create([
            'user_id' => $author->id,
            'category' => $category,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
