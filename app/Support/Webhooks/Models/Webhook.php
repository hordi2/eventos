<?php

declare(strict_types=1);

namespace App\Support\Webhooks\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property list<string> $subscribed_events
 */
final class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'url',
        'secret',
        'subscribed_events',
        'is_active',
        'last_delivery_at',
        'last_delivery_status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'subscribed_events' => 'array',
            'is_active' => 'boolean',
            'last_delivery_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): WebhookFactory
    {
        return WebhookFactory::new();
    }

    public function isSubscribedTo(string $eventName): bool
    {
        return $this->is_active && in_array($eventName, $this->subscribed_events, true);
    }
}
