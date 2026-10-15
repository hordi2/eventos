<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\EventPollVoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Le vote d'un participant pour un sondage (D9). Une ligne par (sondage,
 * inscription) : la contrainte unique porte le choix unique, et son
 * remplacement (changer d'avis tant que le sondage est ouvert) se fait en
 * mettant à jour cette même ligne plutôt qu'en en créer une autre.
 */
final class EventPollVote extends Model
{
    /** @use HasFactory<EventPollVoteFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_poll_id',
        'event_poll_option_id',
        'registration_id',
    ];

    protected static function newFactory(): EventPollVoteFactory
    {
        return EventPollVoteFactory::new();
    }
}
