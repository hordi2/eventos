import { type PropsWithChildren } from 'react';

export default function AuthLayout({ title, children }: PropsWithChildren<{ title: string }>) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
            <div className="w-full max-w-sm space-y-6">
                <h1 className="text-center text-2xl font-semibold text-gray-900">{title}</h1>
                <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">{children}</div>
            </div>
        </div>
    );
}
