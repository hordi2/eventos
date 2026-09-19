export type ShowIf = 'always' | 'attending' | 'not_attending';

export type AskScopeValue = 'once' | 'each_attendee';

export interface SubEventRef {
    id: number;
    title: string;
}

export interface SubEventOption extends SubEventRef {
    schedule: string;
}

export interface FieldConfig {
    show_if?: ShowIf;
    ask_scope?: AskScopeValue;
    sub_events?: SubEventRef[];
    tag_ids?: number[];
    max_length?: number;
    min?: number;
    max?: number;
    default_country?: string;
    legal_text?: string;
    // Bloc « Don » : montants en unité mineure de la devise.
    currency?: string;
    amounts?: number[];
    allow_custom?: boolean;
    cause?: string | null;
    // Bloc « Fichier joint ».
    file_types?: string[];
    max_size_mb?: number;
    // Bloc « Texte, image, vidéo » : image_url n'est jamais enregistrée,
    // le serveur la recalcule depuis image_path.
    image_path?: string;
    image_url?: string;
    image_alt?: string;
    image_width?: number;
    image_height?: number;
    video_url?: string;
    [key: string]: unknown;
}

export interface FileTypeOption {
    value: string;
    label: string;
}

export interface CurrencyOption {
    code: string;
    label: string;
}

export interface FieldOptionData {
    value: string;
    label: string;
    quota: number | null;
}

export interface FieldData {
    key: string;
    type: string;
    label: string;
    help_text: string | null;
    is_required: boolean;
    config: FieldConfig;
    options: FieldOptionData[];
}

/**
 * Question telle que la manipule le constructeur : `uid` identifie le bloc à
 * l'écran, `key` reste la clé stable des réponses, des exports et des règles.
 */
export interface BuilderField extends FieldData {
    uid: string;
}

export interface RuleCondition {
    field_key: string;
    operator: string;
    value: string;
}

export interface RuleData {
    target_field_key: string;
    action: string;
    condition: RuleCondition;
}

export interface WelcomeSettings {
    enabled: boolean;
    title: string;
    message: string;
    button_label: string;
}

export interface RsvpSettings {
    decline_enabled: boolean;
    attending_label: string;
    decline_label: string;
    max_companions: number;
}

export interface ScreenTexts {
    title: string;
    message: string;
}

export interface ThemeSettings {
    background_color: string | null;
    text_color: string | null;
    accent_color: string | null;
    button_color: string | null;
    button_text_color: string | null;
    heading_font: string;
    body_font: string;
    logo_url?: string | null;
    background_image_url?: string | null;
    header_image_url?: string | null;
    custom_css?: string | null;
}

export interface FormSettings {
    welcome: WelcomeSettings;
    rsvp: RsvpSettings;
    confirmation: ScreenTexts;
    decline_screen: ScreenTexts;
    theme: ThemeSettings;
}

export type ScreenKey = 'welcome' | 'rsvp' | 'confirmation' | 'decline_screen';

export type ImageKind = 'logo' | 'background' | 'header';

export type ThemeColorKey = 'background_color' | 'text_color' | 'accent_color' | 'button_color' | 'button_text_color';

export type Selection = { kind: 'field'; uid: string } | { kind: 'screen'; screen: ScreenKey } | { kind: 'theme' } | null;

export type Simulation = 'attending' | 'not_attending';

export type PreviewDevice = 'desktop' | 'mobile';

export interface FieldTypeOption {
    value: string;
    label: string;
    premium: boolean;
}

export interface FontOption {
    value: string;
    label: string;
    stack: string;
}

export interface TagOption {
    id: number;
    name: string;
}

export interface FormPayload {
    id: number;
    name: string;
    status: string;
    has_published_version: boolean;
    fields: FieldData[];
    rules: RuleData[];
    settings: FormSettings;
}

/** Bloc « Partager » d'un formulaire publié (PresentFormSharing). */
export interface FormSharing {
    available: boolean;
    url: string | null;
    isPreview: boolean;
    previewDays: number;
    qrCode: string | null;
    whatsappUrl: string | null;
    mailtoUrl: string | null;
}
