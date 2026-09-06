<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Event\Models\Event;
use App\Models\User;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'allow_editor_financial_access',
        'logo_path',
        'primary_color',
        'plan',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'subscription_current_period_end',
        'payment_failed_at',
        'dunning_stage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_editor_financial_access' => 'boolean',
            'plan' => PlanTier::class,
            'subscription_current_period_end' => 'immutable_datetime',
            'payment_failed_at' => 'immutable_datetime',
            'dunning_stage' => 'integer',
        ];
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
