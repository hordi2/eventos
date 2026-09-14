const ICON_PATHS = {
    form: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z',
    welcome: 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21v-1a8 8 0 0 1 16 0v1M19 4l2-1M20 8h2',
    meal: 'M7 3v8M5 3v5a2 2 0 0 0 4 0V3M7 11v10M17 3c-2 0-3 2-3 5s1 4 3 4v9',
    media: 'M3 5h18v14H3zM3 16l5-5 4 4 3-3 6 6M15.5 9.5h.01',
    question: 'M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12ZM9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5M12 16h.01',
    subEvent: 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4M12 13v5M9.5 15.5h5',
    donation: 'M3 8h18v4H3zM5 12h14v9H5zM12 8v13M12 8c-1.5-3-6-3-6 0s4.5 0 6 0ZM12 8c1.5-3 6-3 6 0s-4.5 0-6 0Z',
    note: 'M4 20h4L19 9l-4-4L4 16v4ZM13 7l4 4',
    donor: 'M12 8.5c-1-2-4.5-2-4.5.8 0 2 4.5 4.7 4.5 4.7s4.5-2.7 4.5-4.7c0-2.8-3.5-2.8-4.5-.8ZM3 15h3l4 3h6a2 2 0 0 0 0-4h-3',
    confirmation: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM8 12.5l3 3 5-6',
    decline: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM9 9l6 6M15 9l-6 6',
    identity: 'M3 5h18v14H3zM8.5 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM5.5 16a3 3 0 0 1 6 0M14 9h4M14 13h4',
    gear: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 13a7.6 7.6 0 0 0 0-2l2-1.5-2-3.5-2.4 1a7 7 0 0 0-1.7-1L15 3.5h-4l-.4 2.5a7 7 0 0 0-1.7 1l-2.4-1-2 3.5 2 1.5a7.6 7.6 0 0 0 0 2l-2 1.5 2 3.5 2.4-1a7 7 0 0 0 1.7 1l.4 2.5h4l.4-2.5a7 7 0 0 0 1.7-1l2.4 1 2-3.5Z',
    copy: 'M8 8h12v12H8zM16 8V4H4v12h4',
    trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
    up: 'M12 19V5M6 11l6-6 6 6',
    down: 'M12 5v14M6 13l6 6 6-6',
    grip: 'M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01',
    hand: 'M8 13V5.5a1.5 1.5 0 0 1 3 0V11M11 10.5V4.5a1.5 1.5 0 0 1 3 0v6M14 10.5V6a1.5 1.5 0 0 1 3 0v7c0 4-2.5 8-7 8-3 0-4.5-1.5-6-4l-1.6-3a1.5 1.5 0 0 1 2.6-1.5L8 14',
    star: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z',
    upload: 'M12 16V4M7 9l5-5 5 5M4 20h16',
    palette: 'M12 21a9 9 0 1 1 9-9c0 2.5-2 3-3.5 3H15a2 2 0 0 0-1 3.7c.6.6.3 2.3-2 2.3ZM7.5 11.5h.01M10.5 7.5h.01M15.5 8.5h.01',
    close: 'M6 6l12 12M18 6 6 18',
    plus: 'M12 5v14M5 12h14',
    desktop: 'M3 4h18v12H3zM8 20h8M12 16v4',
    mobile: 'M7 2h10v20H7zM11 18h2',
} as const;

export type BuilderIconName = keyof typeof ICON_PATHS;

interface BuilderIconProps {
    name: BuilderIconName;
    className?: string;
}

/**
 * Pictogrammes au trait du constructeur de formulaire, sur la même grille de
 * 24 px et le même trait qu'EventIcon.
 */
export default function BuilderIcon({ name, className = 'h-5 w-5' }: BuilderIconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            className={`shrink-0 stroke-current ${className}`}
            fill="none"
            strokeWidth={name === 'grip' ? 3 : 1.6}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={ICON_PATHS[name]} />
        </svg>
    );
}
