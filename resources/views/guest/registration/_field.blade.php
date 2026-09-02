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
