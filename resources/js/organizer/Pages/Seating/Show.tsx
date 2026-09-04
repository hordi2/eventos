import { Head } from '@inertiajs/react';
import { type DragEvent, useState } from 'react';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import TextInput from '../../Components/TextInput';
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

interface Constraint {
    id: number;
    guest_a_type: string;
    guest_a_id: number;
    guest_b_type: string;
    guest_b_id: number;
    type: 'must_be_with' | 'must_not_be_with';
}

interface Props {
    event: { id: number; title: string };
    tables: SeatingTable[];
    unassignedGuests: Guest[];
    constraints: Constraint[];
    shapes: string[];
}

interface OpenSeat {
    tableId: number;
    index: number;
}

const SHAPE_LABELS: Record<string, string> = {
    round: 'Ronde',
    rectangular: 'Rectangulaire',
    u_shape: 'En U',
    cocktail: 'Cocktail',
    rows: 'Rangées',
};

// Rayon des places (« boules ») autour d'une table et espace les séparant
// du bord de la table (T-065 bis : demande de l'utilisateur de visualiser
// chaque place plutôt qu'une simple liste).
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

export default function Show({ event, tables: initialTables, unassignedGuests: initialUnassigned, constraints: initialConstraints, shapes }: Props) {
    const [tables, setTables] = useState<SeatingTable[]>(initialTables);
    const [unassignedGuests, setUnassignedGuests] = useState<Guest[]>(initialUnassigned);
    const [constraints, setConstraints] = useState<Constraint[]>(initialConstraints);
    const [newTableShape, setNewTableShape] = useState(shapes[0] ?? 'round');
    const [newTableCapacity, setNewTableCapacity] = useState('8');
    const [feedback, setFeedback] = useState<string | null>(null);
    const [autoPlacing, setAutoPlacing] = useState(false);
    const [constraintForm, setConstraintForm] = useState({ guestAKey: '', guestBKey: '', type: 'must_not_be_with' });
    const [openSeat, setOpenSeat] = useState<OpenSeat | null>(null);
    const [seatPick, setSeatPick] = useState('');

    const allGuests = [...unassignedGuests, ...tables.flatMap((table) => table.guests)];

    function toggleSeat(tableId: number, index: number) {
        setSeatPick('');
        setOpenSeat((current) => (current !== null && current.tableId === tableId && current.index === index ? null : { tableId, index }));
    }

    function closeSeat() {
        setOpenSeat(null);
        setSeatPick('');
    }

    async function handleAddTable() {
        const response = await window.axios.post<SeatingTable>(`/events/${event.id}/seating/tables`, {
            shape: newTableShape,
            capacity: Number(newTableCapacity),
        });
        setTables((current) => [...current, response.data]);
    }

    async function handleDeleteTable(table: SeatingTable) {
        await window.axios.delete(`/events/${event.id}/seating/tables/${table.id}`);
        setTables((current) => current.filter((t) => t.id !== table.id));
        setUnassignedGuests((current) => [...current, ...table.guests]);
    }

    async function handleMoveTable(table: SeatingTable, positionX: number, positionY: number) {
        setTables((current) => current.map((t) => (t.id === table.id ? { ...t, position_x: positionX, position_y: positionY } : t)));
        await window.axios.patch(`/events/${event.id}/seating/tables/${table.id}`, { position_x: positionX, position_y: positionY });
    }

    async function handleAssign(guest: Guest, table: SeatingTable) {
        try {
            await window.axios.post(`/events/${event.id}/seating/tables/${table.id}/assign`, {
                guest_type: guest.guest_type,
                guest_id: guest.id,
            });
        } catch (error) {
            const message = (error as { response?: { data?: { error?: string } } }).response?.data?.error ?? "Impossible d'affecter cet invité.";
            setFeedback(message);

            return;
        }

        setUnassignedGuests((current) => current.filter((g) => guestKey(g) !== guestKey(guest)));
        setTables((current) =>
            current.map((t) => ({
                ...t,
                guests: t.id === table.id ? [...t.guests.filter((g) => guestKey(g) !== guestKey(guest)), guest] : t.guests.filter((g) => guestKey(g) !== guestKey(guest)),
            })),
        );
        setFeedback(null);
    }

    async function handleUnassign(guest: Guest) {
        await window.axios.post(`/events/${event.id}/seating/unassign`, { guest_type: guest.guest_type, guest_id: guest.id });
        setTables((current) => current.map((t) => ({ ...t, guests: t.guests.filter((g) => guestKey(g) !== guestKey(guest)) })));
        setUnassignedGuests((current) => [...current, guest]);
    }

    async function handleSeatAssign(table: SeatingTable) {
        const guest = unassignedGuests.find((g) => guestKey(g) === seatPick);

        if (!guest) {
            return;
        }

        await handleAssign(guest, table);
        closeSeat();
    }

    async function handleSeatRemove(guest: Guest) {
        await handleUnassign(guest);
        closeSeat();
    }

    async function handleSeatChange(table: SeatingTable, previousGuest: Guest) {
        const nextGuest = unassignedGuests.find((g) => guestKey(g) === seatPick);

        if (!nextGuest) {
            return;
        }

        await handleUnassign(previousGuest);
        await handleAssign(nextGuest, table);
        closeSeat();
    }

    async function handleAutoPlace() {
        setAutoPlacing(true);

        try {
            const response = await window.axios.post<{ placed_count: number; unplaced_count: number }>(
                `/events/${event.id}/seating/auto-place`,
            );
            setFeedback(`${response.data.placed_count} invités placés, ${response.data.unplaced_count} non placés.`);
            // Recharge complète : le placement automatique touche
            // potentiellement de nombreuses tables à la fois, plus simple
            // et plus sûr que de reconstruire l'état local table par table.
            window.location.reload();
        } finally {
            setAutoPlacing(false);
        }
    }

    async function handleAddConstraint() {
        const [guestAType, guestAId] = constraintForm.guestAKey.split(':');
        const [guestBType, guestBId] = constraintForm.guestBKey.split(':');

        if (!guestAType || !guestBType) {
            return;
        }

        const response = await window.axios.post<Constraint>(`/events/${event.id}/seating/constraints`, {
            guest_a_type: guestAType,
            guest_a_id: Number(guestAId),
            guest_b_type: guestBType,
            guest_b_id: Number(guestBId),
            type: constraintForm.type,
        });
        setConstraints((current) => [...current.filter((c) => c.id !== response.data.id), response.data]);
    }

    async function handleDeleteConstraint(constraint: Constraint) {
        await window.axios.delete(`/events/${event.id}/seating/constraints/${constraint.id}`);
        setConstraints((current) => current.filter((c) => c.id !== constraint.id));
    }

    function handleTableDrop(dragEvent: DragEvent<HTMLDivElement>, table: SeatingTable) {
        dragEvent.preventDefault();
        const key = dragEvent.dataTransfer.getData('text/guest-key');
        const guest = allGuests.find((g) => guestKey(g) === key);

        if (guest) {
            void handleAssign(guest, table);
        }
    }

    function handleCanvasDrop(dragEvent: DragEvent<HTMLDivElement>) {
        dragEvent.preventDefault();
        const tableId = dragEvent.dataTransfer.getData('text/table-id');

        if (!tableId) {
            return;
        }

        const table = tables.find((t) => t.id === Number(tableId));

        if (!table) {
            return;
        }

        const canvasRect = dragEvent.currentTarget.getBoundingClientRect();
        const positionX = Math.max(0, dragEvent.clientX - canvasRect.left - table.width / 2);
        const positionY = Math.max(0, dragEvent.clientY - canvasRect.top - table.height / 2);

        void handleMoveTable(table, positionX, positionY);
    }

    return (
        <OrganizerLayout title="Plan de table" eyebrow={event.title}>
            <Head title="Plan de table" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl">Plan de table</h1>
                <div className="flex gap-3">
                    <a href={`/events/${event.id}/seating/export/plan`} className="text-sm text-accent underline underline-offset-2">
                        Exporter le plan (PDF)
                    </a>
                    <a href={`/events/${event.id}/seating/export/lists`} className="text-sm text-accent underline underline-offset-2">
                        Exporter les listes (PDF)
                    </a>
                </div>
            </div>

            {feedback !== null && <div className="mb-4 rounded-card bg-bg-deep px-4 py-3 text-sm text-ink-soft">{feedback}</div>}

            <div className="mb-6 flex flex-wrap items-end gap-3 rounded-card bg-bg p-4 ring-1 ring-line">
                <div>
                    <InputLabel htmlFor="new-table-shape">Forme</InputLabel>
                    <Select id="new-table-shape" value={newTableShape} onChange={(e) => setNewTableShape(e.target.value)}>
                        {shapes.map((shape) => (
                            <option key={shape} value={shape}>
                                {SHAPE_LABELS[shape] ?? shape}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <InputLabel htmlFor="new-table-capacity">Capacité</InputLabel>
                    <TextInput
                        id="new-table-capacity"
                        type="number"
                        min={1}
                        className="w-24"
                        value={newTableCapacity}
                        onChange={(e) => setNewTableCapacity(e.target.value)}
                    />
                </div>
                <Button className="w-auto" onClick={() => void handleAddTable()}>
                    Ajouter une table
                </Button>
                <Button variant="secondary" className="w-auto" onClick={() => void handleAutoPlace()} disabled={autoPlacing}>
                    {autoPlacing ? 'Placement…' : 'Placement automatique'}
                </Button>
            </div>

            <p className="mb-3 text-sm text-ink-soft">
                Chaque table est entourée d'une place par siège disponible. Une place vide (pointillé, « + ») s'affecte en un clic ; une place
                occupée affiche l'invité et permet de le retirer ou de le remplacer.
            </p>

            <div className="flex gap-6">
                <div
                    className="relative flex-1 overflow-auto rounded-card bg-bg-deep ring-1 ring-line"
                    style={{ height: 620 }}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={handleCanvasDrop}
                >
                    {tables.map((table) => {
                        const seats = seatsFor(table);

                        return (
                            <div key={table.id}>
                                <div
                                    draggable
                                    onDragStart={(e) => e.dataTransfer.setData('text/table-id', String(table.id))}
                                    onDragOver={(e) => e.preventDefault()}
                                    onDrop={(e) => handleTableDrop(e, table)}
                                    className={`absolute flex cursor-move flex-col items-center justify-center border-2 border-ink bg-bg p-2 text-center text-xs ${table.shape === 'round' ? 'rounded-full' : 'rounded-md'}`}
                                    style={{ left: table.position_x, top: table.position_y, width: table.width, height: table.height }}
                                >
                                    <span className="font-medium">{table.name}</span>
                                    <span className="text-ink-soft">
                                        {table.guests.length}/{table.capacity}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => void handleDeleteTable(table)}
                                        className="mt-1 text-[10px] text-danger underline"
                                    >
                                        Supprimer
                                    </button>
                                </div>

                                {seats.map((seat, index) => {
                                    const position = seatPosition(table, index, seats.length);
                                    const isOpen = openSeat !== null && openSeat.tableId === table.id && openSeat.index === index;

                                    return (
                                        <div key={`${table.id}-seat-${index}`} className="absolute z-10" style={{ left: position.left, top: position.top }}>
                                            <button
                                                type="button"
                                                onClick={() => toggleSeat(table.id, index)}
                                                title={seat.type === 'filled' ? seat.guest.name : 'Place libre'}
                                                className={`flex items-center justify-center rounded-full text-[10px] font-medium ${
                                                    seat.type === 'filled'
                                                        ? 'border border-accent bg-accent/10 text-ink'
                                                        : 'border border-dashed border-ink-soft text-ink-soft'
                                                }`}
                                                style={{ width: SEAT_SIZE, height: SEAT_SIZE }}
                                            >
                                                {seat.type === 'filled' ? initials(seat.guest.name) : '+'}
                                            </button>

                                            {isOpen && (
                                                <div className="absolute left-8 top-0 z-20 w-56 space-y-2 rounded-card bg-bg p-3 text-xs shadow-lg ring-1 ring-line">
                                                    {seat.type === 'filled' ? (
                                                        <>
                                                            <p className="font-medium">{seat.guest.name}</p>
                                                            <Button
                                                                variant="secondary"
                                                                className="w-full"
                                                                onClick={() => void handleSeatRemove(seat.guest)}
                                                            >
                                                                Retirer de la table
                                                            </Button>
                                                            <Select value={seatPick} onChange={(e) => setSeatPick(e.target.value)}>
                                                                <option value="">Remplacer par…</option>
                                                                {unassignedGuests.map((guest) => (
                                                                    <option key={guestKey(guest)} value={guestKey(guest)}>
                                                                        {guest.name}
                                                                    </option>
                                                                ))}
                                                            </Select>
                                                            <Button
                                                                className="w-full"
                                                                onClick={() => void handleSeatChange(table, seat.guest)}
                                                                disabled={seatPick === ''}
                                                            >
                                                                Remplacer
                                                            </Button>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <p className="text-ink-soft">Place libre — assigner un invité :</p>
                                                            <Select value={seatPick} onChange={(e) => setSeatPick(e.target.value)}>
                                                                <option value="">Choisir un invité</option>
                                                                {unassignedGuests.map((guest) => (
                                                                    <option key={guestKey(guest)} value={guestKey(guest)}>
                                                                        {guest.name}
                                                                    </option>
                                                                ))}
                                                            </Select>
                                                            <Button className="w-full" onClick={() => void handleSeatAssign(table)} disabled={seatPick === ''}>
                                                                Assigner
                                                            </Button>
                                                        </>
                                                    )}
                                                    <button type="button" onClick={closeSeat} className="w-full text-center text-ink-soft underline">
                                                        Fermer
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>

                <div className="w-72 shrink-0 space-y-6">
                    <div className="rounded-card bg-bg p-4 ring-1 ring-line">
                        <h2 className="mb-3 font-serif text-base italic">Invités non affectés ({unassignedGuests.length})</h2>
                        <ul className="max-h-64 space-y-1 overflow-auto">
                            {unassignedGuests.map((guest) => (
                                <li
                                    key={guestKey(guest)}
                                    draggable
                                    onDragStart={(e) => e.dataTransfer.setData('text/guest-key', guestKey(guest))}
                                    className="cursor-move rounded bg-bg-deep px-2 py-1 text-sm"
                                >
                                    {guest.name}
                                </li>
                            ))}
                            {unassignedGuests.length === 0 && <li className="text-sm text-ink-soft">Aucun invité en attente.</li>}
                        </ul>
                    </div>

                    <div className="rounded-card bg-bg p-4 ring-1 ring-line">
                        <h2 className="mb-3 font-serif text-base italic">Contraintes</h2>
                        <div className="space-y-2">
                            <Select value={constraintForm.guestAKey} onChange={(e) => setConstraintForm((c) => ({ ...c, guestAKey: e.target.value }))}>
                                <option value="">Invité A</option>
                                {allGuests.map((guest) => (
                                    <option key={guestKey(guest)} value={guestKey(guest)}>
                                        {guest.name}
                                    </option>
                                ))}
                            </Select>
                            <Select value={constraintForm.guestBKey} onChange={(e) => setConstraintForm((c) => ({ ...c, guestBKey: e.target.value }))}>
                                <option value="">Invité B</option>
                                {allGuests.map((guest) => (
                                    <option key={guestKey(guest)} value={guestKey(guest)}>
                                        {guest.name}
                                    </option>
                                ))}
                            </Select>
                            <Select value={constraintForm.type} onChange={(e) => setConstraintForm((c) => ({ ...c, type: e.target.value }))}>
                                <option value="must_not_be_with">Ne doit pas être avec</option>
                                <option value="must_be_with">Doit être avec</option>
                            </Select>
                            <Button variant="secondary" className="w-full" onClick={() => void handleAddConstraint()}>
                                Ajouter la contrainte
                            </Button>
                        </div>

                        <ul className="mt-3 space-y-1">
                            {constraints.map((constraint) => {
                                const guestA = allGuests.find((g) => guestKey(g) === `${constraint.guest_a_type}:${constraint.guest_a_id}`);
                                const guestB = allGuests.find((g) => guestKey(g) === `${constraint.guest_b_type}:${constraint.guest_b_id}`);

                                return (
                                    <li key={constraint.id} className="flex items-center justify-between text-xs">
                                        <span>
                                            {guestA?.name ?? '?'} {constraint.type === 'must_not_be_with' ? '≠' : '='} {guestB?.name ?? '?'}
                                        </span>
                                        <button type="button" onClick={() => void handleDeleteConstraint(constraint)} className="text-danger">
                                            ✕
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </div>
            </div>
        </OrganizerLayout>
    );
}
