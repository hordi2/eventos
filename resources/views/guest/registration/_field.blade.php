@php
    $config = $field->config ?? [];
    // Réponse d'un accompagnant (T-032) : le champ est nommé
    // _companions[rang][answers][clé] ; sinon, simplement la clé du champ.
    $namePrefix = $namePrefix ?? null;
    $inputName = $namePrefix !== null ? "{$namePrefix}[{$field->key}]" : $field->key;
    $oldKey = $namePrefix !== null ? str_replace(['[', ']'], ['.', ''], $namePrefix).".{$field->key}" : $field->key;
    $inputId = 'field_'.str_replace('.', '_', $oldKey);
    $errorKey = $oldKey;
@endphp

<div class="mb-6">
    @if ($field->type->value !== 'informational_text')
        <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-ink">
            {{ $field->label }}
            @if ($field->is_required)<span aria-hidden="true">*</span>@endif
        </label>
    @endif

    @if ($field->help_text)
        <p class="mb-2 text-sm text-ink-soft">{{ $field->help_text }}</p>
    @endif

    @switch($field->type->value)
        @case('informational_text')
            <p class="text-base whitespace-pre-line text-ink">{{ $field->label }}</p>

            @if (! empty($config['image_path']))
                {{-- Image redimensionnée à l'envoi ; dimensions posées pour
                     éviter que la page ne saute pendant le chargement. --}}
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($config['image_path']) }}"
                    alt="{{ $config['image_alt'] ?? '' }}"
                    @if (! empty($config['image_width'])) width="{{ $config['image_width'] }}" @endif
                    @if (! empty($config['image_height'])) height="{{ $config['image_height'] }}" @endif
                    loading="lazy"
                    class="mt-3 h-auto w-full rounded-card"
                >
            @endif

            @php($video = \App\Domain\Form\Support\VideoEmbed::from($config['video_url'] ?? null))
            @if ($video !== null)
                {{-- Le lecteur n'arrive qu'au clic : la page reste légère et
                     rien ne part chez l'hébergeur avant ce clic. --}}
                <div x-data="{ playing: false }" class="mt-3 overflow-hidden rounded-card border border-line">
                    <template x-if="playing">
                        <div class="aspect-video w-full">
                            <iframe
                                src="{{ $video->embedUrl }}"
                                title="{{ $field->label }}"
                                loading="lazy"
                                allow="accelerometer; autoplay; encrypted-media; picture-in-picture"
                                allowfullscreen
                                class="h-full w-full"
                            ></iframe>
                        </div>
                    </template>
                    <button type="button" x-show="! playing" @click="playing = true" class="flex min-h-11 w-full items-center gap-3 px-4 py-3 text-left">
                        <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink text-bg">▶</span>
                        <span>
                            <span class="block text-sm font-medium text-ink">Lire la vidéo</span>
                            <span class="block text-xs text-ink-soft">{{ $video->provider }} · se charge seulement quand vous cliquez</span>
                        </span>
                    </button>
                </div>
            @endif
            @break

        @case('long_text')
            <textarea
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                rows="4"
                @if ($field->is_required) required @endif
                @if (isset($config['max_length'])) maxlength="{{ $config['max_length'] }}" @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >{{ old($oldKey, $value) }}</textarea>
            @break

        @case('number')
            <input
                type="number"
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                value="{{ old($oldKey, $value) }}"
                @if ($field->is_required) required @endif
                @if (isset($config['min'])) min="{{ $config['min'] }}" @endif
                @if (isset($config['max'])) max="{{ $config['max'] }}" @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('date')
            <input
                type="date"
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                value="{{ old($oldKey, $value) }}"
                @if ($field->is_required) required @endif
                @if (isset($config['min_date'])) min="{{ $config['min_date'] }}" @endif
                @if (isset($config['max_date'])) max="{{ $config['max_date'] }}" @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('yes_no')
            <div class="flex gap-4" role="radiogroup" aria-labelledby="{{ $inputId }}">
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="radio" name="{{ $inputName }}" value="1" @checked(old($oldKey, $value) == '1') @if ($field->is_required) required @endif>
                    Oui
                </label>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="radio" name="{{ $inputName }}" value="0" @checked(old($oldKey, $value) === '0')>
                    Non
                </label>
            </div>
            @break

        @case('consent')
            <label class="flex items-start gap-2 text-sm text-ink">
                <input type="checkbox" name="{{ $inputName }}" value="1" @checked(old($oldKey, $value)) @if ($field->is_required) required @endif class="mt-1">
                <span>{{ $config['legal_text'] ?? $field->label }}</span>
            </label>
            @break

        @case('single_choice')
        @case('meal_choice')
            <div role="radiogroup" aria-labelledby="{{ $inputId }}" class="space-y-2">
                @foreach ($field->options as $option)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="radio" name="{{ $inputName }}" value="{{ $option->value }}" @checked(old($oldKey, $value) === $option->value) @if ($field->is_required) required @endif>
                        {{ $option->label }}
                    </label>
                @endforeach
            </div>
            @break

        @case('multiple_choice')
            @php($selected = (array) old($oldKey, $value ?? []))
            <div class="space-y-2">
                @foreach ($field->options as $option)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="{{ $inputName }}[]" value="{{ $option->value }}" @checked(in_array($option->value, $selected, true))>
                        {{ $option->label }}
                    </label>
                @endforeach
            </div>
            @break

        @case('phone')
            <input
                type="tel"
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                value="{{ old($oldKey, $value) }}"
                @if ($field->is_required) required @endif
                placeholder="+243 8xx xxx xxx"
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('email')
            <input
                type="email"
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                value="{{ old($oldKey, $value) }}"
                @if ($field->is_required) required @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('dropdown')
            <select
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                @if ($field->is_required) required @endif
                class="w-full rounded-control border border-line bg-bg px-3 py-2 text-ink"
            >
                <option value="">Choisissez…</option>
                @foreach ($field->options as $option)
                    <option value="{{ $option->value }}" @selected(old($oldKey, $value) === $option->value)>{{ $option->label }}</option>
                @endforeach
            </select>
            @break

        @case('date_time')
            <input
                type="datetime-local"
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                value="{{ old($oldKey, $value) }}"
                @if ($field->is_required) required @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('url')
        @case('social_profile')
            <input
                type="url"
                inputmode="url"
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                value="{{ old($oldKey, $value) }}"
                @if ($field->is_required) required @endif
                placeholder="{{ $field->type->value === 'social_profile' ? 'https://www.linkedin.com/in/…' : 'https://' }}"
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('quantity')
            <input
                type="number"
                inputmode="numeric"
                step="1"
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                value="{{ old($oldKey, $value) }}"
                min="{{ $config['min'] ?? 0 }}"
                max="{{ $config['max'] ?? 99 }}"
                @if ($field->is_required) required @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink sm:w-40"
            >
            @break

        @case('postal_address')
            @php($address = (array) old($oldKey, $value ?? []))
            <div class="space-y-3">
                <input type="text" id="{{ $inputId }}" name="{{ $inputName }}[line1]" value="{{ $address['line1'] ?? '' }}" placeholder="Adresse" aria-label="Adresse" autocomplete="address-line1" @if ($field->is_required) required @endif class="w-full rounded-control border border-line px-3 py-2 text-ink">
                <input type="text" name="{{ $inputName }}[line2]" value="{{ $address['line2'] ?? '' }}" placeholder="Complément d'adresse" aria-label="Complément d'adresse" autocomplete="address-line2" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="{{ $inputName }}[city]" value="{{ $address['city'] ?? '' }}" placeholder="Ville" aria-label="Ville" autocomplete="address-level2" @if ($field->is_required) required @endif class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    <input type="text" name="{{ $inputName }}[region]" value="{{ $address['region'] ?? '' }}" placeholder="Province ou région" aria-label="Province ou région" autocomplete="address-level1" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="{{ $inputName }}[postal_code]" value="{{ $address['postal_code'] ?? '' }}" placeholder="Code postal" aria-label="Code postal" autocomplete="postal-code" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    <input type="text" name="{{ $inputName }}[country]" value="{{ $address['country'] ?? '' }}" placeholder="Pays" aria-label="Pays" autocomplete="country-name" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
            </div>
            @foreach (['line1', 'city'] as $part)
                @error("{$errorKey}.{$part}")
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endforeach
            @break

        @case('sub_events')
            @php($chosenSubEvents = array_map('intval', (array) old($oldKey, $value ?? [])))
            <div class="space-y-2" id="{{ $inputId }}">
                @foreach (\App\Domain\Form\Actions\SyncSubEventRegistrations::offeredIds($config) as $subEventId)
                    @php($choice = ($subEventChoices ?? [])[$subEventId] ?? null)
                    @continue($choice === null)
                    @php($isChosen = in_array($subEventId, $chosenSubEvents, true))
                    <label class="flex items-start gap-3 rounded-control border border-line bg-bg px-4 py-3 text-ink {{ $choice['closed'] && ! $isChosen ? 'opacity-60' : '' }}">
                        <input type="checkbox" name="{{ $inputName }}[]" value="{{ $subEventId }}" class="mt-1" @checked($isChosen) @disabled($choice['closed'] && ! $isChosen)>
                        <span>
                            <span class="block font-medium">{{ $choice['title'] }}</span>
                            <span class="block text-sm text-ink-soft">{{ $choice['schedule'] }}</span>
                            @if ($choice['availability'] !== null)
                                <span class="block text-xs text-ink-soft">{{ $choice['availability'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            @break

        @case('donation')
            @php($donation = (array) old($oldKey, $value ?? []))
            @php($donationCurrency = \App\Domain\Form\Support\DonationAnswer::currency($config))
            @php($donationChoice = is_string($donation['choice'] ?? null) ? $donation['choice'] : '')
            <div x-data="{ choice: @js($donationChoice) }" id="{{ $inputId }}" class="space-y-3">
                @if (! empty($config['cause']))
                    <p class="text-sm text-ink-soft">Au profit de : <span class="font-medium text-ink">{{ $config['cause'] }}</span></p>
                @endif
                <div role="radiogroup" class="flex flex-wrap gap-2">
                    @foreach (\App\Domain\Form\Support\DonationAnswer::suggestedAmounts($config) as $suggestedAmount)
                        <label class="cursor-pointer">
                            <input type="radio" name="{{ $inputName }}[choice]" value="{{ $suggestedAmount }}" x-model="choice" @checked($donationChoice === (string) $suggestedAmount) class="peer sr-only">
                            <span class="inline-flex min-h-11 items-center rounded-pill border border-line bg-bg px-4 text-sm font-medium text-ink peer-checked:border-ink peer-checked:bg-ink peer-checked:text-bg peer-focus-visible:ring-2 peer-focus-visible:ring-accent">{{ \App\Support\Money::fromMinorUnits($suggestedAmount, $donationCurrency)->format() }}</span>
                        </label>
                    @endforeach
                    @if (\App\Domain\Form\Support\DonationAnswer::allowsCustom($config))
                        <label class="cursor-pointer">
                            <input type="radio" name="{{ $inputName }}[choice]" value="{{ \App\Domain\Form\Support\DonationAnswer::CUSTOM }}" x-model="choice" @checked($donationChoice === \App\Domain\Form\Support\DonationAnswer::CUSTOM) class="peer sr-only">
                            <span class="inline-flex min-h-11 items-center rounded-pill border border-line bg-bg px-4 text-sm font-medium text-ink peer-checked:border-ink peer-checked:bg-ink peer-checked:text-bg peer-focus-visible:ring-2 peer-focus-visible:ring-accent">Autre montant</span>
                        </label>
                    @endif
                    @unless ($field->is_required)
                        <label class="cursor-pointer">
                            <input type="radio" name="{{ $inputName }}[choice]" value="" x-model="choice" @checked($donationChoice === '') class="peer sr-only">
                            <span class="inline-flex min-h-11 items-center rounded-pill border border-line bg-bg px-4 text-sm text-ink-soft peer-checked:border-ink peer-checked:text-ink peer-focus-visible:ring-2 peer-focus-visible:ring-accent">Pas de don pour l'instant</span>
                        </label>
                    @endunless
                </div>
                @if (\App\Domain\Form\Support\DonationAnswer::allowsCustom($config))
                    <div x-show="choice === @js(\App\Domain\Form\Support\DonationAnswer::CUSTOM)">
                        <label for="{{ $inputId }}_custom" class="mb-1.5 block text-sm text-ink">Votre montant ({{ $donationCurrency }})</label>
                        <input type="text" inputmode="decimal" autocomplete="off" id="{{ $inputId }}_custom" name="{{ $inputName }}[custom]" value="{{ is_string($donation['custom'] ?? null) ? $donation['custom'] : '' }}" class="w-full rounded-control border border-line px-3 py-2 text-ink sm:w-48">
                    </div>
                @endif
                <p class="text-xs text-ink-soft">Vous réglerez votre don juste après l'inscription : par carte, Mobile Money ou à l'accueil.</p>
            </div>
            @foreach (['choice', 'custom'] as $part)
                @error("{$errorKey}.{$part}")
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endforeach
            @break

        @case('file_upload')
            @php($currentToken = old($oldKey, $value))
            @php($currentFile = is_string($currentToken) ? (($uploadedFiles ?? [])[$currentToken] ?? null) : null)
            @if ($currentFile !== null)
                {{-- Fichier déjà envoyé : gardé tant que l'invité n'en choisit pas un autre, sauf s'il a été refusé. --}}
                @unless ($currentFile['isRejected'])
                    <input type="hidden" name="{{ $inputName }}" value="{{ $currentToken }}">
                @endunless
                <p class="mb-2 flex flex-wrap items-baseline gap-x-2 text-sm">
                    <span class="font-medium break-all text-ink">{{ $currentFile['name'] }}</span>
                    <span class="{{ $currentFile['isRejected'] ? 'text-red-600' : 'text-ink-soft' }}">{{ $currentFile['size'] }} · {{ $currentFile['status'] }}</span>
                </p>
            @endif
            <input
                type="file"
                id="{{ $inputId }}"
                name="{{ \App\Domain\Form\Support\FileUploadAnswer::INPUT_KEY }}[{{ $field->key }}]"
                accept="{{ \App\Domain\Form\Support\FileUploadAnswer::acceptAttribute($config) }}"
                class="block w-full text-sm text-ink-soft file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-pill file:border file:border-line file:bg-bg file:px-4 file:py-2 file:text-sm file:font-medium file:text-ink"
            >
            <p class="mt-1.5 text-xs text-ink-soft">
                {{ \App\Domain\Form\Support\FileUploadAnswer::typesLabel($config) }} · {{ \App\Domain\Form\Support\FileUploadAnswer::maxSizeMb($config) }} Mo maximum{{ $currentFile !== null ? ' · choisissez un autre fichier pour le remplacer' : '' }}. Chaque fichier est vérifié par un antivirus.
            </p>
            @break

        @case('donor_info')
            @php($donor = (array) old($oldKey, $value ?? []))
            <div class="space-y-3">
                <input type="text" id="{{ $inputId }}" name="{{ $inputName }}[name]" value="{{ $donor['name'] ?? ($donorDefaultName ?? '') }}" placeholder="Nom du donateur" aria-label="Nom du donateur" autocomplete="name" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                <input type="text" name="{{ $inputName }}[company]" value="{{ $donor['company'] ?? '' }}" placeholder="Entreprise ou organisation (facultatif)" aria-label="Entreprise ou organisation" autocomplete="organization" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                <input type="text" name="{{ $inputName }}[line1]" value="{{ $donor['line1'] ?? '' }}" placeholder="Adresse" aria-label="Adresse" autocomplete="address-line1" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                <input type="text" name="{{ $inputName }}[line2]" value="{{ $donor['line2'] ?? '' }}" placeholder="Complément d'adresse" aria-label="Complément d'adresse" autocomplete="address-line2" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="{{ $inputName }}[city]" value="{{ $donor['city'] ?? '' }}" placeholder="Ville" aria-label="Ville" autocomplete="address-level2" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    <input type="text" name="{{ $inputName }}[region]" value="{{ $donor['region'] ?? '' }}" placeholder="Province ou région" aria-label="Province ou région" autocomplete="address-level1" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="{{ $inputName }}[postal_code]" value="{{ $donor['postal_code'] ?? '' }}" placeholder="Code postal" aria-label="Code postal" autocomplete="postal-code" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    <input type="text" name="{{ $inputName }}[country]" value="{{ $donor['country'] ?? '' }}" placeholder="Pays" aria-label="Pays" autocomplete="country-name" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
                <label class="flex items-start gap-2 text-sm text-ink">
                    <input type="checkbox" name="{{ $inputName }}[anonymous]" value="1" @checked(! empty($donor['anonymous'])) class="mt-1">
                    <span>Je souhaite que mon don reste anonyme</span>
                </label>
                <p class="text-xs text-ink-soft">Ces informations figurent sur le reçu de votre don{{ $field->is_required ? ' : elles sont demandées dès que vous donnez' : '' }}.</p>
            </div>
            @foreach (['name', 'line1', 'city'] as $part)
                @error("{$errorKey}.{$part}")
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endforeach
            @break

        @default
            <input
                type="text"
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                value="{{ old($oldKey, $value) }}"
                @if ($field->is_required) required @endif
                @if (isset($config['max_length'])) maxlength="{{ $config['max_length'] }}" @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
    @endswitch

    @error($errorKey)
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
