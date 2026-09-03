<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'color',
    ];

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag')->withPivot('created_at');
    }
}
