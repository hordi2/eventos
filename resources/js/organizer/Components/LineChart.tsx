interface Point {
    label: string;
    value: number;
}

interface Props {
    points: Point[];
    height?: number;
}

/**
 * SVG minimal fait main plutôt qu'une bibliothèque de graphiques : un seul
 * tracé linéaire, pas besoin d'interactivité ni de légende — voir T-070.
 */
export default function LineChart({ points, height = 160 }: Props) {
    const width = 600;
    const padding = 24;

    if (points.length === 0) {
        return <p className="py-8 text-center text-sm text-ink-soft">Pas encore de données.</p>;
    }

    const maxValue = Math.max(...points.map((point) => point.value), 1);
    const stepX = points.length > 1 ? (width - padding * 2) / (points.length - 1) : 0;

    const coordinates = points.map((point, index) => ({
        x: padding + index * stepX,
        y: height - padding - (point.value / maxValue) * (height - padding * 2),
    }));

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className="w-full" role="img" aria-label="Courbe cumulée">
            <polyline points={coordinates.map((c) => `${c.x},${c.y}`).join(' ')} fill="none" stroke="var(--color-accent, #111)" strokeWidth={2} />
            {coordinates.map((coordinate, index) => (
                <circle key={points[index].label} cx={coordinate.x} cy={coordinate.y} r={2.5} fill="var(--color-accent, #111)" />
            ))}
        </svg>
    );
}
