<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => HouseholdType::class,
        ];
    }

    protected static function newFactory(): HouseholdFactory
    {
        return HouseholdFactory::new();
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
