import { forwardRef, type TextareaHTMLAttributes } from 'react';

const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function Textarea(
    { className = '', ...props },
    ref,
) {
    return (
        <textarea
            ref={ref}
            className={`min-h-24 w-full resize-y rounded-control border border-line bg-transparent px-3 py-2.5 font-sans text-base text-ink transition-colors duration-300 focus:border-accent focus:ring-0 focus:outline-none ${className}`}
            {...props}
        />
    );
});

export default Textarea;
