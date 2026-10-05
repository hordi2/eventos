<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Factories\EventTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un modèle d'événement publié dans la bibliothèque communautaire (D11).
 *
 * N'utilise pas BelongsToOrganization : la bibliothèque est commune à toutes
 * les organisations, c'est son objet même. L'organisation dit qui a publié.
 *
 * @property array<string, mixed> $payload
 */
final class EventTemplate extends Model
{
    /** @use HasFactory<EventTemplateFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'published_by',
        'name',
        'slug',
        'summary',
        'category',
        'payload',
        'is_published',
        'uses_count',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'category' => EventType::class,
            'is_published' => 'boolean',
            'uses_count' => 'integer',
        ];
    }

    protected static function newFactory(): EventTemplateFactory
    {
        return EventTemplateFactory::new();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
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
