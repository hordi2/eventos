import { type PropsWithChildren } from 'react';

type Variant = 'neutral' | 'success' | 'danger';

const variantClasses: Record<Variant, string> = {
    neutral: 'bg-bg-deep text-ink-soft',
    success: 'bg-success-bg text-success',
    danger: 'bg-danger-bg text-danger',
};

export default function Badge({ variant = 'neutral', children }: PropsWithChildren<{ variant?: Variant }>) {
    return (
        <span
            className={`inline-flex items-center rounded-pill px-2.5 py-1 font-label text-[11px] tracking-[0.08em] uppercase ${variantClasses[variant]}`}
        >
            {children}
        </span>
    );
}
