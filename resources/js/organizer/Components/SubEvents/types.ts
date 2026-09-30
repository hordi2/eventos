export interface SubEventRow {
    id: number;
    title: string;
    startAt: string;
    endAt: string;
    schedule: string;
    capacity: number | null;
    room: string | null;
    speakers: string[];
    allowWaitlist: boolean;
    people: number;
    confirmed: number;
    waitlisted: number;
    conflicts: string[];
    checkInUrl: string;
}
