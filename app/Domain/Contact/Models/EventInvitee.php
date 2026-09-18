<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\EventInviteeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un contact de l'organisation inscrit sur la liste d'invités d'un
 * événement (UC-03). event_id est une simple colonne : Domain/Contact ne
 * dépend pas de Domain/Event.
 *
 * companions_allowed nul signifie « illimité », dans la limite de la
 * plateforme (CompanionAllowance).
 */
final class EventInvitee extends Model
{
    /** @use HasFactory<EventInviteeFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'contact_id',
        'contact_import_id',
        'group_key',
        'companions_allowed',
        'cc_email',
    ];

    protected function casts(): array
    {
        return [
            'companions_allowed' => 'integer',
        ];
    }

    protected static function newFactory(): EventInviteeFactory
    {
        return EventInviteeFactory::new();
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
