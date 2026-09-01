import { type ButtonHTMLAttributes } from 'react';

type Variant = 'primary' | 'secondary' | 'danger';

const variantClasses: Record<Variant, string> = {
    primary: 'bg-ink text-bg hover:opacity-90',
    secondary: 'border border-line text-ink hover:border-ink',
    danger: 'bg-danger text-white hover:opacity-90',
};

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
}

export default function Button({ variant = 'primary', className = '', disabled, children, ...props }: ButtonProps) {
    return (
        <button
            disabled={disabled}
            className={`inline-flex min-h-11 w-full items-center justify-center gap-2.5 rounded-pill px-8 py-4 font-sans text-[14.5px] font-medium transition-all duration-300 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 hover:-translate-y-0.5 ${variantClasses[variant]} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}
