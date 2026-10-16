<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Settings;

use App\Domain\Organization\Models\MembershipRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMembershipRoleRequest extends FormRequest
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
            'role' => ['required', Rule::in(array_map(fn (MembershipRole $role): string => $role->value, MembershipRole::invitable()))],
        ];
    }

    public function role(): MembershipRole
    {
        return MembershipRole::from($this->validated('role'));
    }
}
