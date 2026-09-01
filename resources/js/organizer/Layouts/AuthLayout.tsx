import { type PropsWithChildren } from 'react';
import { Link } from '@inertiajs/react';
import Logo from '../Components/Logo';

export default function AuthLayout({ title, children }: PropsWithChildren<{ title: string }>) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-bg-alt px-4 py-12">
            <div className="w-full max-w-sm space-y-8">
                <Link href="/" className="flex justify-center">
                    <Logo className="h-12 w-auto" />
                </Link>

                <h1 className="text-center font-serif text-2xl italic font-medium text-ink">{title}</h1>

                <div className="rounded-2xl bg-bg p-8 shadow-sm ring-1 ring-line">{children}</div>
            </div>
        </div>
    );
}
