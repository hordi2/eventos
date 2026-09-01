import { type LabelHTMLAttributes } from 'react';

export default function InputLabel({ children, className = '', ...props }: LabelHTMLAttributes<HTMLLabelElement>) {
    return (
        <label
            className={`mb-2.5 block font-label text-[11px] tracking-[0.18em] text-ink-soft uppercase ${className}`}
            {...props}
        >
            {children}
        </label>
    );
}
