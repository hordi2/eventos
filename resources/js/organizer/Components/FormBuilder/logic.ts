import { type RequestPayload } from '@inertiajs/core';
import { type CSSProperties } from 'react';
import { type BuilderIconName } from './BuilderIcon';
import {
    type BuilderField,
    type FieldConfig,
    type FieldData,
    type FieldOptionData,
    type FontOption,
    type FormSettings,
    type ImageKind,
    type RuleCondition,
    type RuleData,
    type ScreenKey,
    type ShowIf,
    type Simulation,
    type SubEventOption,
    type ThemeColorKey,
    type ThemeSettings,
} from './types';

export const TYPES_WITH_OPTIONS = ['single_choice', 'multiple_choice', 'meal_choice', 'dropdown'];

// Types déjà proposés par leur propre élément de la palette.
export const HIDDEN_FROM_CUSTOM = ['informational_text', 'meal_choice', 'sub_events'];

export const PALETTE_DRAG_TYPE = 'application/x-itaza-palette';

export const MOVE_DRAG_TYPE = 'application/x-itaza-bloc';

export const HEX_PATTERN = /^#[0-9a-fA-F]{6}$/;

export const PANEL_SECTION_TITLE = 'mb-3 block font-label text-[11px] tracking-[0.18em] text-ink-soft uppercase';

export const PREVIEW_INPUT = 'w-full rounded-control border border-black/15 bg-transparent px-3 py-2 text-sm text-current focus:border-current focus:outline-none';

export const SHOW_IF_LABELS: Record<ShowIf, string> = {
    always: 'Toujours',
    attending: 'Invité présent',
    not_attending: 'Invité qui ne peut pas venir',
};

export const OPERATORS: { value: string; label: string }[] = [
    { value: 'is', label: 'est' },
    { value: 'is_not', label: "n'est pas" },
    { value: 'contains', label: 'contient' },
    { value: 'does_not_contain', label: 'ne contient pas' },
    { value: 'greater_than', label: 'est supérieure à' },
    { value: 'less_than', label: 'est inférieure à' },
    { value: 'is_empty', label: "n'est pas remplie" },
    { value: 'is_not_empty', label: 'est remplie' },
];

export const RULE_ACTIONS: { value: string; label: string }[] = [
    { value: 'show', label: 'Afficher cette question' },
    { value: 'hide', label: 'Masquer cette question' },
    { value: 'require', label: 'Rendre cette question obligatoire' },
];

export const TYPE_DESCRIPTIONS: Record<string, string> = {
    short_text: "Une réponse courte : fonction, nom d'entreprise…",
    long_text: 'Un texte libre sur plusieurs lignes.',
    number: 'Un nombre, avec minimum et maximum.',
    email: 'Une adresse e-mail.',
    phone: 'Un numéro de téléphone au format international.',
    date: 'Une date du calendrier.',
    single_choice: 'Un seul choix parmi une liste.',
    multiple_choice: 'Plusieurs choix possibles.',
    yes_no: 'Oui ou non.',
    consent: 'Une case à cocher horodatée : autorisation photo, données…',
    dropdown: 'Un choix dans une liste déroulante compacte.',
    date_time: "Une date et une heure : heure d'arrivée, créneau…",
    url: 'Un lien web : site, portfolio…',
    social_profile: 'Un profil LinkedIn, Instagram, Facebook…',
    quantity: 'Une quantité bornée : places, tickets, chambres…',
    postal_address: 'Une adresse complète : rue, ville, pays.',
    sub_events: "Les sessions auxquelles l'invité et ses accompagnants participent.",
};

export const SCREEN_META: Record<ScreenKey, { title: string; icon: BuilderIconName }> = {
    welcome: { title: 'Message de bienvenue', icon: 'welcome' },
    rsvp: { title: 'Coordonnées et réponse', icon: 'identity' },
    confirmation: { title: 'Écran de confirmation', icon: 'confirmation' },
    decline_screen: { title: 'Écran « Je ne peux pas venir »', icon: 'decline' },
};

export const COLOR_FIELDS: { key: ThemeColorKey; label: string; fallback: string }[] = [
    { key: 'background_color', label: 'Fond de la page', fallback: '#ffffff' },
    { key: 'text_color', label: 'Texte', fallback: '#1b1611' },
    { key: 'accent_color', label: 'Liens et contours actifs', fallback: '#0a0a0a' },
    { key: 'button_color', label: 'Boutons', fallback: '#1b1611' },
    { key: 'button_text_color', label: 'Texte des boutons', fallback: '#ffffff' },
];

export const IMAGE_SLOTS: { kind: ImageKind; label: string }[] = [
    { kind: 'background', label: 'Image de fond' },
    { kind: 'logo', label: 'Logo' },
];

export interface BlockBlueprint {
    type: string;
    label: string;
    help_text?: string | null;
    is_required?: boolean;
    config?: FieldConfig;
    options?: FieldOptionData[];
}

export type PaletteAction =
    | { kind: 'field'; blueprint: BlockBlueprint }
    | { kind: 'custom' }
    | { kind: 'subEvents' }
    | { kind: 'screen'; screen: ScreenKey }
    | { kind: 'soon' };

export interface PaletteEntry {
    id: string;
    label: string;
    icon: BuilderIconName;
    action: PaletteAction;
    premium?: boolean;
}

export const PALETTE: PaletteEntry[] = [
    { id: 'welcome', label: 'Message de bienvenue', icon: 'welcome', action: { kind: 'screen', screen: 'welcome' } },
    {
        id: 'meal',
        label: 'Préférences alimentaires',
        icon: 'meal',
        action: {
            kind: 'field',
            blueprint: {
                type: 'meal_choice',
                label: 'Préférences alimentaires',
                options: [
                    { value: 'viande', label: 'Viande', quota: null },
                    { value: 'poisson', label: 'Poisson', quota: null },
                    { value: 'vegetarien', label: 'Végétarien', quota: null },
                ],
            },
        },
    },
    {
        id: 'text',
        label: 'Texte, image, vidéo',
        icon: 'media',
        action: { kind: 'field', blueprint: { type: 'informational_text', label: 'Ajoutez ici votre texte.', config: { show_if: 'always' } } },
    },
    { id: 'custom', label: 'Question personnalisée', icon: 'question', premium: true, action: { kind: 'custom' } },
    { id: 'sub_event', label: 'Événements secondaires', icon: 'subEvent', action: { kind: 'subEvents' } },
    { id: 'donation', label: 'Don en espèces ou en nature', icon: 'donation', action: { kind: 'soon' } },
    {
        id: 'note',
        label: "Note de l'invité",
        icon: 'note',
        action: { kind: 'field', blueprint: { type: 'long_text', label: 'Un mot pour les organisateurs', config: { show_if: 'always' } } },
    },
    { id: 'donor', label: 'Informations sur le donateur', icon: 'donor', action: { kind: 'soon' } },
    { id: 'confirmation', label: 'Écran de confirmation', icon: 'confirmation', action: { kind: 'screen', screen: 'confirmation' } },
    { id: 'decline', label: 'Écran « Je ne peux pas venir »', icon: 'decline', action: { kind: 'screen', screen: 'decline_screen' } },
];

let uidCounter = 0;

export function nextUid(): string {
    uidCounter += 1;

    return `bloc_${uidCounter}`;
}

export function slugify(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 40);
}

export function uniqueKey(base: string, fields: FieldData[]): string {
    const root = slugify(base) || 'question';
    const used = new Set(fields.map((field) => field.key));
    let key = root;
    let suffix = 2;

    while (used.has(key)) {
        key = `${root}_${suffix}`;
        suffix += 1;
    }

    return key;
}

export function optionValue(option: FieldOptionData): string {
    return option.value || slugify(option.label) || 'option';
}

/**
 * Donne à chaque option une valeur technique unique dès l'enregistrement du
 * bloc : les règles conditionnelles comparent cette valeur, elle ne doit
 * donc plus changer ensuite, même si le libellé est renommé.
 */
export function withOptionValues(options: FieldOptionData[]): FieldOptionData[] {
    const used = new Set<string>();

    return options
        .filter((option) => option.label.trim() !== '')
        .map((option) => {
            const root = optionValue(option);
            let value = root;
            let suffix = 2;

            while (used.has(value)) {
                value = `${root}_${suffix}`;
                suffix += 1;
            }

            used.add(value);

            return { ...option, label: option.label.trim(), value };
        });
}

export function toBuilderField(field: FieldData): BuilderField {
    return {
        ...field,
        uid: nextUid(),
        // PHP sérialise une configuration vide en tableau JSON ([]).
        config: Array.isArray(field.config) ? {} : { ...field.config },
        options: field.options.map((option) => ({ ...option })),
    };
}

export function createField(blueprint: BlockBlueprint, fields: FieldData[]): BuilderField {
    const defaultOptions: FieldOptionData[] = TYPES_WITH_OPTIONS.includes(blueprint.type)
        ? [
              { value: 'option_1', label: 'Option 1', quota: null },
              { value: 'option_2', label: 'Option 2', quota: null },
          ]
        : [];

    return {
        uid: nextUid(),
        key: uniqueKey(blueprint.label, fields),
        type: blueprint.type,
        label: blueprint.label,
        help_text: blueprint.help_text ?? null,
        is_required: blueprint.is_required ?? false,
        config: { ...(blueprint.config ?? {}) },
        options: (blueprint.options ?? defaultOptions).map((option) => ({ ...option })),
    };
}

export function blueprintForType(type: string): BlockBlueprint {
    return { type, label: 'Nouvelle question' };
}

/**
 * Bloc « Événements secondaires » : propose d'emblée toutes les sessions,
 * que l'organisateur peut ensuite restreindre dans les réglages du bloc.
 */
export function subEventsBlueprint(subEvents: SubEventOption[]): BlockBlueprint {
    return {
        type: 'sub_events',
        label: 'À quelles sessions participerez-vous ?',
        config: { sub_events: subEvents.map(({ id, title }) => ({ id, title })) },
    };
}

export function duplicateField(field: BuilderField, fields: FieldData[]): BuilderField {
    return {
        ...field,
        uid: nextUid(),
        key: uniqueKey(`${field.key}_copie`, fields),
        label: `${field.label} (copie)`,
        config: { ...field.config, tag_ids: field.config.tag_ids ? [...field.config.tag_ids] : undefined },
        options: field.options.map((option) => ({ ...option })),
    };
}

function isEmpty(value: unknown): boolean {
    return value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0);
}

export function evaluateCondition(condition: RuleCondition, answers: Record<string, unknown>): boolean {
    const value = answers[condition.field_key];
    const target = condition.value;

    switch (condition.operator) {
        case 'is':
            return String(value ?? '') === target;
        case 'is_not':
            return String(value ?? '') !== target;
        case 'contains':
            return Array.isArray(value) ? value.map(String).includes(target) : String(value ?? '').includes(target);
        case 'does_not_contain':
            return Array.isArray(value) ? !value.map(String).includes(target) : !String(value ?? '').includes(target);
        case 'greater_than':
            return Number(value) > Number(target);
        case 'less_than':
            return Number(value) < Number(target);
        case 'is_empty':
            return isEmpty(value);
        case 'is_not_empty':
            return !isEmpty(value);
        default:
            return false;
    }
}

export interface FieldState {
    visible: boolean;
    required: boolean;
}

/**
 * Reprend EvaluateFormVisibility pour l'aperçu seulement : une soumission
 * réelle reste toujours tranchée par le serveur. Le critère des tags n'est
 * pas simulé, l'aperçu ne représentant aucun contact précis.
 */
export function computeVisibility(
    fields: FieldData[],
    rules: RuleData[],
    answers: Record<string, unknown>,
    simulation: Simulation,
): Record<string, FieldState> {
    const state: Record<string, FieldState> = {};

    fields.forEach((field) => {
        state[field.key] = { visible: true, required: field.is_required };
    });

    rules.forEach((rule) => {
        const target = state[rule.target_field_key];

        if (!target) {
            return;
        }

        const matched = evaluateCondition(rule.condition, answers);

        if (rule.action === 'show') {
            target.visible = matched;
        } else if (rule.action === 'hide') {
            target.visible = !matched;
        } else if (rule.action === 'require') {
            target.required = matched;
        }
    });

    fields.forEach((field) => {
        const showIf = field.config.show_if ?? 'attending';
        const attending = simulation === 'attending';

        if ((showIf === 'attending' && !attending) || (showIf === 'not_attending' && attending)) {
            state[field.key].visible = false;
        }
    });

    return state;
}

export interface PreviewTheme {
    page: CSSProperties;
    heading: CSSProperties;
    button: CSSProperties;
}

function fontStack(fonts: FontOption[], value: string, fallback: string): string {
    return fonts.find((font) => font.value === value)?.stack ?? fallback;
}

/**
 * Mêmes valeurs par défaut que le parcours invité (guest.css) : sans couleur
 * de bouton, le bouton reprend la couleur du texte.
 */
export function previewTheme(theme: ThemeSettings, fonts: FontOption[]): PreviewTheme {
    return {
        page: {
            backgroundColor: theme.background_color ?? '#ffffff',
            backgroundImage: theme.background_image_url ? `url("${theme.background_image_url}")` : undefined,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            color: theme.text_color ?? '#1b1611',
            fontFamily: fontStack(fonts, theme.body_font, "'Plus Jakarta Sans', sans-serif"),
        },
        heading: { fontFamily: fontStack(fonts, theme.heading_font, "'Bodoni Moda', serif") },
        button: {
            backgroundColor: theme.button_color ?? theme.text_color ?? '#1b1611',
            color: theme.button_text_color ?? '#ffffff',
        },
    };
}

export function fontStackFor(fonts: FontOption[], value: string): string {
    return fontStack(fonts, value, 'inherit');
}

export function resetTheme(theme: ThemeSettings): ThemeSettings {
    return {
        ...theme,
        background_color: null,
        text_color: null,
        accent_color: null,
        button_color: null,
        button_text_color: null,
        heading_font: 'bodoni',
        body_font: 'jakarta',
    };
}

export function normalizeSettings(settings: FormSettings): FormSettings {
    return {
        welcome: { ...settings.welcome },
        rsvp: { ...settings.rsvp, max_companions: Number(settings.rsvp.max_companions ?? 0) },
        confirmation: { ...settings.confirmation },
        decline_screen: { ...settings.decline_screen },
        theme: { ...settings.theme },
    };
}

/**
 * Charge utile de FormController::store/update. Les URL d'images n'y
 * figurent jamais : seul l'envoi d'une image écrit son chemin.
 */
export function buildPayload(name: string, fields: BuilderField[], rules: RuleData[], settings: FormSettings): RequestPayload {
    const { theme } = settings;

    const payload = {
        name,
        fields: fields.map(({ uid, ...field }) => field),
        rules: rules
            .filter((rule) => rule.condition.field_key !== '')
            .map((rule) => ({
                target_field_key: rule.target_field_key,
                action: rule.action,
                condition_group: { combinator: 'and', conditions: [rule.condition] },
            })),
        settings: {
            welcome: settings.welcome,
            rsvp: settings.rsvp,
            confirmation: settings.confirmation,
            decline_screen: settings.decline_screen,
            theme: {
                background_color: theme.background_color,
                text_color: theme.text_color,
                accent_color: theme.accent_color,
                button_color: theme.button_color,
                button_text_color: theme.button_text_color,
                heading_font: theme.heading_font,
                body_font: theme.body_font,
            },
        },
    };

    // La configuration d'un champ accepte des valeurs de types variés que
    // FormDataConvertible ne sait pas décrire ; la charge reste du JSON simple.
    return payload as unknown as RequestPayload;
}
