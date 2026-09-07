<?php

declare(strict_types=1);

namespace App\Domain\Community\Models;

use App\Models\User;
use Database\Factories\CommunityTopicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CommunityTopic extends Model
{
    /** @use HasFactory<CommunityTopicFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'category', 'title', 'body'];

    protected function casts(): array
    {
        return [
            'category' => CommunityCategory::class,
        ];
    }

    protected static function newFactory(): CommunityTopicFactory
    {
        return CommunityTopicFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CommunityPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class)->oldest();
    }
}
