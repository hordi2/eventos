import { type DragEvent, useState } from 'react';

interface DropZoneProps {
    active: boolean;
    onDrop: (event: DragEvent<HTMLDivElement>) => void;
    emptyHint?: boolean;
}

export default function DropZone({ active, onDrop, emptyHint = false }: DropZoneProps) {
    const [over, setOver] = useState(false);

    if (!active && !emptyHint) {
        return <div className="h-3" aria-hidden="true" />;
    }

    return (
        <div
            onDragOver={(event) => {
                event.preventDefault();
                setOver(true);
            }}
            onDragLeave={() => setOver(false)}
            onDrop={(event) => {
                event.preventDefault();
                setOver(false);
                onDrop(event);
            }}
            className={`flex items-center justify-center rounded-card border-2 border-dashed px-4 text-center text-sm transition ${
                emptyHint ? 'my-3 min-h-28' : 'my-2 h-12'
            } ${over ? 'border-current bg-black/5' : 'border-black/20'}`}
        >
            {emptyHint ? 'Glissez une question ici, ou cliquez sur un élément de « Questions du formulaire ».' : 'Déposer ici'}
        </div>
    );
}
