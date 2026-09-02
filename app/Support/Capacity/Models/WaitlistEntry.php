<?php

declare(strict_types=1);

namespace App\Support\Capacity\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'holder_type',
        'holder_id',
        'reservation_key',
        'quantity',
        'position',
        'status',
        'promoted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'position' => 'integer',
            'status' => WaitlistEntryStatus::class,
            'promoted_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): WaitlistEntryFactory
    {
        return WaitlistEntryFactory::new();
    }
}
