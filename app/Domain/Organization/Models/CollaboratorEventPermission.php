<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\CollaboratorEventPermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L'événement n'est référencé que par event_id, jamais par une relation
 * Eloquent : Domain/Organization ne dépend pas des modèles de Domain/Event
 * (section 3 du CLAUDE.md).
 */
final class CollaboratorEventPermission extends Model
{
    /** @use HasFactory<CollaboratorEventPermissionFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'collaborator_id',
        'event_id',
        'permission',
    ];

    protected function casts(): array
    {
        return [
            'permission' => CollaboratorPermission::class,
        ];
    }

    protected static function newFactory(): CollaboratorEventPermissionFactory
    {
        return CollaboratorEventPermissionFactory::new();
    }

    /**
     * @return BelongsTo<Collaborator, $this>
     */
    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }
}
