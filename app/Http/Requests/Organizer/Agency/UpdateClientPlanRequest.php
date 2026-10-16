<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Agency;

use App\Domain\Organization\Models\PlanTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateClientPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::enum(PlanTier::class)],
        ];
    }

    public function plan(): PlanTier
    {
        return PlanTier::from($this->validated('plan'));
    }
}
