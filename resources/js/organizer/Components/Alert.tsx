import { type PropsWithChildren } from 'react';

type Variant = 'success' | 'danger';

const variantClasses: Record<Variant, string> = {
    success: 'bg-success-bg text-success',
    danger: 'bg-danger-bg text-danger',
};

export default function Alert({ variant, children }: PropsWithChildren<{ variant: Variant }>) {
    return (
        <div
            role={variant === 'danger' ? 'alert' : 'status'}
            className={`rounded-control px-4 py-3 text-sm font-medium ${variantClasses[variant]}`}
        >
            {children}
        </div>
    );
}
