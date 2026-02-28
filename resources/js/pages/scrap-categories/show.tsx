import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';

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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { destroy, edit, index as scrapCategoriesIndex, show } from '@/routes/scrap-categories';
import { type BreadcrumbItem, type ScrapCategory } from '@/types';

interface Props {
    category: ScrapCategory;
}

export default function Show({ category }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Scrap Categories', href: scrapCategoriesIndex().url },
        { title: `#${category.id}`, href: show.url(category.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Category #${category.id}`} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={scrapCategoriesIndex().url}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-bold">Category #{category.id}</h1>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={edit.url(category.id)}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="destructive">
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
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
                                        onClick={() => router.delete(destroy.url(category.id))}
                                    >
                                        Delete
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>

                <div className="max-w-2xl">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <span className="text-sm font-medium">URL</span>
                                <p className="mt-1">
                                    <a
                                        href={category.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-sm text-blue-600 break-all hover:underline dark:text-blue-400"
                                    >
                                        {category.url}
                                    </a>
                                </p>
                            </div>
                            <div className="flex gap-6">
                                <div>
                                    <span className="text-sm font-medium">Type</span>
                                    <p className="mt-1">
                                        <Badge variant="secondary">{category.type}</Badge>
                                    </p>
                                </div>
                                <div>
                                    <span className="text-sm font-medium">Status</span>
                                    <p className="mt-1">
                                        <Badge variant={category.is_scraped ? 'default' : 'outline'}>
                                            {category.is_scraped ? 'Scraped' : 'Unscraped'}
                                        </Badge>
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-6 text-sm text-muted-foreground">
                                <div>
                                    <span className="font-medium">Created:</span>{' '}
                                    {new Date(category.created_at).toLocaleString()}
                                </div>
                                <div>
                                    <span className="font-medium">Updated:</span>{' '}
                                    {new Date(category.updated_at).toLocaleString()}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
