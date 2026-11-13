@php
    $footerLinks = [
        ['label' => 'Soutien', 'href' => route('static.support')],
        ['label' => 'Communauté', 'href' => route('community.index')],
        ['label' => "Retour d'information", 'href' => route('static.feedback')],
        ['label' => 'Statut', 'href' => route('status.index')],
        ['label' => "Conditions d'utilisation", 'href' => route('static.terms')],
        ['label' => 'Politique de confidentialité', 'href' => route('static.privacy')],
        ['label' => "Programme d'affiliation", 'href' => route('static.affiliates')],
    ];
    $socials = [
        'Facebook' => 'https://www.facebook.com/share/1EEdj2y4hX/',
        'Instagram' => 'https://www.instagram.com/itazainvitation/',
    ];
@endphp

<footer class="border-t border-line bg-bg-alt">
    <div class="mx-auto max-w-5xl px-4 py-12 text-center sm:px-6">
        <img src="/images/logo.png" alt="{{ config('app.name', 'Itaza Invitation') }}" class="mx-auto mb-6 h-7 w-auto opacity-80">

        <nav class="mb-6 flex flex-wrap justify-center gap-x-6 gap-y-2">
            @foreach ($footerLinks as $link)
                <a href="{{ $link['href'] }}" class="text-sm text-ink-soft hover:text-ink">{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <p class="mb-6 text-xs text-ink-soft">© {{ now()->year }} {{ config('app.name', 'Itaza Invitation') }}</p>

        <div class="flex justify-center gap-4">
            @foreach ($socials as $social => $socialUrl)
                <a href="{{ $socialUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $social }}" title="{{ $social }}" class="text-ink-soft hover:text-ink">
                    <svg viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                        @switch($social)
                            @case('Facebook')
                                <path d="M13 22v-8h2.7l.4-3H13V9c0-.9.2-1.5 1.5-1.5H16V5c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.5v3H9.8v8H13Z" />
                                @break
                            @case('Instagram')
                                <path d="M12 8.6a3.4 3.4 0 1 0 0 6.8 3.4 3.4 0 0 0 0-6.8ZM12 4c-2.2 0-2.5 0-3.4.1-.9 0-1.5.2-2 .4-.6.2-1 .5-1.5 1a4 4 0 0 0-1 1.5c-.2.5-.3 1.1-.4 2C3.6 9.9 3.6 10.2 3.6 12s0 2.1.1 3c0 .9.2 1.5.4 2 .2.6.5 1 1 1.5.4.4.9.7 1.5 1 .5.1 1.1.3 2 .3.9.1 1.2.1 3.4.1s2.5 0 3.4-.1c.9 0 1.5-.2 2-.4.6-.2 1-.5 1.5-1 .4-.4.7-.9 1-1.5.1-.5.3-1.1.3-2 .1-.9.1-1.2.1-3.4s0-2.5-.1-3.4c0-.9-.2-1.5-.4-2a4 4 0 0 0-1-1.5c-.4-.4-.9-.7-1.5-1-.5-.1-1.1-.3-2-.4C14.5 4 14.2 4 12 4Zm0 1.4c2.1 0 2.4 0 3.3.1.8 0 1.2.2 1.5.3.4.1.6.3.9.6.3.3.4.5.6.9.1.3.2.7.3 1.5.1.9.1 1.2.1 3.3s0 2.4-.1 3.3c0 .8-.2 1.2-.3 1.5-.1.4-.3.6-.6.9-.3.3-.5.4-.9.6-.3.1-.7.2-1.5.3-.9.1-1.2.1-3.3.1s-2.4 0-3.3-.1c-.8 0-1.2-.2-1.5-.3-.4-.1-.6-.3-.9-.6a2.6 2.6 0 0 1-.6-.9c-.1-.3-.2-.7-.3-1.5-.1-.9-.1-1.2-.1-3.3s0-2.4.1-3.3c0-.8.2-1.2.3-1.5.1-.4.3-.6.6-.9.3-.3.5-.4.9-.6.3-.1.7-.2 1.5-.3.9-.1 1.2-.1 3.3-.1ZM16.9 7.5a.8.8 0 1 0 0 1.6.8.8 0 0 0 0-1.6Z" />
                                @break
                        @endswitch
                    </svg>
                </a>
            @endforeach
        </div>
    </div>
</footer>
