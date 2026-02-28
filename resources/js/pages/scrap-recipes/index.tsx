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
import { destroy, edit, index as scrapRecipesIndex, show } from '@/routes/scrap-recipes';
import { type BreadcrumbItem, type PaginatedData, type ScrapRecipe } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Scrap Recipes', href: scrapRecipesIndex().url },
];

interface Props {
    recipes: PaginatedData<ScrapRecipe>;
    filters: { search: string | null };
}

export default function Index({ recipes, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(null);

    const handleSearch = useCallback(
        (value: string) => {
            setSearch(value);

            if (debounceRef.current) clearTimeout(debounceRef.current);

            debounceRef.current = setTimeout(() => {
                router.get(
                    scrapRecipesIndex().url,
                    { search: value || undefined },
                    { preserveState: true, replace: true },
                );
            }, 300);
        },
        [],
    );

    const handleDelete = useCallback((id: number) => {
        router.delete(destroy.url(id));
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Scrap Recipes" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-2xl font-bold">Scrap Recipes</h1>
                    <div className="text-sm text-muted-foreground">
                        {recipes.total} {recipes.total === 1 ? 'recipe' : 'recipes'}
                    </div>
                </div>

                <Input
                    placeholder="Search by name, category, or cuisine..."
                    value={search}
                    onChange={(e) => handleSearch(e.target.value)}
                    className="max-w-sm"
                />

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12">ID</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Cuisine</TableHead>
                                <TableHead>Rating</TableHead>
                                <TableHead className="w-32 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {recipes.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                        No recipes found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                recipes.data.map((recipe) => (
                                    <TableRow key={recipe.id}>
                                        <TableCell className="font-mono text-muted-foreground">
                                            {recipe.id}
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate font-medium">
                                            <Link
                                                href={show.url(recipe.id)}
                                                className="hover:underline"
                                            >
                                                {recipe.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {recipe.category && (
                                                <Badge variant="secondary">{recipe.category}</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {recipe.cuisine && (
                                                <Badge variant="outline">{recipe.cuisine}</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {recipe.rating_value ? `${recipe.rating_value}/5` : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={show.url(recipe.id)}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={edit.url(recipe.id)}>
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
                                                            <AlertDialogTitle>Delete recipe?</AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This will permanently delete &quot;{recipe.name}&quot;. This action cannot be undone.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                            <AlertDialogAction
                                                                variant="destructive"
                                                                onClick={() => handleDelete(recipe.id)}
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

                {recipes.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing {recipes.from}–{recipes.to} of {recipes.total}
                        </p>
                        <div className="flex gap-1">
                            {recipes.links.map((link, i) => (
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
