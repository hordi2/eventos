export default function GoogleButton() {
    return (
        <a
            href="/auth/google/redirect"
            className="inline-flex w-full items-center justify-center gap-3 rounded-full border border-line px-8 py-4 font-sans text-[14.5px] font-medium text-ink transition-colors duration-300 hover:border-ink"
        >
            <svg className="h-4 w-4" viewBox="0 0 18 18" aria-hidden="true">
                <path
                    fill="#4285F4"
                    d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.71v2.26h2.91c1.7-1.57 2.69-3.88 2.69-6.61Z"
                />
                <path
                    fill="#34A853"
                    d="M9 18c2.43 0 4.47-.8 5.96-2.19l-2.91-2.26c-.81.54-1.84.86-3.05.86-2.35 0-4.34-1.58-5.05-3.71H.94v2.33A9 9 0 0 0 9 18Z"
                />
                <path fill="#FBBC05" d="M3.95 10.7A5.4 5.4 0 0 1 3.67 9c0-.59.1-1.17.28-1.7V4.97H.94A9 9 0 0 0 0 9c0 1.45.35 2.83.94 4.03l3.01-2.33Z" />
                <path
                    fill="#EA4335"
                    d="M9 3.58c1.32 0 2.51.46 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .94 4.97l3.01 2.33C4.66 5.16 6.65 3.58 9 3.58Z"
                />
            </svg>
            Continuer avec Google
        </a>
    );
}
