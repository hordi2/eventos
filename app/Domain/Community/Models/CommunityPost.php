<?php

declare(strict_types=1);

namespace App\Domain\Community\Models;

use App\Models\User;
use Database\Factories\CommunityPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CommunityPost extends Model
{
    /** @use HasFactory<CommunityPostFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['community_topic_id', 'user_id', 'body'];

    protected static function newFactory(): CommunityPostFactory
    {
        return CommunityPostFactory::new();
    }

    /**
     * @return BelongsTo<CommunityTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(CommunityTopic::class, 'community_topic_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
