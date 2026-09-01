export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export interface SharedProps {
    auth: {
        user: User | null;
    };
    [key: string]: unknown;
}
