import { Head } from '@inertiajs/react';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface Guest {
    guest_type: 'attendee' | 'ticket';
    id: number;
    name: string;
}

interface SeatingTable {
    id: number;
    name: string;
    shape: string;
    capacity: number;
    position_x: number;
    position_y: number;
    width: number;
    height: number;
    rotation: number;
    guests: Guest[];
}

interface Props {
    event: { id: number; title: string };
    tables: SeatingTable[];
}

// Même disposition en cercle que l'éditeur (Seating/Show), reprise ici sans
// aucune interaction : vue d'ensemble pensée pour être affichée telle
// quelle le jour de l'événement (demande explicite de l'utilisateur).
const SEAT_SIZE = 28;
const SEAT_GAP = 16;

function guestKey(guest: Guest): string {
    return `${guest.guest_type}:${guest.id}`;
}

function initials(name: string): string {
    const parts = name.trim().split(/\s+/);

    return parts
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

type Seat = { type: 'filled'; guest: Guest } | { type: 'empty' };

function seatsFor(table: SeatingTable): Seat[] {
    const seats: Seat[] = table.guests.map((guest) => ({ type: 'filled', guest }));

    while (seats.length < table.capacity) {
        seats.push({ type: 'empty' });
    }

    return seats;
}

function seatPosition(table: SeatingTable, index: number, total: number): { left: number; top: number } {
    const centerX = table.position_x + table.width / 2;
    const centerY = table.position_y + table.height / 2;
    const ringRadius = Math.max(table.width, table.height) / 2 + SEAT_GAP + SEAT_SIZE / 2;
    const angle = -Math.PI / 2 + (2 * Math.PI * index) / Math.max(total, 1);

    return {
        left: centerX + ringRadius * Math.cos(angle) - SEAT_SIZE / 2,
        top: centerY + ringRadius * Math.sin(angle) - SEAT_SIZE / 2,
    };
}

export default function Overview({ event, tables }: Props) {
    const canvasWidth = Math.max(800, ...tables.map((t) => t.position_x + t.width + 80));
    const canvasHeight = Math.max(560, ...tables.map((t) => t.position_y + t.height + 80));

    return (
        <OrganizerLayout title="Vue d'ensemble de la salle" eyebrow={event.title}>
            <Head title="Vue d'ensemble de la salle" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl">Vue d'ensemble de la salle</h1>
                <a href={`/events/${event.id}/seating`} className="text-sm text-accent underline underline-offset-2">
                    Retour à l'éditeur
                </a>
            </div>

            <div className="relative mb-8 overflow-auto rounded-card bg-bg-deep ring-1 ring-line" style={{ height: 620 }}>
                <div className="relative" style={{ width: canvasWidth, height: canvasHeight }}>
                    {tables.map((table) => {
                        const seats = seatsFor(table);

                        return (
                            <div key={table.id}>
                                <div
                                    className={`absolute flex flex-col items-center justify-center border-2 border-ink bg-bg p-2 text-center text-xs ${table.shape === 'round' ? 'rounded-full' : 'rounded-md'}`}
                                    style={{ left: table.position_x, top: table.position_y, width: table.width, height: table.height }}
                                >
                                    <span className="font-medium">{table.name}</span>
                                    <span className="text-ink-soft">
                                        {table.guests.length}/{table.capacity}
                                    </span>
                                </div>

                                {seats.map((seat, index) => {
                                    const position = seatPosition(table, index, seats.length);

                                    return (
                                        <div
                                            key={`${table.id}-seat-${index}`}
                                            className={`absolute flex items-center justify-center rounded-full text-[10px] font-medium ${
                                                seat.type === 'filled'
                                                    ? 'border border-accent bg-accent/10 text-ink'
                                                    : 'border border-dashed border-ink-soft text-ink-soft'
                                            }`}
                                            style={{ left: position.left, top: position.top, width: SEAT_SIZE, height: SEAT_SIZE }}
                                            title={seat.type === 'filled' ? seat.guest.name : 'Place libre'}
                                        >
                                            {seat.type === 'filled' ? initials(seat.guest.name) : ''}
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                {tables.map((table) => (
                    <div key={table.id} className="rounded-card bg-bg p-4 ring-1 ring-line">
                        <h3 className="mb-2 text-sm font-medium">
                            {table.name} <span className="text-ink-soft">({table.guests.length}/{table.capacity})</span>
                        </h3>
                        <ul className="space-y-1 text-sm">
                            {table.guests.map((guest) => (
                                <li key={guestKey(guest)}>{guest.name}</li>
                            ))}
                            {table.guests.length === 0 && <li className="text-ink-soft">Vide.</li>}
                        </ul>
                    </div>
                ))}
                {tables.length === 0 && <p className="text-sm text-ink-soft">Aucune table créée pour cet événement.</p>}
            </div>
        </OrganizerLayout>
    );
}
