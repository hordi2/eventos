<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Event\Models\ProposalFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitProposalRequest extends FormRequest
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
            'proposer_name' => ['required', 'string', 'max:255'],
            'proposer_email' => ['required', 'email', 'max:255'],
            'proposer_role' => ['nullable', 'string', 'max:255'],
            'proposer_company' => ['nullable', 'string', 'max:255'],
            'proposer_bio' => ['nullable', 'string', 'max:2000'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:5000'],
            'format' => ['required', Rule::enum(ProposalFormat::class)],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proposer_name.required' => __('Indiquez votre nom.'),
            'proposer_email.required' => __('Indiquez votre adresse e-mail : la réponse vous y sera envoyée.'),
            'proposer_email.email' => __("Cette adresse e-mail n'est pas valide."),
            'title.required' => __('Donnez un titre à votre sujet.'),
            'summary.required' => __('Résumez votre sujet en quelques lignes.'),
            'format.required' => __('Choisissez la forme de votre intervention.'),
            'duration_minutes.min' => __('La durée doit être d\'au moins 5 minutes.'),
            'duration_minutes.max' => __('La durée ne peut pas dépasser 8 heures.'),
        ];
    }
}
