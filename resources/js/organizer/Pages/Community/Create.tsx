import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface CategoryOption {
    value: string;
    label: string;
    description: string;
}

export default function Create({ categories }: { categories: CategoryOption[] }) {
    const { data, setData, post, processing, errors } = useForm({
        category: categories[0]?.value ?? '',
        title: '',
        body: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/community');
    }

    return (
        <OrganizerLayout title="Nouveau sujet" eyebrow="Communauté">
            <Head title="Nouveau sujet" />

            <form onSubmit={submit} className="max-w-xl">
                <div className="mb-6">
                    <InputLabel>Catégorie</InputLabel>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {categories.map((category) => (
                            <label
                                key={category.value}
                                className={`cursor-pointer rounded-card border p-3 text-sm ${
                                    data.category === category.value ? 'border-ink' : 'border-line'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="category"
                                    value={category.value}
                                    checked={data.category === category.value}
                                    onChange={() => setData('category', category.value)}
                                    className="sr-only"
                                />
                                <span className="font-medium text-ink">{category.label}</span>
                                <span className="mt-1 block text-xs text-ink-soft">{category.description}</span>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.category} />
                </div>

                <div className="mb-6">
                    <InputLabel htmlFor="title">Titre</InputLabel>
                    <TextInput id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                    <InputError message={errors.title} />
                </div>

                <div className="mb-6">
                    <InputLabel htmlFor="body">Votre message</InputLabel>
                    <textarea
                        id="body"
                        rows={8}
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        className="w-full rounded-control border border-line px-3 py-2.5 text-sm text-ink"
                    />
                    <InputError message={errors.body} />
                </div>

                <Button type="submit" disabled={processing}>
                    Publier le sujet
                </Button>
            </form>
        </OrganizerLayout>
    );
}
