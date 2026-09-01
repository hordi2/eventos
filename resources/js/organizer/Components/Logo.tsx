export default function Logo({ className = 'h-9 w-auto' }: { className?: string }) {
    return (
        <>
            <img src="/images/logo.png" alt="Itaza Invitation" className={`${className} dark:hidden`} />
            <img src="/images/logo-white.png" alt="Itaza Invitation" className={`hidden ${className} dark:block`} />
        </>
    );
}
