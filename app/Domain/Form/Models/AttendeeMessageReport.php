<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Models\User;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\AttendeeMessageReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un message signalé à l'organisateur par un participant (D8).
 */
final class AttendeeMessageReport extends Model
{
    /** @use HasFactory<AttendeeMessageReportFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'attendee_message_id',
        'reporter_registration_id',
        'reason',
        'status',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendeeReportStatus::class,
            'handled_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): AttendeeMessageReportFactory
    {
        return AttendeeMessageReportFactory::new();
    }

    /**
     * @return BelongsTo<AttendeeMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AttendeeMessage::class, 'attendee_message_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'reporter_registration_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
