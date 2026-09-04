<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Models\User;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * event_id reste une simple colonne (section 3 du CLAUDE.md).
 */
final class Export extends Model
{
    /** @use HasFactory<ExportFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'type',
        'status',
        'columns',
        'segment',
        'file_path',
        'row_count',
        'requested_by',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExportType::class,
            'status' => ExportStatus::class,
            'columns' => 'array',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ExportFactory
    {
        return ExportFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
