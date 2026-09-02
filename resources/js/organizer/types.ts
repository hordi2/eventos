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

export interface SharedProps {
    auth: {
        user: User | null;
    };
    nav: NavItem[] | null;
    [key: string]: unknown;
}
