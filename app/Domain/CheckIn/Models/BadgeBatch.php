<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

use App\Models\User;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\BadgeBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * event_id reste une simple colonne (section 3 du CLAUDE.md).
 */
final class BadgeBatch extends Model
{
    /** @use HasFactory<BadgeBatchFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'status',
        'guest_count',
        'file_path',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BadgeBatchStatus::class,
            'completed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): BadgeBatchFactory
    {
        return BadgeBatchFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
