const ICON_PATHS = {
    checklist: 'M9 6h11M9 12h11M9 18h11M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2',
    chart: 'M3 3v18h18M7 15l4-4 3 3 5-6',
    website: 'M3 5h18v14H3zM3 9h18M7 13h5M7 16h8',
    form: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z',
    settings:
        'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 13a7.6 7.6 0 0 0 0-2l2-1.5-2-3.5-2.4 1a7 7 0 0 0-1.7-1L15 3.5h-4l-.4 2.5a7 7 0 0 0-1.7 1l-2.4-1-2 3.5 2 1.5a7.6 7.6 0 0 0 0 2l-2 1.5 2 3.5 2.4-1a7 7 0 0 0 1.7 1l.4 2.5h4l.4-2.5a7 7 0 0 0 1.7-1l2.4 1 2-3.5Z',
    guests: 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM2 21v-1a7 7 0 0 1 14 0v1M16 3.5a4 4 0 0 1 0 7.5M22 21v-1a6 6 0 0 0-4-5.6',
    eye: 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
    rocket: 'M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2M14 4c3-1 6-1 6-1s0 3-1 6l-7 7-5-5 7-7ZM9 11l-3-1-2 2 4 1M13 15l1 3-2 2-1-4',
    megaphone: 'M3 11v2a1 1 0 0 0 1 1h2l6 4V6L6 10H4a1 1 0 0 0-1 1ZM16 9a3 3 0 0 1 0 6M19 6a7 7 0 0 1 0 12',
    send: 'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z',
    mail: 'M3 5h18v14H3zM3 6l9 7 9-7',
    link: 'M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7',
    team: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
    seat: 'M7 3h10v8H7zM4 11h16v4H4zM6 15v6M18 15v6',
    scan: 'M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10',
    badge: 'M4 4h16v16H4zM12 11a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM8 17a4 4 0 0 1 8 0',
    ticket: 'M3 7V5h18v2a2 2 0 0 0 0 4v2a2 2 0 0 0 0 4v2H3v-2a2 2 0 0 0 0-4v-2a2 2 0 0 0 0-4ZM14 5v14',
    download: 'M12 3v12M7 10l5 5 5-5M4 21h16',
    gift: 'M3 8h18v4H3zM5 12h14v9H5zM12 8v13M12 8c-1.5-3-6-3-6 0s4.5 0 6 0ZM12 8c1.5-3 6-3 6 0s-4.5 0-6 0Z',
    palette: 'M12 21a9 9 0 1 1 9-9c0 2.5-2 3-3.5 3H15a2 2 0 0 0-1 3.7c.6.6.3 2.3-2 2.3ZM7.5 11.5h.01M10.5 7.5h.01M15.5 8.5h.01',
    switch: 'M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5',
    external: 'M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6',
    home: 'M3 11 12 3l9 8M5 9.5V21h14V9.5',
    menu: 'M4 6h16M4 12h16M4 18h16',
    close: 'M6 6l12 12M18 6 6 18',
    check: 'M5 12.5l4.5 4.5L19 7.5',
    chevronRight: 'M9 6l6 6-6 6',
    chevronDown: 'M6 9l6 6 6-6',
} as const;

export type EventIconName = keyof typeof ICON_PATHS;

interface EventIconProps {
    name: EventIconName;
    className?: string;
}

/**
 * Pictogrammes au trait des pages d'un événement (menu latéral, liste de
 * contrôle), dessinés sur une grille de 24 px et colorés par currentColor.
 */
export default function EventIcon({ name, className = 'h-5 w-5' }: EventIconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            className={`shrink-0 stroke-current ${className}`}
            fill="none"
            strokeWidth="1.6"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={ICON_PATHS[name]} />
        </svg>
    );
}
