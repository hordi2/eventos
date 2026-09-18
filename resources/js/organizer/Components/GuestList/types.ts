export interface InviteeTag {
    name: string;
    color: string;
}

export interface InviteeRow {
    id: number;
    firstName: string | null;
    lastName: string | null;
    fullName: string;
    email: string | null;
    phone: string | null;
    groupKey: string | null;
    companionsAllowed: number | null;
    companionsLabel: string;
    ccEmail: string | null;
    tags: InviteeTag[];
    response: { status: string; label: string } | null;
    personalUrl: string;
    whatsappUrl: string | null;
}
