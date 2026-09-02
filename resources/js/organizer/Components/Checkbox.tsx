import { forwardRef, type InputHTMLAttributes } from 'react';

const Checkbox = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Checkbox(
    { className = '', ...props },
    ref,
) {
    return (
        <input
            ref={ref}
            type="checkbox"
            className={`h-4 w-4 rounded-sm border border-line bg-transparent text-accent focus:ring-0 focus:ring-offset-0 ${className}`}
            {...props}
        />
    );
});

export default Checkbox;
