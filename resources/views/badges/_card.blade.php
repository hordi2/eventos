{{-- Carte de badge partagée entre le PDF individuel et la planche Avery (T-064). --}}
<div class="badge-card" style="border-top-color: {{ $context->accentColor ?? '#111111' }};">
    @if ($context->logoDataUri !== null)
        <img src="{{ $context->logoDataUri }}" class="badge-logo" alt="">
    @endif

    <div class="badge-organization">{{ $context->organizationName }}</div>
    <div class="badge-event">{{ $context->eventTitle }}</div>
    <div class="badge-name">{{ $context->guestName }}</div>

    @if ($context->tableName !== null)
        <div class="badge-table">{{ $context->tableName }}</div>
    @endif

    @if ($context->qrDataUri !== null)
        <img src="{{ $context->qrDataUri }}" class="badge-qr" alt="">
    @endif
</div>
