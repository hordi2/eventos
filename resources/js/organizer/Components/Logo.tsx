import { usePage } from '@inertiajs/react';
import { type SharedProps } from '../types';

/**
 * La marque du portail. Un compte confié à une agence qui marque les
 * portails de ses clients porte son logo ; partout ailleurs, c'est celui
 * d'Itaza.
 */
export default function Logo({ className = 'h-9 w-auto' }: { className?: string }) {
    const { portalBrand } = usePage<SharedProps>().props;

    if (portalBrand) {
        return <img src={portalBrand.logoUrl} alt={portalBrand.name} className={`${className} object-contain`} />;
    }

    return (
        <>
            <img src="/images/logo.png" alt="Itaza Invitation" className={`${className} dark:hidden`} />
            <img src="/images/logo-white.png" alt="Itaza Invitation" className={`hidden ${className} dark:block`} />
        </>
    );
}
