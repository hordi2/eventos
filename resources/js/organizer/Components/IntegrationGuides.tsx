import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { type SharedProps } from '../types';
import N8nConnection, { type N8nState } from './N8nConnection';

interface EventOption {
    value: string;
    label: string;
}

interface Props {
    apiBaseUrl: string;
    n8n: N8nState;
    availableEvents: EventOption[];
    /**
     * Outil pour lequel la clé qui vient d'être créée a été demandée. Porté
     * par la page parente pour que la section « Clés API » plus bas
     * n'affiche pas la même clé une seconde fois.
     */
    connectionTool: string | null;
    onConnectionToolChange: (tool: string) => void;
}

type GuideKey = 'zapier' | 'n8n';

interface Guide {
    key: GuideKey;
    name: string;
    tagline: string;
    /** Où coller la clé dans l'outil, une fois celle-ci créée ici. */
    connectionSteps: string[];
    /** Comment recevoir les événements Itaza une fois connecté. */
    triggerSteps: string[];
}

const GUIDES: Guide[] = [
    {
        key: 'zapier',
        name: 'Zapier',
        tagline: 'Connectez Itaza à plus de 6 000 applications sans écrire de code.',
        connectionSteps: [
            'Créez la clé ci-dessus, puis copiez-la.',
            'Dans Zapier, ajoutez une action « Webhooks by Zapier » et choisissez « Custom Request ».',
            'Dans « Headers », ajoutez Authorization avec la valeur : Bearer suivi de votre clé.',
            'Testez sur l’URL de connexion affichée ci-dessous : Zapier doit vous renvoyer votre nom et votre organisation.',
        ],
        triggerSteps: [
            'Créez un Zap dont le déclencheur est « Webhooks by Zapier » → « Catch Hook », et copiez l’URL fournie.',
            'Dans « Webhooks sortants » plus bas, collez cette URL et cochez les événements voulus.',
            'Dans Zapier, cliquez sur « Test trigger » puis déclenchez une inscription de test pour qu’il découvre les champs.',
        ],
    },
    {
        key: 'n8n',
        name: 'n8n',
        tagline: 'Orchestrez vos automatisations sur votre propre serveur n8n.',
        connectionSteps: [
            'Dans n8n, ouvrez Réglages → n8n API et créez une clé.',
            'Collez-la ci-dessus avec l’adresse de votre instance : Itaza vérifie la clé avant de l’enregistrer.',
            'Vos workflows contenant un nœud « Webhook » apparaissent alors dans la liste.',
            'Choisissez les événements puis cliquez sur « Brancher » : Itaza lit l’URL du webhook dans n8n, vous n’avez rien à recopier.',
        ],
        triggerSteps: [
            'Chaque livraison arrive signée : vérifiez l’en-tête X-Itaza-Signature dans un nœud « Crypto » si vous voulez authentifier la source.',
            'Pour appeler Itaza depuis n8n (sens inverse), créez une clé API plus bas et utilisez un identifiant « Header Auth » : Authorization = Bearer votre-clé.',
        ],
    },
];

function CodeLine({ children }: { children: string }) {
    return <code className="block overflow-x-auto rounded-control bg-bg-deep px-3 py-2 text-xs break-all">{children}</code>;
}

function Steps({ steps }: { steps: string[] }) {
    return (
        <ol className="space-y-3 text-sm text-ink-soft">
            {steps.map((step, index) => (
                <li key={step} className="flex gap-3">
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent/10 text-xs text-accent">
                        {index + 1}
                    </span>
                    {step}
                </li>
            ))}
        </ol>
    );
}

/**
 * Connexion de Zapier et n8n par clé API, puis réception des événements via
 * les webhooks sortants déjà présents plus bas dans la page.
 */
export default function IntegrationGuides({
    apiBaseUrl,
    n8n,
    availableEvents,
    connectionTool,
    onConnectionToolChange,
}: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [openGuide, setOpenGuide] = useState<GuideKey | null>(null);
    const [copied, setCopied] = useState(false);

    function createKeyFor(guide: Guide) {
        onConnectionToolChange(guide.name);
        setOpenGuide(guide.key);
        router.post('/settings/api/tokens', { name: `Connexion ${guide.name}` }, { preserveScroll: true });
    }

    async function copyKey(key: string) {
        await navigator.clipboard.writeText(key);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    return (
        <section className="mb-14">
            <h2 className="mb-1 font-serif text-xl italic">Zapier et n8n</h2>
            <p className="mb-6 max-w-2xl text-sm text-ink-soft">
                Pour n8n, collez ici la clé API de votre instance : Itaza y lit vos workflows et branche celui que vous
                choisissez. Zapier ne fournit pas de clé de ce type à ses utilisateurs — la connexion s'y fait par une
                clé Itaza et l'URL de webhook du Zap.
            </p>

            {flash.plainToken && connectionTool && (
                <div className="mb-6 rounded-card border border-accent/30 bg-accent/5 p-4">
                    <p className="mb-2 text-sm font-medium">
                        Clé de connexion {connectionTool} — copiez-la maintenant, elle ne sera plus jamais affichée.
                    </p>
                    <code className="block truncate rounded-control bg-bg-deep px-3 py-2 text-sm">{flash.plainToken}</code>
                    <button
                        type="button"
                        onClick={() => copyKey(flash.plainToken as string)}
                        className="mt-2 text-sm underline underline-offset-2"
                    >
                        {copied ? 'Clé copiée' : 'Copier la clé'}
                    </button>
                </div>
            )}

            <div className="space-y-3">
                {GUIDES.map((guide) => (
                    <div key={guide.key} className="rounded-card border border-line">
                        <div className="flex flex-wrap items-center justify-between gap-4 p-4">
                            <div>
                                <p className="font-label text-xs tracking-[0.14em] text-ink uppercase">{guide.name}</p>
                                <p className="mt-1 text-sm text-ink-soft">{guide.tagline}</p>
                            </div>
                            <div className="flex shrink-0 flex-wrap gap-2">
                                {/* n8n se connecte dans l'autre sens : on y colle SA clé
                                    (bloc ci-dessous), pas une clé Itaza. */}
                                {guide.key !== 'n8n' && (
                                    <button
                                        type="button"
                                        onClick={() => createKeyFor(guide)}
                                        className="rounded-pill bg-ink px-5 py-2 text-sm font-medium text-bg hover:opacity-90"
                                    >
                                        Créer la clé de connexion
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => setOpenGuide(openGuide === guide.key ? null : guide.key)}
                                    className="rounded-pill border border-line px-5 py-2 text-sm text-ink hover:border-ink"
                                >
                                    {openGuide === guide.key ? 'Masquer' : 'Instructions'}
                                </button>
                            </div>
                        </div>

                        {guide.key === 'n8n' && <N8nConnection n8n={n8n} availableEvents={availableEvents} />}

                        {openGuide === guide.key && (
                            <div className="space-y-6 border-t border-line p-4">
                                <div>
                                    <p className="mb-3 text-sm font-medium text-ink">1. Connecter {guide.name} à Itaza</p>
                                    <Steps steps={guide.connectionSteps} />
                                    <p className="mt-3 mb-1 text-xs text-ink-soft">URL de connexion à tester :</p>
                                    <CodeLine>{`GET ${apiBaseUrl}/me`}</CodeLine>
                                </div>

                                <div>
                                    <p className="mb-3 text-sm font-medium text-ink">2. Recevoir les événements Itaza</p>
                                    <Steps steps={guide.triggerSteps} />
                                </div>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            <details className="mt-4">
                <summary className="cursor-pointer text-sm text-ink-soft underline underline-offset-4 hover:text-ink">
                    API d'abonnement (pour une app Zapier publiée)
                </summary>

                <div className="mt-4 space-y-4 rounded-card border border-line p-4 text-sm text-ink-soft">
                    <p>
                        Ces points d'entrée permettent à un outil externe de gérer ses abonnements lui-même. Même
                        authentification : en-tête
                        <code className="mx-1 rounded bg-bg-deep px-1.5 py-0.5 text-xs">Authorization: Bearer …</code>.
                    </p>

                    <div>
                        <p className="mb-1 text-ink">S'abonner à un événement</p>
                        <CodeLine>{`POST ${apiBaseUrl}/hooks  {"target_url": "https://…", "event": "registration.created"}`}</CodeLine>
                    </div>

                    <div>
                        <p className="mb-1 text-ink">Se désabonner</p>
                        <CodeLine>{`DELETE ${apiBaseUrl}/hooks/{id}`}</CodeLine>
                    </div>

                    <div>
                        <p className="mb-1 text-ink">Charge utile d'exemple (pour le mappage des champs)</p>
                        <CodeLine>{`GET ${apiBaseUrl}/hooks/sample?event=registration.created`}</CodeLine>
                    </div>

                    <p>
                        Une clé pilote la première organisation de son propriétaire : créez une clé par organisation si
                        vous en gérez plusieurs.
                    </p>
                </div>
            </details>
        </section>
    );
}
