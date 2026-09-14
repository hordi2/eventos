<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\EventChecklistMarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Étape de la liste de contrôle cochée à la main par l'organisateur. Une
 * étape qu'Itaza constate seul (formulaire publié, plan de table créé...)
 * n'a pas besoin de ligne ici : voir GetEventChecklist.
 *
 * @property CarbonImmutable|null $marked_at
 */
final class EventChecklistMark extends Model
{
    /** @use HasFactory<EventChecklistMarkFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'step',
        'marked_by',
        'marked_at',
    ];

    protected function casts(): array
    {
        return [
            'step' => EventChecklistStep::class,
            'marked_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): EventChecklistMarkFactory
    {
        return EventChecklistMarkFactory::new();
    }
}
