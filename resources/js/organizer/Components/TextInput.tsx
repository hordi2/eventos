import { forwardRef, type InputHTMLAttributes } from 'react';

const TextInput = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function TextInput(
    { className = '', ...props },
    ref,
) {
    return (
        <input
            ref={ref}
            className={`min-h-11 w-full border-0 border-b border-line bg-transparent px-0.5 py-2.5 font-sans text-base text-ink transition-colors duration-300 focus:border-accent focus:ring-0 focus:outline-none ${className}`}
            {...props}
        />
    );
});

export default TextInput;
