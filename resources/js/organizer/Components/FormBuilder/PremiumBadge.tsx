import BuilderIcon from './BuilderIcon';

/**
 * Question qu'on peut essayer à tout moment, mais dont la publication demande
 * un plan payant (PublishFormVersion).
 */
export default function PremiumBadge({ compact = false }: { compact?: boolean }) {
    return (
        <span
            title="Publication réservée aux plans payants"
            className="inline-flex shrink-0 items-center gap-1 rounded-pill bg-[#fff1cc] px-2 py-0.5 font-label text-[10px] tracking-[0.08em] text-[#7a4f00] uppercase"
        >
            <BuilderIcon name="star" className="h-3 w-3" />
            {compact ? <span className="sr-only">Plans payants</span> : 'Plans payants'}
        </span>
    );
}
