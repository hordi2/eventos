import { PREVIEW_INPUT, type PreviewTheme } from './logic';
import ScreenBlock from './ScreenBlock';
import { type RsvpSettings, type Simulation } from './types';

interface IdentityBlockProps {
    eventTitle: string;
    rsvp: RsvpSettings;
    phoneRequired: boolean;
    simulation: Simulation;
    selected: boolean;
    theme: PreviewTheme;
    onOpen: () => void;
    onSimulationChange: (simulation: Simulation) => void;
}

export default function IdentityBlock({ eventTitle, rsvp, phoneRequired, simulation, selected, theme, onOpen, onSimulationChange }: IdentityBlockProps) {
    return (
        <ScreenBlock title="Coordonnées et réponse" icon="identity" selected={selected} onOpen={onOpen}>
            <p className="mb-1 text-sm opacity-70">{eventTitle}</p>
            <h2 className="mb-6 text-2xl" style={theme.heading}>
                Votre inscription
            </h2>

            <div className="space-y-4">
                <div>
                    <p className="mb-1.5 text-sm font-medium">Adresse e-mail *</p>
                    <input type="email" aria-label="Adresse e-mail (aperçu)" readOnly tabIndex={-1} className={PREVIEW_INPUT} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <p className="mb-1.5 text-sm font-medium">Prénom</p>
                        <input type="text" aria-label="Prénom (aperçu)" readOnly tabIndex={-1} className={PREVIEW_INPUT} />
                    </div>
                    <div>
                        <p className="mb-1.5 text-sm font-medium">Nom</p>
                        <input type="text" aria-label="Nom (aperçu)" readOnly tabIndex={-1} className={PREVIEW_INPUT} />
                    </div>
                </div>
                <div>
                    <p className="mb-1.5 text-sm font-medium">Téléphone{phoneRequired ? ' *' : ''}</p>
                    <input type="tel" aria-label="Téléphone (aperçu)" readOnly tabIndex={-1} placeholder="+243 8xx xxx xxx" className={PREVIEW_INPUT} />
                </div>

                {rsvp.decline_enabled && (
                    <fieldset>
                        <legend className="mb-2 text-sm font-medium">Votre réponse *</legend>
                        <div className="space-y-2">
                            {(['attending', 'not_attending'] as const).map((choice) => (
                                <label key={choice} className="flex cursor-pointer items-center gap-3 rounded-control border border-black/15 px-4 py-3 text-sm">
                                    <input
                                        type="radio"
                                        name="apercu_reponse"
                                        checked={simulation === choice}
                                        onChange={() => onSimulationChange(choice)}
                                    />
                                    {choice === 'attending' ? rsvp.attending_label : rsvp.decline_label}
                                </label>
                            ))}
                        </div>
                        <p className="mt-2 text-xs opacity-60">
                            Dans l'aperçu, ce choix simule la réponse de l'invité et montre les questions qui lui sont posées.
                        </p>
                    </fieldset>
                )}
            </div>
        </ScreenBlock>
    );
}
