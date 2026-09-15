import { useState } from 'react';
import InputLabel from '../InputLabel';
import Textarea from '../Textarea';
import TextInput from '../TextInput';
import Toggle from '../Toggle';
import { SCREEN_META } from './logic';
import SidePanel from './SidePanel';
import { type FormSettings, type RsvpSettings, type ScreenKey, type ScreenTexts, type WelcomeSettings } from './types';

interface ScreenDrawerProps {
    screen: ScreenKey;
    settings: FormSettings;
    eventTitle: string;
    onSave: (settings: FormSettings) => void;
    onCancel: () => void;
}

export default function ScreenDrawer({ screen, settings, eventTitle, onSave, onCancel }: ScreenDrawerProps) {
    const [draft, setDraft] = useState<FormSettings>(settings);
    const meta = SCREEN_META[screen];

    function setWelcome(patch: Partial<WelcomeSettings>) {
        setDraft((previous) => ({ ...previous, welcome: { ...previous.welcome, ...patch } }));
    }

    function setRsvp(patch: Partial<RsvpSettings>) {
        setDraft((previous) => ({ ...previous, rsvp: { ...previous.rsvp, ...patch } }));
    }

    function setTexts(section: 'confirmation' | 'decline_screen', patch: Partial<ScreenTexts>) {
        setDraft((previous) =>
            section === 'confirmation'
                ? { ...previous, confirmation: { ...previous.confirmation, ...patch } }
                : { ...previous, decline_screen: { ...previous.decline_screen, ...patch } },
        );
    }

    const declineToggle = (
        <div className="flex items-center justify-between gap-4">
            <span className="text-sm text-ink">Proposer la réponse « Je ne peux pas venir »</span>
            <Toggle checked={draft.rsvp.decline_enabled} onChange={(checked) => setRsvp({ decline_enabled: checked })} label="Proposer la réponse « Je ne peux pas venir »" />
        </div>
    );

    return (
        <SidePanel
            title={meta.title}
            icon={meta.icon}
            onClose={onCancel}
            footer={
                <>
                    <button type="button" onClick={onCancel} className="ml-auto rounded-pill border border-line px-5 py-2 text-sm font-medium text-ink hover:border-ink">
                        Annuler
                    </button>
                    <button type="button" onClick={() => onSave(draft)} className="rounded-pill bg-ink px-5 py-2 text-sm font-medium text-bg hover:opacity-90">
                        Enregistrer
                    </button>
                </>
            }
        >
            {screen === 'welcome' && (
                <>
                    <div className="flex items-center justify-between gap-4">
                        <span className="text-sm text-ink">Afficher un message avant les coordonnées</span>
                        <Toggle checked={draft.welcome.enabled} onChange={(checked) => setWelcome({ enabled: checked })} label="Afficher le message de bienvenue" />
                    </div>
                    <div>
                        <InputLabel htmlFor="welcome_title">Titre</InputLabel>
                        <TextInput id="welcome_title" type="text" maxLength={120} placeholder={eventTitle} value={draft.welcome.title} onChange={(event) => setWelcome({ title: event.target.value })} />
                    </div>
                    <div>
                        <InputLabel htmlFor="welcome_message">Message</InputLabel>
                        <Textarea
                            id="welcome_message"
                            maxLength={2000}
                            placeholder="Merci de prendre une minute pour nous répondre."
                            value={draft.welcome.message}
                            onChange={(event) => setWelcome({ message: event.target.value })}
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="welcome_button">Texte du bouton</InputLabel>
                        <TextInput id="welcome_button" type="text" maxLength={40} placeholder="Commencer" value={draft.welcome.button_label} onChange={(event) => setWelcome({ button_label: event.target.value })} />
                    </div>
                </>
            )}

            {screen === 'rsvp' && (
                <>
                    <p className="text-sm text-ink-soft">
                        L'adresse e-mail, le prénom, le nom et le téléphone sont toujours demandés : ils servent à la confirmation et au check-in.
                    </p>
                    {declineToggle}
                    <div>
                        <InputLabel htmlFor="rsvp_attending">Réponse positive</InputLabel>
                        <TextInput id="rsvp_attending" type="text" maxLength={80} placeholder="Je serai présent(e)" value={draft.rsvp.attending_label} onChange={(event) => setRsvp({ attending_label: event.target.value })} />
                    </div>
                    <div>
                        <InputLabel htmlFor="rsvp_decline">Réponse négative</InputLabel>
                        <TextInput
                            id="rsvp_decline"
                            type="text"
                            maxLength={80}
                            placeholder="Je ne peux pas venir"
                            value={draft.rsvp.decline_label}
                            disabled={!draft.rsvp.decline_enabled}
                            onChange={(event) => setRsvp({ decline_label: event.target.value })}
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="rsvp_companions">Accompagnants maximum par invité</InputLabel>
                        <TextInput
                            id="rsvp_companions"
                            type="number"
                            min={0}
                            max={20}
                            value={draft.rsvp.max_companions}
                            onChange={(event) => setRsvp({ max_companions: Math.max(0, Math.min(20, Math.trunc(Number(event.target.value) || 0))) })}
                        />
                        <p className="mt-2 text-xs text-ink-soft">
                            0 : chaque invité s'inscrit seul. Au-delà, il ajoute ses accompagnants par leur nom ; chacun compte pour une place et reçoit son QR code.
                        </p>
                    </div>
                </>
            )}

            {screen === 'confirmation' && (
                <>
                    <div>
                        <InputLabel htmlFor="confirmation_title">Titre</InputLabel>
                        <TextInput id="confirmation_title" type="text" maxLength={120} placeholder="Inscription confirmée" value={draft.confirmation.title} onChange={(event) => setTexts('confirmation', { title: event.target.value })} />
                    </div>
                    <div>
                        <InputLabel htmlFor="confirmation_message">Message</InputLabel>
                        <Textarea
                            id="confirmation_message"
                            maxLength={2000}
                            placeholder={`Merci, votre inscription à ${eventTitle} est enregistrée.`}
                            value={draft.confirmation.message}
                            onChange={(event) => setTexts('confirmation', { message: event.target.value })}
                        />
                    </div>
                    <p className="text-xs text-ink-soft">Un invité placé en liste d'attente voit un message dédié.</p>
                </>
            )}

            {screen === 'decline_screen' && (
                <>
                    {declineToggle}
                    <div>
                        <InputLabel htmlFor="decline_title">Titre</InputLabel>
                        <TextInput id="decline_title" type="text" maxLength={120} placeholder="Merci pour votre réponse" value={draft.decline_screen.title} onChange={(event) => setTexts('decline_screen', { title: event.target.value })} />
                    </div>
                    <div>
                        <InputLabel htmlFor="decline_message">Message</InputLabel>
                        <Textarea
                            id="decline_message"
                            maxLength={2000}
                            value={draft.decline_screen.message}
                            onChange={(event) => setTexts('decline_screen', { message: event.target.value })}
                        />
                    </div>
                    <p className="text-xs text-ink-soft">Un refus ne prend aucune place et apparaît dans le segment « Déclinés ».</p>
                </>
            )}
        </SidePanel>
    );
}
