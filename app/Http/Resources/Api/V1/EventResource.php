<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Event\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Event $resource
 */
final class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('venue');

        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'title' => $this->resource->title,
            'subtitle' => $this->resource->subtitle,
            'type' => $this->resource->type->value,
            'status' => $this->resource->status->value,
            'lifecycle_status' => $this->resource->computedStatus()->value,
            'start_at' => $this->resource->start_at->toIso8601String(),
            'end_at' => $this->resource->end_at->toIso8601String(),
            'timezone' => $this->resource->timezone,
            'is_online' => (bool) $this->resource->is_online,
            'capacity' => $this->resource->capacity,
            'registration_opens_at' => $this->resource->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $this->resource->registration_closes_at?->toIso8601String(),
            'venue' => $this->resource->venue === null ? null : [
                'name' => $this->resource->venue->name,
                'address' => $this->resource->venue->address,
            ],
        ];
    }
}
