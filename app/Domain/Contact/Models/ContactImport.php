<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

use App\Models\User;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\ContactImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ContactImport extends Model
{
    /** @use HasFactory<ContactImportFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'created_by',
        'original_filename',
        'file_path',
        'headers',
        'column_mapping',
        'duplicate_strategy',
        'status',
        'total_rows',
        'accepted_count',
        'duplicate_count',
        'rejected_count',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'column_mapping' => 'array',
            'duplicate_strategy' => DuplicateStrategy::class,
            'status' => ContactImportStatus::class,
            'total_rows' => 'integer',
            'accepted_count' => 'integer',
            'duplicate_count' => 'integer',
            'rejected_count' => 'integer',
            'processed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ContactImportFactory
    {
        return ContactImportFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ContactImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ContactImportRow::class);
    }
}
