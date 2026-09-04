interface Bar {
    label: string;
    value: number;
}

interface Props {
    bars: Bar[];
    height?: number;
}

/**
 * SVG minimal fait main — voir LineChart pour le même choix.
 */
export default function BarChart({ bars, height = 160 }: Props) {
    const width = 600;
    const padding = 24;

    if (bars.length === 0) {
        return <p className="py-8 text-center text-sm text-ink-soft">Pas encore d'arrivées enregistrées.</p>;
    }

    const maxValue = Math.max(...bars.map((bar) => bar.value), 1);
    const barWidth = (width - padding * 2) / bars.length;

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className="w-full" role="img" aria-label="Courbe d'arrivée par tranche horaire">
            {bars.map((bar, index) => {
                const barHeight = (bar.value / maxValue) * (height - padding * 2);

                return (
                    <rect
                        key={bar.label}
                        x={padding + index * barWidth + 2}
                        y={height - padding - barHeight}
                        width={Math.max(barWidth - 4, 1)}
                        height={barHeight}
                        fill="var(--color-accent, #111)"
                        opacity={0.85}
                    />
                );
            })}
        </svg>
    );
}
