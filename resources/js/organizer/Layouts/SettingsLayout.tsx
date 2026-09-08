import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { type SharedProps } from '../types';
import OrganizerLayout from './OrganizerLayout';

export type SettingsSection =
    | 'profile'
    | 'security'
    | 'notifications'
    | 'branding'
    | 'billing'
    | 'integrations'
    | 'white-label'
    | 'referral'
    | 'audit-log'
    | 'compliance';

interface SettingsLayoutProps {
    title: string;
    active: SettingsSection;
}

export default function SettingsLayout({ title, active, children }: PropsWithChildren<SettingsLayoutProps>) {
    const { settingsAccess } = usePage<SharedProps>().props;

    const sections: { key: SettingsSection; label: string; href: string; visible: boolean }[] = [
        { key: 'profile', label: 'Mon compte', href: '/settings/profile', visible: true },
        { key: 'security', label: 'Sécurité', href: '/settings/security', visible: true },
        { key: 'notifications', label: 'Notifications', href: '/settings/notifications', visible: true },
        { key: 'billing', label: 'Facturation', href: '/billing', visible: settingsAccess.billing },
        { key: 'integrations', label: 'Intégrations', href: '/settings/api', visible: settingsAccess.integrations },
        { key: 'white-label', label: 'Étiquetage blanc', href: '/settings/white-label', visible: settingsAccess.whiteLabel },
        { key: 'referral', label: 'Refer-a-Friend', href: '/settings/referral', visible: settingsAccess.referral },
        { key: 'branding', label: 'Personnalisation', href: '/organization/branding', visible: settingsAccess.branding },
        { key: 'audit-log', label: "Journal d'audit", href: '/audit-log', visible: settingsAccess.auditLog },
        { key: 'compliance', label: 'Registre des traitements', href: '/compliance/register', visible: settingsAccess.auditLog },
    ];

    return (
        <OrganizerLayout title={title} eyebrow="Paramètres">
            <nav className="-mt-2 mb-10 flex gap-8 overflow-x-auto border-b border-line">
                {sections
                    .filter((section) => section.visible)
                    .map((section) => (
                        <Link
                            key={section.key}
                            href={section.href}
                            className={`shrink-0 border-b-2 pb-3 text-sm whitespace-nowrap transition-colors ${
                                section.key === active
                                    ? 'border-accent text-accent'
                                    : 'border-transparent text-ink-soft hover:text-ink'
                            }`}
                        >
                            {section.label}
                        </Link>
                    ))}
            </nav>

            <div className="min-w-0">{children}</div>
        </OrganizerLayout>
    );
}
