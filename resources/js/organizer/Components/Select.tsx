import { forwardRef, type SelectHTMLAttributes } from 'react';

const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(function Select(
    { className = '', children, ...props },
    ref,
) {
    return (
        <div className="relative">
            <select
                ref={ref}
                className={`min-h-11 w-full appearance-none border-0 border-b border-line bg-transparent px-0.5 py-2.5 pr-6 font-sans text-base text-ink transition-colors duration-300 focus:border-accent focus:ring-0 focus:outline-none ${className}`}
                {...props}
            >
                {children}
            </select>
            <svg
                className="pointer-events-none absolute top-1/2 right-0.5 h-2.5 w-3.5 -translate-y-1/2 text-ink-soft"
                viewBox="0 0 14 9"
                fill="none"
                aria-hidden="true"
            >
                <path d="M1 1l6 6 6-6" stroke="currentColor" strokeWidth="1.5" />
            </svg>
        </div>
    );
});

export default Select;
