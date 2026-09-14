@php
    $config = $field->config ?? [];
    $errorKey = $field->key;
@endphp

<div class="mb-6">
    @if ($field->type->value !== 'informational_text')
        <label for="field_{{ $field->key }}" class="mb-1.5 block text-sm font-medium text-ink">
            {{ $field->label }}
            @if ($field->is_required)<span aria-hidden="true">*</span>@endif
        </label>
    @endif

    @if ($field->help_text)
        <p class="mb-2 text-sm text-ink-soft">{{ $field->help_text }}</p>
    @endif

    @switch($field->type->value)
        @case('informational_text')
            <p class="text-base text-ink">{{ $field->label }}</p>
            @break

        @case('long_text')
            <textarea
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                rows="4"
                @if ($field->is_required) required @endif
                @if (isset($config['max_length'])) maxlength="{{ $config['max_length'] }}" @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >{{ old($field->key, $value) }}</textarea>
            @break

        @case('number')
            <input
                type="number"
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                value="{{ old($field->key, $value) }}"
                @if ($field->is_required) required @endif
                @if (isset($config['min'])) min="{{ $config['min'] }}" @endif
                @if (isset($config['max'])) max="{{ $config['max'] }}" @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('date')
            <input
                type="date"
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                value="{{ old($field->key, $value) }}"
                @if ($field->is_required) required @endif
                @if (isset($config['min_date'])) min="{{ $config['min_date'] }}" @endif
                @if (isset($config['max_date'])) max="{{ $config['max_date'] }}" @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('yes_no')
            <div class="flex gap-4" role="radiogroup" aria-labelledby="field_{{ $field->key }}">
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="radio" name="{{ $field->key }}" value="1" @checked(old($field->key, $value) == '1') @if ($field->is_required) required @endif>
                    Oui
                </label>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="radio" name="{{ $field->key }}" value="0" @checked(old($field->key, $value) === '0')>
                    Non
                </label>
            </div>
            @break

        @case('consent')
            <label class="flex items-start gap-2 text-sm text-ink">
                <input type="checkbox" name="{{ $field->key }}" value="1" @checked(old($field->key, $value)) @if ($field->is_required) required @endif class="mt-1">
                <span>{{ $config['legal_text'] ?? $field->label }}</span>
            </label>
            @break

        @case('single_choice')
        @case('meal_choice')
            <div role="radiogroup" aria-labelledby="field_{{ $field->key }}" class="space-y-2">
                @foreach ($field->options as $option)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="radio" name="{{ $field->key }}" value="{{ $option->value }}" @checked(old($field->key, $value) === $option->value) @if ($field->is_required) required @endif>
                        {{ $option->label }}
                    </label>
                @endforeach
            </div>
            @break

        @case('multiple_choice')
            @php($selected = (array) old($field->key, $value ?? []))
            <div class="space-y-2">
                @foreach ($field->options as $option)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="{{ $field->key }}[]" value="{{ $option->value }}" @checked(in_array($option->value, $selected, true))>
                        {{ $option->label }}
                    </label>
                @endforeach
            </div>
            @break

        @case('phone')
            <input
                type="tel"
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                value="{{ old($field->key, $value) }}"
                @if ($field->is_required) required @endif
                placeholder="+243 8xx xxx xxx"
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('email')
            <input
                type="email"
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                value="{{ old($field->key, $value) }}"
                @if ($field->is_required) required @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('dropdown')
            <select
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                @if ($field->is_required) required @endif
                class="w-full rounded-control border border-line bg-bg px-3 py-2 text-ink"
            >
                <option value="">Choisissez…</option>
                @foreach ($field->options as $option)
                    <option value="{{ $option->value }}" @selected(old($field->key, $value) === $option->value)>{{ $option->label }}</option>
                @endforeach
            </select>
            @break

        @case('date_time')
            <input
                type="datetime-local"
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                value="{{ old($field->key, $value) }}"
                @if ($field->is_required) required @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
            @break

        @case('url')
        @case('social_profile')
            <input
                type="url"
                inputmode="url"
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                value="{{ old($field->key, $value) }}"
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
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                value="{{ old($field->key, $value) }}"
                min="{{ $config['min'] ?? 0 }}"
                max="{{ $config['max'] ?? 99 }}"
                @if ($field->is_required) required @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink sm:w-40"
            >
            @break

        @case('postal_address')
            @php($address = (array) old($field->key, $value ?? []))
            <div class="space-y-3">
                <input type="text" name="{{ $field->key }}[line1]" value="{{ $address['line1'] ?? '' }}" placeholder="Adresse" aria-label="Adresse" autocomplete="address-line1" @if ($field->is_required) required @endif class="w-full rounded-control border border-line px-3 py-2 text-ink">
                <input type="text" name="{{ $field->key }}[line2]" value="{{ $address['line2'] ?? '' }}" placeholder="Complément d'adresse" aria-label="Complément d'adresse" autocomplete="address-line2" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="{{ $field->key }}[city]" value="{{ $address['city'] ?? '' }}" placeholder="Ville" aria-label="Ville" autocomplete="address-level2" @if ($field->is_required) required @endif class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    <input type="text" name="{{ $field->key }}[region]" value="{{ $address['region'] ?? '' }}" placeholder="Province ou région" aria-label="Province ou région" autocomplete="address-level1" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="{{ $field->key }}[postal_code]" value="{{ $address['postal_code'] ?? '' }}" placeholder="Code postal" aria-label="Code postal" autocomplete="postal-code" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    <input type="text" name="{{ $field->key }}[country]" value="{{ $address['country'] ?? '' }}" placeholder="Pays" aria-label="Pays" autocomplete="country-name" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
            </div>
            @foreach (['line1', 'city'] as $part)
                @error("{$field->key}.{$part}")
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endforeach
            @break

        @default
            <input
                type="text"
                id="field_{{ $field->key }}"
                name="{{ $field->key }}"
                value="{{ old($field->key, $value) }}"
                @if ($field->is_required) required @endif
                @if (isset($config['max_length'])) maxlength="{{ $config['max_length'] }}" @endif
                class="w-full rounded-control border border-line px-3 py-2 text-ink"
            >
    @endswitch

    @error($errorKey)
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
