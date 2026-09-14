import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import EventIcon, { type EventIconName } from '../../Components/EventIcon';
import EventLayout from '../../Layouts/EventLayout';
import { type EventNav, type EventNavLinkKey, type SharedProps } from '../../types';

interface ChecklistStep {
    key: string;
    title: string;
    description: string;
    optional: boolean;
    done: boolean;
    automatic: boolean;
    markable: boolean;
    linkKey: string;
}

interface ChecklistTab {
    key: string;
    label: string;
    completed: number;
    total: number;
    steps: ChecklistStep[];
}

interface Props {
    checklist: {
        completed: number;
        total: number;
        percent: number;
        tabs: ChecklistTab[];
    };
    canUpdate: boolean;
}

const STEP_ICONS: Record<string, EventIconName> = {
    site_web: 'website',
    formulaire: 'form',
    liste_invites: 'guests',
    emails_confirmation: 'send',
    apercu: 'eye',
    publication: 'rocket',
    invitations: 'mail',
    lien_partage: 'link',
    suivi_reponses: 'chart',
    plan_de_table: 'seat',
    check_in: 'scan',
    export: 'download',
    frequentation: 'guests',
    remerciements: 'mail',
};

const TAB_ICONS: Record<string, EventIconName> = {
    personnaliser: 'palette',
    lancement: 'rocket',
    organiser: 'settings',
    suivi: 'mail',
};

const actionClass = 'inline-flex items-center gap-1.5 text-sm font-medium text-ink underline underline-offset-4 hover:text-ink-soft';

export default function Checklist({ checklist, canUpdate }: Props) {
    const page = usePage<SharedProps>();
    const eventNav = page.props.eventNav;
    const requestedTab = new URLSearchParams(page.url.split('?')[1] ?? '').get('onglet');
    const [activeKey, setActiveKey] = useState(
        checklist.tabs.find((tab) => tab.key === requestedTab)?.key ?? checklist.tabs[0]?.key,
    );
    const [copied, setCopied] = useState(false);

    if (eventNav === null) {
        return null;
    }

    const nav: EventNav = eventNav;
    const activeIndex = Math.max(0, checklist.tabs.findIndex((tab) => tab.key === activeKey));
    const activeTab = checklist.tabs[activeIndex];
    const nextTab = checklist.tabs[activeIndex + 1];

    function mark(step: ChecklistStep, completed: boolean) {
        router.post(`/events/${nav.id}/checklist`, { step: step.key, completed }, { preserveScroll: true });
    }

    async function copyPublicLink(step: ChecklistStep) {
        if (!nav.publicUrl) {
            return;
        }

        await navigator.clipboard.writeText(nav.publicUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);

        if (canUpdate && !step.done) {
            mark(step, true);
        }
    }

    function renderTarget(step: ChecklistStep) {
        if (step.linkKey === 'preview') {
            const href = nav.publicUrl ?? nav.previewUrl;

            return href ? (
                <a
                    href={href}
                    target="_blank"
                    rel="noreferrer"
                    onClick={() => canUpdate && !step.done && mark(step, true)}
                    className={actionClass}
                >
                    Ouvrir l'aperçu
                    <EventIcon name="external" className="h-3.5 w-3.5" />
                </a>
            ) : null;
        }

        if (step.linkKey === 'publish') {
            return nav.status === 'draft' && nav.canChangeStatus ? (
                <button type="button" onClick={() => router.post(`/events/${nav.id}/publish`, {}, { preserveScroll: true })} className={actionClass}>
                    Publier maintenant
                </button>
            ) : null;
        }

        if (step.linkKey === 'share') {
            return nav.publicUrl ? (
                <button type="button" onClick={() => copyPublicLink(step)} className={actionClass}>
                    {copied ? 'Lien copié' : 'Copier le lien'}
                </button>
            ) : (
                <span className="text-sm text-ink-soft">Disponible après la publication</span>
            );
        }

        const href = nav.links[step.linkKey as EventNavLinkKey] ?? null;

        return href ? (
            <Link href={href} className={actionClass}>
                Ouvrir
                <EventIcon name="chevronRight" className="h-3.5 w-3.5" />
            </Link>
        ) : null;
    }

    function renderCompletion(step: ChecklistStep) {
        if (step.markable && canUpdate) {
            return (
                <button
                    type="button"
                    onClick={() => mark(step, !step.done)}
                    aria-pressed={step.done}
                    className={`inline-flex items-center gap-2 rounded-pill border px-4 py-2 font-label text-[11px] tracking-[0.1em] whitespace-nowrap uppercase transition-colors ${
                        step.done ? 'border-success/40 bg-success-bg text-success' : 'border-line bg-bg text-ink hover:border-ink'
                    }`}
                >
                    {step.done ? <EventIcon name="check" className="h-3.5 w-3.5" /> : <span className="h-3 w-3 rounded-full border border-current" />}
                    {step.done ? 'Terminé' : 'Marquer comme terminé'}
                </button>
            );
        }

        return step.done ? (
            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-success text-bg" title="Constaté par Itaza">
                <EventIcon name="check" className="h-4 w-4" />
                <span className="sr-only">Complet</span>
            </span>
        ) : (
            <span className="block h-7 w-7 rounded-full border-2 border-line" aria-hidden="true" />
        );
    }

    return (
        <EventLayout title="Liste de contrôle">
            <Head title={`Liste de contrôle — ${nav.title}`} />

            <section className="flex flex-wrap items-center justify-between gap-4 rounded-card border border-line bg-bg p-5">
                <div className="flex items-center gap-4">
                    <span className="flex h-12 w-12 items-center justify-center rounded-card bg-bg-alt text-ink">
                        <EventIcon name="checklist" />
                    </span>
                    <div>
                        <p className="text-sm text-ink">
                            {checklist.completed} sur {checklist.total} étapes terminées
                        </p>
                        <div
                            role="progressbar"
                            aria-valuenow={checklist.percent}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label="Progression de la préparation"
                            className="mt-2 h-2 w-48 overflow-hidden rounded-pill bg-bg-deep sm:w-64"
                        >
                            <div className="h-full rounded-pill bg-success" style={{ width: `${checklist.percent}%` }} />
                        </div>
                    </div>
                </div>
                <span className="rounded-pill border border-line px-4 py-1.5 font-label text-xs tracking-[0.12em] text-ink uppercase tabular-nums">
                    {checklist.percent} % complété
                </span>
            </section>

            <div role="tablist" aria-label="Étapes de la liste de contrôle" className="mt-6 flex flex-wrap gap-3">
                {checklist.tabs.map((tab) => {
                    const selected = tab.key === activeTab.key;

                    return (
                        <button
                            key={tab.key}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            onClick={() => setActiveKey(tab.key)}
                            className={`inline-flex items-center gap-2 rounded-pill border px-5 py-2.5 text-sm transition-colors ${
                                selected ? 'border-ink bg-ink text-bg' : 'border-line bg-bg text-ink hover:border-ink'
                            }`}
                        >
                            <EventIcon name={TAB_ICONS[tab.key] ?? 'checklist'} className="h-4 w-4" />
                            {tab.label}
                            <span
                                className={`rounded-pill px-2 py-0.5 text-xs tabular-nums ${selected ? 'bg-bg/20 text-bg' : 'bg-bg-alt text-ink-soft'}`}
                            >
                                {tab.total}
                            </span>
                        </button>
                    );
                })}
            </div>

            <div role="tabpanel" aria-label={activeTab.label} className="mt-8">
                <div className="mb-4 flex items-center gap-3">
                    <span className="flex h-10 w-10 items-center justify-center rounded-card bg-bg text-ink ring-1 ring-line">
                        <EventIcon name={TAB_ICONS[activeTab.key] ?? 'checklist'} />
                    </span>
                    <div>
                        <h2 className="font-medium text-ink">{activeTab.label}</h2>
                        <p className="text-xs text-ink-soft tabular-nums">
                            {activeTab.completed}/{activeTab.total} complétée{activeTab.completed > 1 ? 's' : ''}
                        </p>
                    </div>
                </div>

                <ul className="space-y-3">
                    {activeTab.steps.map((step) => (
                        <li key={step.key} className="flex flex-col gap-4 rounded-card border border-line bg-bg p-5 sm:flex-row sm:items-start">
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-card bg-bg-alt text-ink">
                                <EventIcon name={STEP_ICONS[step.key] ?? 'checklist'} />
                            </span>

                            <div className="min-w-0 flex-1">
                                <h3 className="font-medium text-ink">
                                    {step.title} {step.optional && <span className="font-normal text-ink-soft">(optionnel)</span>}
                                </h3>
                                <p className="mt-1 text-sm text-ink-soft">{step.description}</p>
                                <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2">
                                    <span
                                        className={`inline-flex items-center gap-1.5 rounded-pill px-2.5 py-1 font-label text-[10px] tracking-[0.1em] uppercase ${
                                            step.done ? 'bg-success-bg text-success' : 'bg-bg-alt text-ink-soft'
                                        }`}
                                    >
                                        <span className={`h-1.5 w-1.5 rounded-full ${step.done ? 'bg-success' : 'bg-ink-soft'}`} />
                                        {step.done ? 'Complet' : 'En attente'}
                                    </span>
                                    {renderTarget(step)}
                                </div>
                            </div>

                            <div className="shrink-0 sm:pt-1">{renderCompletion(step)}</div>
                        </li>
                    ))}
                </ul>

                {nextTab && (
                    <button
                        type="button"
                        onClick={() => {
                            setActiveKey(nextTab.key);
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }}
                        className="mt-6 inline-flex items-center gap-2 rounded-pill border border-line bg-bg px-5 py-2.5 text-sm text-ink hover:border-ink"
                    >
                        À suivre : {nextTab.label}
                        <EventIcon name="chevronRight" className="h-4 w-4" />
                    </button>
                )}
            </div>
        </EventLayout>
    );
}
