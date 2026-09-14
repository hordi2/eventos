<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Settings;

use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\Membership;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Création (POST, avec e-mail) et modification (PATCH, permissions seules)
 * d'un collaborateur. L'autorisation est portée par la route
 * (can-organization:inviteMembers).
 */
final class SaveCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->requireId();

        return [
            'email' => $this->isCreating() ? ['required', 'string', 'email', 'max:255'] : ['prohibited'],
            'permissions' => ['required', 'array'],
            'permissions.*.event_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('events', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'permissions.*.permission' => ['required', Rule::enum(CollaboratorPermission::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.*.event_id.exists' => "Cet événement n'existe pas dans votre organisation.",
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $permissions = $this->parsePermissions($this->input('permissions'));

                if (! in_array(true, array_map(fn (CollaboratorPermission $permission): bool => $permission !== CollaboratorPermission::None, $permissions), true)) {
                    $validator->errors()->add('permissions', 'Donnez accès à au moins un événement.');

                    return;
                }

                if ($this->isCreating()) {
                    $this->ensureEmailIsNew($validator, (string) $this->input('email'));
                }
            },
        ];
    }

    /**
     * @return array<int, CollaboratorPermission> permission par id d'événement
     */
    public function permissionsByEvent(): array
    {
        return $this->parsePermissions($this->validated('permissions'));
    }

    private function isCreating(): bool
    {
        return $this->isMethod('post');
    }

    private function ensureEmailIsNew(Validator $validator, string $email): void
    {
        if (Collaborator::query()->where('email', $email)->exists()) {
            $validator->errors()->add('email', 'Cette personne a déjà accès à vos événements : modifiez ses permissions dans la liste.');

            return;
        }

        $userId = User::query()->where('email', $email)->value('id');

        $isMember = $userId !== null && Membership::query()
            ->where('organization_id', app(CurrentOrganization::class)->requireId())
            ->where('user_id', $userId)
            ->exists();

        if ($isMember) {
            $validator->errors()->add('email', 'Cette personne est déjà membre de votre organisation.');
        }
    }

    /**
     * @return array<int, CollaboratorPermission>
     */
    private function parsePermissions(mixed $rows): array
    {
        // mixed : valeur brute de la requête, validée par rules() avant tout appel.
        $permissions = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $permissions[(int) $row['event_id']] = CollaboratorPermission::from((string) $row['permission']);
            }
        }

        return $permissions;
    }
}
