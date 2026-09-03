<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Models\User;
use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property list<array<string, mixed>> $blocks
 */
final class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'created_by',
        'name',
        'subject',
        'blocks',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }

    protected static function newFactory(): EmailTemplateFactory
    {
        return EmailTemplateFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
