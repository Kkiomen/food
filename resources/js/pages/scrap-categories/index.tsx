import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { destroy, edit, index as scrapCategoriesIndex, show } from '@/routes/scrap-categories';
import { type BreadcrumbItem, type PaginatedData, type ScrapCategory } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Scrap Categories', href: scrapCategoriesIndex().url },
];

interface Props {
    categories: PaginatedData<ScrapCategory>;
    filters: { search: string | null; type: string | null; status: string | null };
}

export default function Index({ categories, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(null);

    const handleSearch = useCallback(
        (value: string) => {
            setSearch(value);

            if (debounceRef.current) clearTimeout(debounceRef.current);

            debounceRef.current = setTimeout(() => {
                router.get(
                    scrapCategoriesIndex().url,
                    {
                        search: value || undefined,
                        type: filters.type || undefined,
                        status: filters.status || undefined,
                    },
                    { preserveState: true, replace: true },
                );
            }, 300);
        },
        [filters.type, filters.status],
    );

    const handleFilter = useCallback(
        (key: 'type' | 'status', value: string) => {
            router.get(
                '/scrap-categories',
                {
                    search: filters.search || undefined,
                    type: key === 'type' ? (value || undefined) : (filters.type || undefined),
                    status: key === 'status' ? (value || undefined) : (filters.status || undefined),
                },
                { preserveState: true, replace: true },
            );
        },
        [filters],
    );

    const handleDelete = useCallback((id: number) => {
        router.delete(destroy.url(id));
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Scrap Categories" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-2xl font-bold">Scrap Categories</h1>
                    <div className="text-sm text-muted-foreground">
                        {categories.total} {categories.total === 1 ? 'category' : 'categories'}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        placeholder="Search by URL..."
                        value={search}
                        onChange={(e) => handleSearch(e.target.value)}
                        className="max-w-sm"
                    />
                    <select
                        value={filters.type ?? ''}
                        onChange={(e) => handleFilter('type', e.target.value)}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All types</option>
                        <option value="ania-gotuje">ania-gotuje</option>
                        <option value="ze-smakiem-na-ty">ze-smakiem-na-ty</option>
                        <option value="poprostupycha">poprostupycha</option>
                        <option value="smaker">smaker</option>
                    </select>
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) => handleFilter('status', e.target.value)}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="scraped">Scraped</option>
                        <option value="unscraped">Unscraped</option>
                    </select>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12">ID</TableHead>
                                <TableHead>URL</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-32 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                        No categories found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                categories.data.map((category) => (
                                    <TableRow key={category.id}>
                                        <TableCell className="font-mono text-muted-foreground">
                                            {category.id}
                                        </TableCell>
                                        <TableCell className="max-w-md truncate font-medium">
                                            <Link
                                                href={show.url(category.id)}
                                                className="hover:underline"
                                            >
                                                {category.url}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">{category.type}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={category.is_scraped ? 'default' : 'outline'}>
                                                {category.is_scraped ? 'Scraped' : 'Unscraped'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={show.url(category.id)}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={edit.url(category.id)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button variant="ghost" size="icon">
                                                            <Trash2 className="h-4 w-4 text-destructive" />
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>Delete category?</AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This will permanently delete this category. This action cannot be undone.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                            <AlertDialogAction
                                                                variant="destructive"
                                                                onClick={() => handleDelete(category.id)}
                                                            >
                                                                Delete
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {categories.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing {categories.from}–{categories.to} of {categories.total}
                        </p>
                        <div className="flex gap-1">
                            {categories.links.map((link, i) => (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    asChild={!!link.url}
                                >
                                    {link.url ? (
                                        <Link
                                            href={link.url}
                                            preserveState
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    )}
                                </Button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
