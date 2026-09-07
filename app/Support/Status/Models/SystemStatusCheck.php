<?php

declare(strict_types=1);

namespace App\Support\Status\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $is_healthy
 * @property CarbonImmutable $checked_at
 * @property ArrayObject<string, bool> $components
 */
final class SystemStatusCheck extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['checked_at', 'is_healthy', 'components'];

    protected function casts(): array
    {
        return [
            'checked_at' => 'immutable_datetime',
            'is_healthy' => 'boolean',
            'components' => AsArrayObject::class,
        ];
    }
}
