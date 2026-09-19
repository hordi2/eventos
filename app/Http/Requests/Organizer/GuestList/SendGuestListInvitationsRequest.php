<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\GuestList;

use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Organization\Models\Organization;
use App\Support\GuestList\PlanInvitationSend;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Envoi groupé des invitations, et son aperçu (même forme, sans send_key).
 * Le droit d'écrire aux invités est vérifié avant toute validation : un
 * membre qui ne l'a pas reçoit un refus, pas un message d'erreur de
 * formulaire. L'accord d'envoi est exigé pour envoyer, pas pour l'aperçu.
 */
final class SendGuestListInvitationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('sendCommunications', Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId()));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->requireId();
        $templates = $this->input('channel') === MessageChannel::Whatsapp->value ? 'whatsapp_templates' : 'email_templates';

        return [
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'template_id' => ['required', 'integer', Rule::exists($templates, 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'audience' => ['required', Rule::in([PlanInvitationSend::AUDIENCE_ALL, PlanInvitationSend::AUDIENCE_UNANSWERED, PlanInvitationSend::AUDIENCE_SELECTED])],
            'mode' => ['required', Rule::in([PlanInvitationSend::MODE_PER_INVITEE, PlanInvitationSend::MODE_PER_GROUP])],
            'invitee_ids' => ['required_if:audience,'.PlanInvitationSend::AUDIENCE_SELECTED, 'array', 'max:5000'],
            'invitee_ids.*' => ['integer'],
            // Clé tirée par le navigateur : une double confirmation n'envoie rien deux fois.
            'send_key' => [$this->isMethod('post') ? 'required' : 'nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_id.required' => 'Choisissez le modèle de message à envoyer.',
            'template_id.exists' => "Ce modèle n'existe plus : choisissez-en un autre.",
            'invitee_ids.required_if' => 'Cochez les invités à qui envoyer le message.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $organization = Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());

            if ($this->isMethod('post') && $organization->sender_agreement_accepted_at === null) {
                $validator->errors()->add('agreement', "Acceptez d'abord l'accord d'envoi pour écrire à vos invités.");
            }
        }];
    }

    /**
     * @return list<int>
     */
    public function selectedIds(): array
    {
        return array_map(intval(...), (array) $this->validated('invitee_ids', []));
    }
}
