<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\CheckIn\Models\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CheckIn $resource
 */
final class CheckInResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'device_local_id' => $this->resource->device_local_id,
            'attendee_id' => $this->resource->attendee_id,
            'ticket_id' => $this->resource->ticket_id,
            'direction' => $this->resource->direction->value,
            'status' => $this->resource->status->value,
            'recorded_at' => $this->resource->recorded_at->toIso8601String(),
        ];
    }
}
