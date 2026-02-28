import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type FormEvent } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { edit, index as scrapCategoriesIndex, show, update } from '@/routes/scrap-categories';
import { type BreadcrumbItem, type ScrapCategory } from '@/types';

interface Props {
    category: ScrapCategory;
}

const TYPES = [
    { value: 'ania-gotuje', label: 'ania-gotuje' },
    { value: 'ze-smakiem-na-ty', label: 'ze-smakiem-na-ty' },
    { value: 'poprostupycha', label: 'poprostupycha' },
    { value: 'smaker', label: 'smaker' },
];

export default function Edit({ category }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Scrap Categories', href: scrapCategoriesIndex().url },
        { title: `#${category.id}`, href: show.url(category.id) },
        { title: 'Edit', href: edit.url(category.id) },
    ];

    const { data, setData, put, processing, errors } = useForm({
        url: category.url,
        type: category.type,
        is_scraped: category.is_scraped,
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put(update.url(category.id));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Category #${category.id}`} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={show.url(category.id)}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold">Edit Category</h1>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Category Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label htmlFor="url">URL *</Label>
                                <Input
                                    id="url"
                                    type="url"
                                    value={data.url}
                                    onChange={(e) => setData('url', e.target.value)}
                                    required
                                />
                                <InputError message={errors.url} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="type">Type *</Label>
                                <select
                                    id="type"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                                >
                                    {TYPES.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.type} className="mt-1" />
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_scraped"
                                    checked={data.is_scraped}
                                    onCheckedChange={(checked) => setData('is_scraped', checked === true)}
                                />
                                <Label htmlFor="is_scraped" className="cursor-pointer">
                                    Scraped
                                </Label>
                                <InputError message={errors.is_scraped} className="mt-1" />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={show.url(category.id)}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
