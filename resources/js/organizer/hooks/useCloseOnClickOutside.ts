import { useEffect, useRef } from 'react';

/**
 * Ferme un menu déroulant au clic en dehors de l'élément référencé.
 */
export default function useCloseOnClickOutside(open: boolean, close: () => void) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        function handleClickOutside(event: MouseEvent) {
            if (ref.current && !ref.current.contains(event.target as Node)) {
                close();
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [open, close]);

    return ref;
}
