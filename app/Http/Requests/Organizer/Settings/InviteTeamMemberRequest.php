<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Settings;

use App\Domain\Organization\Models\MembershipRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_map(fn (MembershipRole $role): string => $role->value, MembershipRole::invitable()))],
        ];
    }

    public function role(): MembershipRole
    {
        return MembershipRole::from($this->validated('role'));
    }
}
