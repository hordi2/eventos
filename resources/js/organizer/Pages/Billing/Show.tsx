import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Button from '../../Components/Button';
import SettingsLayout from '../../Layouts/SettingsLayout';

interface UsageMetric {
    used: number;
    quota: number | null;
}

interface Plan {
    value: string;
    label: string;
    price_label: string;
    registrations_per_month: number | null;
    emails_per_month: number | null;
    active_events: number | null;
}

interface Props {
    organization: {
        plan: string;
        subscription_status: string | null;
        subscription_current_period_end: string | null;
        dunning_stage: number;
        restricted: boolean;
        has_subscription: boolean;
    };
    usage: {
        registrations: UsageMetric;
        emails: UsageMetric;
        active_events: UsageMetric;
        percentages: Record<string, number | null>;
    };
    plans: Plan[];
}

const METRIC_LABELS: Record<string, string> = {
    registrations: 'Inscriptions ce mois-ci',
    emails: 'E-mails ce mois-ci',
    active_events: 'Événements actifs',
};

type TabKey = 'personal' | 'professional' | 'ticket';

const TABS: { key: TabKey; label: string }[] = [
    { key: 'personal', label: 'Événements personnels' },
    { key: 'professional', label: 'Plans professionnels' },
    { key: 'ticket', label: 'Événements sur billet' },
];

const PERSONAL_PLAN_VALUES = ['free', 'personal_essential', 'personal_comfort'];
const PROFESSIONAL_PLAN_VALUES = ['free', 'professional_plus', 'professional_career', 'professional_pro'];

interface PlanCopy {
    tier: string;
    tagline: string;
    features: string[];
}

const PERSONAL_COPY: Record<string, PlanCopy> = {
    free: {
        tier: 'Découverte',
        tagline: 'Pour un premier événement personnel.',
        features: ['Jusqu’à 100 invités', '1 événement actif', 'Invitations e-mail + WhatsApp'],
    },
    personal_essential: {
        tier: 'Essentiel',
        tagline: 'Pour un mariage, un anniversaire ou toute célébration avec plusieurs centaines d’invités.',
        features: ['Jusqu’à 300 invités', 'Plusieurs événements actifs à la fois', 'Fonctionnalités premium incluses', 'Support par e-mail'],
    },
    personal_comfort: {
        tier: 'Confort',
        tagline: 'Pour les familles ou wedding planners qui suivent plusieurs événements à la fois.',
        features: [
            'Jusqu’à 500 invités',
            'Fonctionnalités avancées',
            'E-mails personnalisés',
            'Formulaire RSVP intégrable sur votre site',
            'Support prioritaire par chat',
        ],
    },
};

const PROFESSIONAL_COPY: Record<string, PlanCopy> = {
    free: {
        tier: 'Démarreur',
        tagline: 'Pour une première conférence ou un premier événement d’entreprise.',
        features: ['Jusqu’à 50 invités par événement', '100 inscriptions/mois', 'Formulaires personnalisés illimités'],
    },
    professional_plus: {
        tier: 'En plus',
        tagline: 'Pour une équipe événementielle qui organise plusieurs événements par an.',
        features: [
            '150 inscriptions par mois',
            'Questions personnalisées sur vos formulaires',
            'Plan de sièges',
            'Communications électroniques avancées',
        ],
    },
    professional_career: {
        tier: 'Carrière professionnelle',
        tagline: 'Pour une agence ou une direction événementielle avec un volume soutenu.',
        features: [
            'Tous les avantages d’En plus, et :',
            '500 inscriptions mensuelles',
            'Plusieurs collaborateurs sur l’événement',
            'Limites de capacité par billet',
            'Protection par mot de passe avancée',
        ],
    },
    professional_pro: {
        tier: 'Pro',
        tagline: 'Pour un volume d’inscriptions important, sur plusieurs événements en parallèle.',
        features: [
            'Tous les avantages de Carrière professionnelle, et :',
            '1500 inscriptions mensuelles',
            'Marque Itaza discrète sur vos pages',
            'Logique conditionnelle avancée sur les formulaires',
            'Intégrations avancées (webhooks, API)',
        ],
    },
};

// Prix annuels calculés une fois, à titre indicatif (choix utilisateur :
// affichage seulement, le paiement reste sur l'abonnement mensuel Stripe
// tant qu'un Price ID annuel n'a pas été créé côté Stripe pour ce palier) —
// jamais recalculés à la volée pour rester des montants ronds et lisibles.
const ANNUAL_PRICING: Record<string, { monthlyEquivalent: string; yearlyTotal: string; savings: string }> = {
    personal_essential: { monthlyEquivalent: '5,4 $/mois', yearlyTotal: '65 $/an', savings: 'Économisez 39 %' },
    personal_comfort: { monthlyEquivalent: '10,7 $/mois', yearlyTotal: '128 $/an', savings: 'Économisez 29 %' },
    professional_plus: { monthlyEquivalent: '21,4 $/mois', yearlyTotal: '256 $/an', savings: 'Économisez 39 %' },
    professional_career: { monthlyEquivalent: '70,3 $/mois', yearlyTotal: '843 $/an', savings: 'Économisez 29 %' },
    professional_pro: { monthlyEquivalent: '234,1 $/mois', yearlyTotal: '2 809 $/an', savings: 'Économisez 19 %' },
};

function formatQuota(quota: number | null): string {
    return quota === null ? 'illimité' : String(quota);
}

function barColor(percentage: number | null): string {
    return percentage !== null && percentage >= 80 ? 'bg-danger' : 'bg-ink';
}

function PlanCard({
    plan,
    copy,
    annual,
    isCurrent,
    pending,
    onSelect,
}: {
    plan: Plan;
    copy: PlanCopy;
    annual: boolean;
    isCurrent: boolean;
    pending: boolean;
    onSelect: () => void;
}) {
    const annualPricing = ANNUAL_PRICING[plan.value];
    const showAnnual = annual && annualPricing !== undefined;

    return (
        <div className={`flex flex-col rounded-card bg-bg p-6 ring-1 ${isCurrent ? 'ring-accent' : 'ring-line'}`}>
            <p className="font-label text-xs tracking-[0.14em] text-accent uppercase">{copy.tier}</p>
            <p className="mt-2 font-serif text-2xl text-ink italic">{showAnnual ? annualPricing.monthlyEquivalent : plan.price_label}</p>
            {showAnnual && (
                <p className="mt-1 text-xs text-ink-soft">
                    {annualPricing.yearlyTotal} facturé annuellement · {annualPricing.savings}
                </p>
            )}
            <p className="mt-2 text-sm text-ink-soft">{copy.tagline}</p>
            <ul className="mt-4 mb-6 space-y-2 text-sm text-ink-soft">
                {copy.features.map((feature) => (
                    <li key={feature} className="flex gap-2">
                        <span className="text-success">✓</span>
                        {feature}
                    </li>
                ))}
            </ul>
            <div className="mt-auto">
                {isCurrent ? (
                    <p className="text-sm text-accent">Plan actuel</p>
                ) : (
                    <Button variant="secondary" disabled={pending} onClick={onSelect}>
                        {pending ? 'Redirection…' : 'Mise à niveau'}
                    </Button>
                )}
            </div>
        </div>
    );
}

function EnterpriseCard() {
    return (
        <div className="flex flex-col rounded-card bg-ink p-6 text-bg">
            <p className="font-label text-xs tracking-[0.14em] text-bg/70 uppercase">Entreprise</p>
            <p className="mt-2 font-serif text-2xl italic">Sur mesure</p>
            <p className="mt-2 text-sm text-bg/80">
                Pour planifier des événements entièrement personnalisés à grande échelle, avec votre propre marque.
            </p>
            <ul className="mt-4 mb-6 space-y-2 text-sm text-bg/80">
                <li className="flex gap-2">
                    <span>✓</span>
                    Votre marque, sans mention Itaza
                </li>
                <li className="flex gap-2">
                    <span>✓</span>
                    Champs de données et polices personnalisés
                </li>
                <li className="flex gap-2">
                    <span>✓</span>
                    Kiosque d’enregistrement en libre-service
                </li>
                <li className="flex gap-2">
                    <span>✓</span>
                    Connexion unique (SSO)
                </li>
                <li className="flex gap-2">
                    <span>✓</span>
                    Gestionnaire de compte dédié
                </li>
            </ul>
            <Link
                href="/support"
                className="mt-auto inline-flex min-h-11 w-full items-center justify-center rounded-pill border border-bg/40 px-8 py-4 text-[14.5px] font-medium text-bg hover:border-bg"
            >
                Nous contacter
            </Link>
        </div>
    );
}

export default function Show({ organization, usage, plans }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [pending, setPending] = useState<string | null>(null);
    const [tab, setTab] = useState<TabKey>('personal');
    const [annual, setAnnual] = useState(false);

    function handleCheckout(plan: string) {
        setPending(plan);

        // router.post (jamais une navigation GET brute) : la route est en
        // POST côté serveur (protection CSRF), et Inertia::location() y
        // répond par un en-tête spécial que seul le client Inertia sait
        // convertir en vraie redirection plein-écran vers Stripe.
        router.post(
            `/billing/checkout/${plan}`,
            {},
            { onFinish: () => setPending(null) },
        );
    }

    function handleChange(plan: string) {
        setPending(plan);

        router.post(
            `/billing/change/${plan}`,
            {},
            { onFinish: () => setPending(null) },
        );
    }

    function handlePortal() {
        router.post('/billing/portal');
    }

    function selectPlan(plan: string) {
        organization.has_subscription ? handleChange(plan) : handleCheckout(plan);
    }

    const metrics: Array<{ key: 'registrations' | 'emails' | 'active_events'; metric: UsageMetric }> = [
        { key: 'registrations', metric: usage.registrations },
        { key: 'emails', metric: usage.emails },
        { key: 'active_events', metric: usage.active_events },
    ];

    const currentPlan = plans.find((plan) => plan.value === organization.plan);
    const periodEnd =
        organization.subscription_current_period_end !== null
            ? new Date(organization.subscription_current_period_end).toLocaleDateString('fr-FR', {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              })
            : null;

    const personalPlans = PERSONAL_PLAN_VALUES.map((value) => plans.find((plan) => plan.value === value)).filter(
        (plan): plan is Plan => plan !== undefined,
    );
    const professionalPlans = PROFESSIONAL_PLAN_VALUES.map((value) => plans.find((plan) => plan.value === value)).filter(
        (plan): plan is Plan => plan !== undefined,
    );

    return (
        <SettingsLayout title="Facturation" active="billing">
            <Head title="Facturation" />

            <h2 className="mb-6 border-b border-line pb-4 text-xl">Abonnement</h2>

            <div className="mb-10">
                <p className="mb-1 font-serif text-2xl text-ink italic">{currentPlan?.label ?? 'Gratuit'}</p>
                <p className="mb-6 text-sm text-ink-soft">
                    {organization.has_subscription
                        ? `Abonnement actif${periodEnd !== null ? ` — prochaine échéance le ${periodEnd}` : ''}.`
                        : "Vous n'avez pas d'abonnement payant actif."}
                </p>

                <div className="flex flex-wrap gap-3">
                    <a
                        href="#forfaits"
                        className="inline-flex min-h-11 items-center rounded-pill bg-ink px-6 text-[14.5px] font-medium text-bg hover:opacity-90"
                    >
                        Voir les forfaits et les tarifs
                    </a>

                    {organization.has_subscription && (
                        <Button variant="secondary" className="w-auto" onClick={handlePortal}>
                            Gérer mon abonnement (Stripe)
                        </Button>
                    )}
                </div>
            </div>

            <h2 className="mb-6 border-b border-line pb-4 text-xl">Historique de la facturation</h2>

            <div className="mb-12">
                {organization.has_subscription ? (
                    <p className="text-sm text-ink-soft">
                        Vos factures sont émises et conservées par Stripe : retrouvez-les toutes depuis « Gérer mon
                        abonnement » ci-dessus.
                    </p>
                ) : (
                    <p className="text-sm text-ink-soft">Aucun historique de facturation à afficher.</p>
                )}
            </div>

            {organization.restricted && (
                <div className="mb-8 rounded-card bg-danger/10 p-4 text-sm text-danger ring-1 ring-danger/30">
                    Le prélèvement échoue depuis plusieurs jours : votre organisation applique temporairement les quotas du plan
                    gratuit jusqu'à régularisation du paiement. Vos données restent intactes.
                </div>
            )}

            {!organization.restricted && organization.subscription_status === 'past_due' && (
                <div className="mb-8 rounded-card bg-danger/10 p-4 text-sm text-danger ring-1 ring-danger/30">
                    Le dernier prélèvement a échoué. Mettez à jour votre moyen de paiement pour éviter une restriction.
                </div>
            )}

            {errors.plan && (
                <div className="mb-8 rounded-card bg-danger/10 p-4 text-sm text-danger ring-1 ring-danger/30">{errors.plan}</div>
            )}

            <div className="mb-10 rounded-card bg-bg p-6 ring-1 ring-line">
                <h2 className="mb-4 font-serif text-lg italic">Consommation ce mois-ci</h2>
                <div className="space-y-4">
                    {metrics.map(({ key, metric }) => {
                        const percentage = usage.percentages[key];

                        return (
                            <div key={key}>
                                <div className="mb-1 flex items-center justify-between text-sm">
                                    <span>{METRIC_LABELS[key]}</span>
                                    <span className="text-ink-soft">
                                        {metric.used} / {formatQuota(metric.quota)}
                                    </span>
                                </div>
                                {metric.quota !== null && (
                                    <div className="h-2 w-full overflow-hidden rounded-full bg-bg-deep">
                                        <div
                                            className={`h-full rounded-full ${barColor(percentage)}`}
                                            style={{ width: `${Math.min(100, percentage ?? 0)}%` }}
                                        />
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            <div id="forfaits" className="mb-8 flex flex-wrap items-center justify-between gap-4 border-b border-line">
                <nav className="flex gap-6 overflow-x-auto">
                    {TABS.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setTab(item.key)}
                            className={`shrink-0 border-b-2 pb-3 text-sm whitespace-nowrap ${
                                tab === item.key ? 'border-ink text-ink' : 'border-transparent text-ink-soft'
                            }`}
                        >
                            {item.label}
                        </button>
                    ))}
                </nav>

                {tab !== 'ticket' && (
                    <div className="mb-3 flex shrink-0 items-center gap-1 rounded-pill border border-line p-1">
                        <button
                            type="button"
                            onClick={() => setAnnual(false)}
                            className={`rounded-pill px-3 py-1.5 text-xs ${!annual ? 'bg-bg-deep text-ink' : 'text-ink-soft'}`}
                        >
                            Mensuel
                        </button>
                        <button
                            type="button"
                            onClick={() => setAnnual(true)}
                            className={`rounded-pill px-3 py-1.5 text-xs ${annual ? 'bg-bg-deep text-ink' : 'text-ink-soft'}`}
                        >
                            Annuel — jusqu'à 39 % d'économie
                        </button>
                    </div>
                )}
            </div>

            {tab === 'personal' && (
                <div className="mb-10 grid gap-4 sm:grid-cols-3">
                    {personalPlans.map((plan) => (
                        <PlanCard
                            key={plan.value}
                            plan={plan}
                            copy={PERSONAL_COPY[plan.value]}
                            annual={annual}
                            isCurrent={plan.value === organization.plan}
                            pending={pending === plan.value}
                            onSelect={() => selectPlan(plan.value)}
                        />
                    ))}
                </div>
            )}

            {tab === 'professional' && (
                <div className="mb-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {professionalPlans.map((plan) => (
                        <PlanCard
                            key={plan.value}
                            plan={plan}
                            copy={PROFESSIONAL_COPY[plan.value]}
                            annual={annual}
                            isCurrent={plan.value === organization.plan}
                            pending={pending === plan.value}
                            onSelect={() => selectPlan(plan.value)}
                        />
                    ))}
                    <EnterpriseCard />
                </div>
            )}

            {tab === 'ticket' && (
                <div className="mb-10 rounded-card bg-bg p-6 ring-1 ring-line">
                    <p className="mb-1 font-serif text-lg text-ink italic">Aucune commission Itaza sur vos billets</p>
                    <p className="text-sm text-ink-soft">
                        La billetterie (Stripe, Flutterwave, Mobile Money, paiement à l'arrivée) est incluse dans votre plan
                        d'abonnement, sans pourcentage prélevé par Itaza sur chaque billet vendu. Seuls les frais propres à
                        votre prestataire de paiement s'appliquent, comme pour n'importe quel encaissement en ligne.
                    </p>
                </div>
            )}

            <div className="mb-10 rounded-card bg-bg-alt p-6 ring-1 ring-line">
                <p className="mb-2 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">Inclus dans tous les plans</p>
                <p className="text-sm text-ink-soft">
                    WhatsApp et e-mail au même niveau, check-in hors ligne, Mobile Money natif, formulaires personnalisés,
                    check-in par QR code, exports CSV — les plans se distinguent par leurs volumes et fonctionnalités
                    avancées ci-dessus, jamais par ces trois différenciateurs, toujours inclus.
                </p>
            </div>

            <div className="mb-10 space-y-4">
                <h2 className="font-serif text-lg text-ink italic">Questions fréquentes</h2>
                <FaqItem question="Puis-je changer de plan à tout moment ?">
                    Oui, à la hausse comme à la baisse, depuis le bouton « Gérer mon abonnement » ci-dessous — le
                    changement est géré directement par Stripe.
                </FaqItem>
                <FaqItem question="Que se passe-t-il si je dépasse mon quota d'inscriptions ou d'e-mails ?">
                    Vous recevez une alerte par e-mail à 80 % puis à 100 % du quota mensuel. Contactez-nous si vous devez
                    dépasser temporairement votre quota le temps de changer de plan.
                </FaqItem>
                <FaqItem question="Le paiement Mobile Money est-il inclus dans tous les plans ?">
                    Oui — comme WhatsApp et le check-in hors ligne, ce n'est jamais un ajout réservé à un plan supérieur.
                </FaqItem>
                <FaqItem question="Que se passe-t-il en cas d'échec de paiement ?">
                    Après plusieurs échecs de prélèvement, votre organisation repasse temporairement aux quotas du plan
                    gratuit jusqu'à régularisation — vos données et vos événements restent intacts.
                </FaqItem>
            </div>

        </SettingsLayout>
    );
}

function FaqItem({ question, children }: { question: string; children: string }) {
    return (
        <details className="group rounded-card border border-line p-4">
            <summary className="cursor-pointer list-none text-sm font-medium text-ink marker:content-none">
                {question}
            </summary>
            <p className="mt-2 text-sm text-ink-soft">{children}</p>
        </details>
    );
}
