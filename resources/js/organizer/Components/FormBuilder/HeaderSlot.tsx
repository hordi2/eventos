import BuilderIcon from './BuilderIcon';

interface HeaderSlotProps {
    headerUrl: string | null | undefined;
    onClick: () => void;
}

/**
 * Bandeau d'en-tête dans l'aperçu : la photo en largeur que l'invité voit en
 * premier, le logo posé par-dessus.
 */
export default function HeaderSlot({ headerUrl, onClick }: HeaderSlotProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`group relative block w-full overflow-hidden rounded-card transition ${headerUrl ? '' : 'border-2 border-dashed border-current opacity-60 hover:opacity-90'}`}
        >
            {headerUrl ? (
                <>
                    <img src={headerUrl} alt="Bandeau du formulaire" className="h-36 w-full object-cover sm:h-44" />
                    <span className="absolute inset-x-0 bottom-0 bg-black/40 py-1.5 font-label text-[10px] tracking-[0.18em] text-white uppercase opacity-0 transition group-hover:opacity-100">
                        Cliquez pour changer le bandeau
                    </span>
                </>
            ) : (
                <span className="flex h-20 items-center justify-center gap-3">
                    <BuilderIcon name="media" className="h-6 w-6" />
                    <span className="font-label text-xs tracking-[0.14em] uppercase">Ajouter un bandeau d'en-tête (photo)</span>
                </span>
            )}
        </button>
    );
}
