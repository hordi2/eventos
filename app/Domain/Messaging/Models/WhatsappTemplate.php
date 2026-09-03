<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Models\User;
use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\WhatsappTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property list<string> $variable_mapping
 */
final class WhatsappTemplate extends Model
{
    /** @use HasFactory<WhatsappTemplateFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'created_by',
        'name',
        'provider_template_sid',
        'language',
        'category',
        'variable_mapping',
    ];

    protected function casts(): array
    {
        return [
            'variable_mapping' => 'array',
        ];
    }

    protected static function newFactory(): WhatsappTemplateFactory
    {
        return WhatsappTemplateFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
