import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import { type SharedProps } from '../../types';

export default function VerifyMfaCode() {
    const { flash } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/login/mfa');
    }

    function resend() {
        router.post('/login/mfa/resend');
    }

    return (
        <AuthLayout title="Vérification en deux étapes">
            <Head title="Vérification" />

            <p className="mb-6 text-sm text-ink-soft">
                Un code à 6 chiffres vient de vous être envoyé par e-mail. Il expire dans 10 minutes.
            </p>

            {flash.status === 'mfa-code-resent' && (
                <p className="mb-6 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    Un nouveau code a été envoyé.
                </p>
            )}

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <InputLabel htmlFor="code">Code de vérification</InputLabel>
                    <TextInput
                        id="code"
                        type="text"
                        inputMode="numeric"
                        maxLength={6}
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        autoFocus
                        required
                    />
                    <InputError message={errors.code} />
                </div>

                <Button type="submit" disabled={processing}>
                    Vérifier
                </Button>

                <button type="button" onClick={resend} className="block text-center text-sm text-ink-soft underline underline-offset-4 hover:text-ink">
                    Renvoyer le code
                </button>
            </form>
        </AuthLayout>
    );
}
