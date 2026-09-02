<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\ContactImportRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactImportRow extends Model
{
    /** @use HasFactory<ContactImportRowFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'contact_import_id',
        'contact_id',
        'row_number',
        'raw_data',
        'status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'row_number' => 'integer',
            'status' => ContactImportRowStatus::class,
        ];
    }

    protected static function newFactory(): ContactImportRowFactory
    {
        return ContactImportRowFactory::new();
    }

    /**
     * @return BelongsTo<ContactImport, $this>
     */
    public function contactImport(): BelongsTo
    {
        return $this->belongsTo(ContactImport::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
