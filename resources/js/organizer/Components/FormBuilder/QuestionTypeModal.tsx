import Modal from '../Modal';
import { HIDDEN_FROM_CUSTOM, TYPE_DESCRIPTIONS } from './logic';
import PremiumBadge from './PremiumBadge';
import { type FieldTypeOption } from './types';

interface QuestionTypeModalProps {
    open: boolean;
    fieldTypes: FieldTypeOption[];
    isFreePlan: boolean;
    onClose: () => void;
    onPick: (type: FieldTypeOption) => void;
}

export default function QuestionTypeModal({ open, fieldTypes, isFreePlan, onClose, onPick }: QuestionTypeModalProps) {
    const choices = fieldTypes.filter((type) => !HIDDEN_FROM_CUSTOM.includes(type.value));
    const groups = [
        { title: 'Questions de base', premium: false, items: choices.filter((type) => !type.premium) },
        { title: 'Questions avancées', premium: true, items: choices.filter((type) => type.premium) },
    ];

    return (
        <Modal open={open} onClose={onClose} title="Question personnalisée" size="lg" showCloseButton>
            <div className="space-y-7">
                {groups.map((group) => (
                    <section key={group.title}>
                        <h3 className="mb-3 flex flex-wrap items-center gap-2 font-label text-[11px] tracking-[0.18em] text-ink-soft uppercase">
                            {group.title}
                            {group.premium && <PremiumBadge />}
                        </h3>
                        {group.premium && isFreePlan && (
                            <p className="mb-3 text-xs text-ink-soft">Essayez-les librement : publier un formulaire qui les contient demande un plan payant.</p>
                        )}
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {group.items.map((type) => (
                                <li key={type.value}>
                                    <button
                                        type="button"
                                        onClick={() => onPick(type)}
                                        className="flex h-full w-full flex-col items-start gap-1 rounded-control border border-line px-4 py-3 text-left transition hover:border-ink"
                                    >
                                        <span className="flex items-center gap-2 text-sm font-medium text-ink">
                                            {type.label}
                                            {type.premium && <PremiumBadge compact />}
                                        </span>
                                        <span className="text-xs text-ink-soft">{TYPE_DESCRIPTIONS[type.value] ?? ''}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </Modal>
    );
}
