import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { type SharedProps } from '../types';
import OrganizerLayout from './OrganizerLayout';

export type SettingsSection = 'profile' | 'branding' | 'billing' | 'audit-log' | 'compliance' | 'integrations';

interface SettingsLayoutProps {
    title: string;
    active: SettingsSection;
}

export default function SettingsLayout({ title, active, children }: PropsWithChildren<SettingsLayoutProps>) {
    const { settingsAccess } = usePage<SharedProps>().props;

    const sections: { key: SettingsSection; label: string; href: string; visible: boolean }[] = [
        { key: 'profile', label: 'Compte', href: '/settings/profile', visible: true },
        { key: 'branding', label: 'Personnalisation', href: '/organization/branding', visible: settingsAccess.branding },
        { key: 'billing', label: 'Facturation', href: '/billing', visible: settingsAccess.billing },
        { key: 'audit-log', label: "Journal d'audit", href: '/audit-log', visible: settingsAccess.auditLog },
        { key: 'compliance', label: 'Registre des traitements', href: '/compliance/register', visible: settingsAccess.auditLog },
        { key: 'integrations', label: 'Intégrations & API', href: '/settings/api', visible: settingsAccess.integrations },
    ];

    return (
        <OrganizerLayout title={title} eyebrow="Paramètres">
            <div className="grid grid-cols-1 gap-10 md:grid-cols-[200px_1fr]">
                <nav className="flex gap-1 overflow-x-auto md:flex-col md:overflow-visible">
                    {sections
                        .filter((section) => section.visible)
                        .map((section) => (
                            <Link
                                key={section.key}
                                href={section.href}
                                className={`shrink-0 rounded-control px-3 py-2 text-sm whitespace-nowrap ${
                                    section.key === active ? 'bg-bg-deep text-ink' : 'text-ink-soft hover:text-ink'
                                }`}
                            >
                                {section.label}
                            </Link>
                        ))}
                </nav>
                <div className="min-w-0">{children}</div>
            </div>
        </OrganizerLayout>
    );
}
