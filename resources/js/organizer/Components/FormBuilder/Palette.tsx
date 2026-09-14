import BuilderIcon from './BuilderIcon';
import { PALETTE, type PaletteEntry } from './logic';
import PaletteItem from './PaletteItem';
import SidePanel from './SidePanel';

interface PaletteProps {
    onActivate: (entry: PaletteEntry) => void;
    onDragStateChange: (dragging: boolean) => void;
}

export default function Palette({ onActivate, onDragStateChange }: PaletteProps) {
    return (
        <SidePanel title="Constructeur de formulaire" icon="form">
            <div>
                <h3 className="font-sans text-base font-semibold text-ink">Questions du formulaire</h3>
                <p className="mt-2 flex items-start gap-2 text-sm text-ink-soft">
                    <BuilderIcon name="hand" className="mt-0.5 h-4 w-4" />
                    Glissez un élément dans l'aperçu, ou cliquez dessus pour l'ajouter à la fin.
                </p>
            </div>
            <ul className="space-y-2">
                {PALETTE.map((entry) => (
                    <PaletteItem key={entry.id} entry={entry} onActivate={onActivate} onDragStateChange={onDragStateChange} />
                ))}
            </ul>
        </SidePanel>
    );
}
