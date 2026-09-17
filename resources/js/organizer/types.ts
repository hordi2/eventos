export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export interface NavLink {
    label: string;
    href: string;
}

export type NavItem = NavLink | { label: string; items: NavLink[] };

export interface OrganizationChoice {
    id: number;
    name: string;
    current: boolean;
}

export type EventNavLinkKey =
    | 'checklist'
    | 'responses'
    | 'subEvents'
    | 'files'
    | 'guests'
    | 'form'
    | 'website'
    | 'settings'
    | 'import'
    | 'communications'
    | 'collaborators'
    | 'seating'
    | 'checkIn'
    | 'badges'
    | 'tickets'
    | 'exports'
    | 'referral';

/**
 * Navigation d'un événement (BuildEventNavigation) : un lien vaut null quand
 * l'utilisateur ne peut pas ouvrir la page.
 */
export interface EventNav {
    id: number;
    title: string;
    status: 'draft' | 'published' | 'archived';
    organizationName: string;
    publicUrl: string | null;
    previewUrl: string | null;
    canChangeStatus: boolean;
    links: Record<EventNavLinkKey, string | null>;
}

export interface SharedProps {
    auth: {
        user: User | null;
    };
    nav: NavItem[] | null;
    settingsAccess: {
        branding: boolean;
        billing: boolean;
        auditLog: boolean;
        integrations: boolean;
        security: boolean;
        whiteLabel: boolean;
        referral: boolean;
        eventSharing: boolean;
    };
    organizations: OrganizationChoice[];
    eventNav: EventNav | null;
    flash: {
        status: string | null;
        plainToken: string | null;
        plainSecret: string | null;
    };
    [key: string]: unknown;
}
