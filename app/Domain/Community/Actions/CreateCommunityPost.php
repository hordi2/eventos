<?php

declare(strict_types=1);

namespace App\Domain\Community\Actions;

use App\Domain\Community\Models\CommunityPost;
use App\Domain\Community\Models\CommunityTopic;
use App\Models\User;

final class CreateCommunityPost
{
    public function handle(CommunityTopic $topic, User $author, string $body): CommunityPost
    {
        return CommunityPost::query()->create([
            'community_topic_id' => $topic->id,
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }
}
