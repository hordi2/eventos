import InputLabel from '../InputLabel';
import Select from '../Select';
import BuilderIcon from './BuilderIcon';
import ColorField from './ColorField';
import { COLOR_FIELDS, fontStackFor, IMAGE_SLOTS, PANEL_SECTION_TITLE } from './logic';
import SidePanel from './SidePanel';
import SoonBadge from './SoonBadge';
import { type FontOption, type ImageKind, type ThemeColorKey, type ThemeSettings } from './types';

interface ThemeDrawerProps {
    theme: ThemeSettings;
    fonts: FontOption[];
    onChange: (patch: Partial<ThemeSettings>) => void;
    onReset: () => void;
    onClose: () => void;
    onPickImage: (kind: ImageKind) => void;
}

/**
 * Thème du parcours invité. Chaque changement s'applique tout de suite à
 * l'aperçu puis s'enregistre automatiquement, comme le reste du constructeur.
 */
export default function ThemeDrawer({ theme, fonts, onChange, onReset, onClose, onPickImage }: ThemeDrawerProps) {
    function setColor(key: ThemeColorKey, value: string | null) {
        const patch: Partial<ThemeSettings> = {};
        patch[key] = value;
        onChange(patch);
    }

    return (
        <SidePanel
            title="Thème du formulaire"
            icon="palette"
            onClose={onClose}
            footer={
                <>
                    <button type="button" onClick={onReset} className="mr-auto text-sm text-ink-soft underline hover:text-ink">
                        Réinitialiser les couleurs et polices
                    </button>
                    <button type="button" onClick={onClose} className="rounded-pill bg-ink px-5 py-2 text-sm font-medium text-bg hover:opacity-90">
                        Terminé
                    </button>
                </>
            }
        >
            <section>
                <h3 className={PANEL_SECTION_TITLE}>Images</h3>
                <div className="space-y-3">
                    {IMAGE_SLOTS.map((slot) => {
                        const url = slot.kind === 'logo' ? theme.logo_url : theme.background_image_url;

                        return (
                            <div key={slot.kind} className="flex items-center gap-3">
                                <span className="flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded-control bg-bg-alt ring-1 ring-line">
                                    {url ? (
                                        <img src={url} alt="" className={`h-full w-full ${slot.kind === 'logo' ? 'object-contain p-1' : 'object-cover'}`} />
                                    ) : (
                                        <BuilderIcon name="media" className="h-5 w-5 text-ink-soft" />
                                    )}
                                </span>
                                <span className="min-w-0 flex-1 text-sm text-ink">{slot.label}</span>
                                <button
                                    type="button"
                                    onClick={() => onPickImage(slot.kind)}
                                    className="rounded-pill border border-line px-3 py-1.5 text-xs font-medium text-ink hover:border-ink"
                                >
                                    {url ? 'Changer' : 'Ajouter'}
                                </button>
                            </div>
                        );
                    })}
                </div>
            </section>

            <section>
                <h3 className={PANEL_SECTION_TITLE}>Couleurs</h3>
                <div className="space-y-4">
                    {COLOR_FIELDS.map((color) => (
                        <ColorField
                            key={color.key}
                            id={`theme_${color.key}`}
                            label={color.label}
                            value={theme[color.key]}
                            fallback={color.fallback}
                            onChange={(value) => setColor(color.key, value)}
                        />
                    ))}
                </div>
            </section>

            <section>
                <h3 className={PANEL_SECTION_TITLE}>Polices</h3>
                <div className="space-y-5">
                    <div>
                        <InputLabel htmlFor="theme_heading_font">Titres</InputLabel>
                        <Select id="theme_heading_font" value={theme.heading_font} onChange={(event) => onChange({ heading_font: event.target.value })}>
                            {fonts.map((font) => (
                                <option key={font.value} value={font.value}>
                                    {font.label}
                                </option>
                            ))}
                        </Select>
                        <p className="mt-2 text-xl text-ink" style={{ fontFamily: fontStackFor(fonts, theme.heading_font) }}>
                            Soirée des partenaires
                        </p>
                    </div>
                    <div>
                        <InputLabel htmlFor="theme_body_font">Texte</InputLabel>
                        <Select id="theme_body_font" value={theme.body_font} onChange={(event) => onChange({ body_font: event.target.value })}>
                            {fonts.map((font) => (
                                <option key={font.value} value={font.value}>
                                    {font.label}
                                </option>
                            ))}
                        </Select>
                        <p className="mt-2 text-sm text-ink" style={{ fontFamily: fontStackFor(fonts, theme.body_font) }}>
                            Merci de confirmer votre présence avant le 30 septembre.
                        </p>
                    </div>
                </div>
            </section>

            <section>
                <h3 className={`${PANEL_SECTION_TITLE} flex items-center gap-2`}>
                    CSS personnalisé <SoonBadge />
                </h3>
                <textarea
                    disabled
                    rows={3}
                    aria-label="CSS personnalisé (bientôt disponible)"
                    placeholder=".bouton { … }"
                    className="w-full rounded-control border border-line bg-bg-alt px-3 py-2 font-mono text-xs text-ink-soft"
                />
            </section>
        </SidePanel>
    );
}
