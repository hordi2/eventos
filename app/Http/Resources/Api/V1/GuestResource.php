<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\CheckIn\Data\GuestData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property GuestData $resource
 */
final class GuestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'guest_type' => $this->resource->guestType,
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'checked_in' => $this->resource->checkedIn,
            'checked_in_at' => $this->resource->checkedInAt,
        ];
    }
}
