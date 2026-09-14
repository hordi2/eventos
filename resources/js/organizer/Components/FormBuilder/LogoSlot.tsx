import BuilderIcon from './BuilderIcon';

interface LogoSlotProps {
    logoUrl: string | null | undefined;
    onClick: () => void;
}

export default function LogoSlot({ logoUrl, onClick }: LogoSlotProps) {
    return (
        <div className="flex justify-center">
            <button type="button" onClick={onClick} className="flex flex-col items-center gap-2 rounded-card px-6 py-3 transition hover:bg-black/5">
                {logoUrl ? (
                    <img src={logoUrl} alt="Logo du formulaire" className="h-14 w-auto max-w-[240px] object-contain" />
                ) : (
                    <span className="flex items-center gap-3 rounded-card border-2 border-dashed border-current px-6 py-3 opacity-60">
                        <BuilderIcon name="plus" className="h-7 w-7" />
                        <span className="text-left font-label text-base leading-tight font-semibold tracking-[0.06em] uppercase">
                            Votre logo
                            <br />
                            ici
                        </span>
                    </span>
                )}
                <span className="font-label text-[10px] tracking-[0.18em] uppercase opacity-70">
                    {logoUrl ? 'Cliquez pour changer le logo' : 'Cliquez pour ajouter un logo'}
                </span>
            </button>
        </div>
    );
}
