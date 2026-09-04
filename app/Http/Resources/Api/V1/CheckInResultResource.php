<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\CheckIn\Data\CheckInResultData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CheckInResultData $resource
 */
final class CheckInResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'device_local_id' => $this->resource->deviceLocalId,
            'check_in_id' => $this->resource->checkInId,
            'status' => $this->resource->status->value,
        ];
    }
}
