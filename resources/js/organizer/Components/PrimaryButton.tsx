import { type ButtonHTMLAttributes } from 'react';

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            disabled={disabled}
            className={`inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-ink px-8 py-4 font-sans text-[14.5px] font-medium text-white transition-transform duration-300 hover:-translate-y-0.5 disabled:opacity-50 ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}
