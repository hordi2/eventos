<?php

declare(strict_types=1);

namespace App\Domain\Page\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\GuestBookMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mot laissé par un invité dans le livre d'or (D1).
 */
final class GuestBookMessage extends Model
{
    /** @use HasFactory<GuestBookMessageFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'author_name',
        'message',
        'is_published',
        'author_ip',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function newFactory(): GuestBookMessageFactory
    {
        return GuestBookMessageFactory::new();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
