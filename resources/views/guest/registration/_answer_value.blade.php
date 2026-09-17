{{-- Valeur d'une réponse du brouillon, lisible : libellé d'option, Oui/Non, adresse sur une ligne. --}}
@switch($field->type->value)
    @case('single_choice')
    @case('meal_choice')
    @case('dropdown')
        {{ $field->options->firstWhere('value', $raw)?->label ?? $raw }}
        @break
    @case('multiple_choice')
        {{ $field->options->whereIn('value', (array) $raw)->pluck('label')->implode(', ') }}
        @break
    @case('yes_no')
        {{ $raw === '1' || $raw === 1 ? 'Oui' : 'Non' }}
        @break
    @case('consent')
        Accepté
        @break
    @case('postal_address')
        {{ \App\Domain\Form\Support\PostalAddress::format((array) $raw) }}
        @break
    @case('sub_events')
        {{ app(\App\Domain\Form\Actions\FormatFieldAnswerForExport::class)->handle($field, (array) $raw) }}
        @break
    @case('donation')
        {{ \App\Domain\Form\Support\DonationAnswer::amountFrom($field, $raw)?->format() ?? 'Pas de don' }}
        @break
    @case('file_upload')
        @php($uploaded = is_string($raw) ? (($uploadedFiles ?? [])[$raw] ?? null) : null)
        {{ $uploaded !== null ? "{$uploaded['name']} · {$uploaded['status']}" : 'Fichier à envoyer de nouveau' }}
        @break
    @case('donor_info')
        {{ \App\Domain\Form\Support\DonorInfoAnswer::format(\App\Domain\Form\Support\DonorInfoAnswer::normalize($raw)) }}
        @break
    @default
        {{ is_array($raw) ? implode(', ', $raw) : $raw }}
@endswitch
