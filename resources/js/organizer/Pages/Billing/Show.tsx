import { Head, router, usePage } from '@inertiajs/react';
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

function formatQuota(quota: number | null): string {
    return quota === null ? 'illimité' : String(quota);
}

function barColor(percentage: number | null): string {
    return percentage !== null && percentage >= 80 ? 'bg-danger' : 'bg-ink';
}

export default function Show({ organization, usage, plans }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [pending, setPending] = useState<string | null>(null);

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

    const metrics: Array<{ key: 'registrations' | 'emails' | 'active_events'; metric: UsageMetric }> = [
        { key: 'registrations', metric: usage.registrations },
        { key: 'emails', metric: usage.emails },
        { key: 'active_events', metric: usage.active_events },
    ];

    return (
        <SettingsLayout title="Facturation" active="billing">
            <Head title="Facturation" />

            <div className="mb-8">
                <h1 className="text-2xl">Facturation</h1>
                <p className="text-ink-soft">Plan actuel, consommation des quotas et gestion de l'abonnement.</p>
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

            <div className="mb-8 rounded-card bg-bg p-6 ring-1 ring-line">
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

            <div className="mb-8 grid gap-4 sm:grid-cols-3">
                {plans.map((plan) => {
                    const isCurrent = plan.value === organization.plan;

                    return (
                        <div key={plan.value} className={`rounded-card bg-bg p-6 ring-1 ${isCurrent ? 'ring-accent' : 'ring-line'}`}>
                            <h3 className="mb-1 font-serif text-lg italic">{plan.label}</h3>
                            <p className="mb-4 text-2xl">{plan.price_label}</p>
                            <ul className="mb-4 space-y-1 text-sm text-ink-soft">
                                <li>{formatQuota(plan.registrations_per_month)} inscriptions/mois</li>
                                <li>{formatQuota(plan.emails_per_month)} e-mails/mois</li>
                                <li>{formatQuota(plan.active_events)} événement(s) actif(s)</li>
                            </ul>
                            {isCurrent ? (
                                <p className="text-sm text-accent">Plan actuel</p>
                            ) : (
                                <Button
                                    variant="secondary"
                                    className="w-full"
                                    disabled={pending === plan.value}
                                    onClick={() => (organization.has_subscription ? handleChange(plan.value) : handleCheckout(plan.value))}
                                >
                                    {pending === plan.value ? 'Redirection…' : 'Choisir ce plan'}
                                </Button>
                            )}
                        </div>
                    );
                })}
            </div>

            {organization.has_subscription && (
                <Button variant="secondary" className="w-auto" onClick={handlePortal}>
                    Gérer mon abonnement et mes factures (Stripe)
                </Button>
            )}
        </SettingsLayout>
    );
}
