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
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { destroy, edit, index as scrapRecipesIndex, show } from '@/routes/scrap-recipes';
import { type BreadcrumbItem, type ScrapRecipe } from '@/types';

interface Props {
    recipe: ScrapRecipe;
}

export default function Show({ recipe }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Scrap Recipes', href: scrapRecipesIndex().url },
        { title: recipe.name, href: show.url(recipe.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={recipe.name} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild>
                            <Link href={scrapRecipesIndex().url}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-bold">{recipe.name}</h1>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={edit.url(recipe.id)}>
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
                                    <AlertDialogTitle>Delete recipe?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        This will permanently delete &quot;{recipe.name}&quot;. This action cannot be undone.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                    <AlertDialogAction
                                        variant="destructive"
                                        onClick={() => router.delete(destroy.url(recipe.id))}
                                    >
                                        Delete
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2 space-y-6">
                        {/* Basic Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Basic Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {recipe.description && (
                                    <p className="text-muted-foreground">{recipe.description}</p>
                                )}
                                <div className="flex flex-wrap gap-2">
                                    {recipe.category && <Badge variant="secondary">{recipe.category}</Badge>}
                                    {recipe.cuisine && <Badge variant="outline">{recipe.cuisine}</Badge>}
                                    {recipe.diet && <Badge>{recipe.diet}</Badge>}
                                </div>
                                {recipe.url && (
                                    <div>
                                        <span className="text-sm font-medium">Source: </span>
                                        <a
                                            href={recipe.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-sm text-blue-600 hover:underline dark:text-blue-400"
                                        >
                                            {recipe.url}
                                        </a>
                                    </div>
                                )}
                                {recipe.author && (
                                    <div>
                                        <span className="text-sm font-medium">Author: </span>
                                        <span className="text-sm text-muted-foreground">{recipe.author}</span>
                                    </div>
                                )}
                                {recipe.keywords && recipe.keywords.length > 0 && (
                                    <div className="flex flex-wrap gap-1">
                                        {recipe.keywords.map((keyword, i) => (
                                            <Badge key={i} variant="outline" className="text-xs">
                                                {keyword}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Ingredients */}
                        {recipe.ingredients && recipe.ingredients.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Ingredients</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="list-disc space-y-1 pl-5">
                                        {recipe.ingredients.map((ingredient, i) => (
                                            <li key={i} className="text-sm">{ingredient}</li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}

                        {/* Prepared Steps */}
                        {recipe.prepared_steps && recipe.prepared_steps.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Prepared Steps</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ol className="list-decimal space-y-3 pl-5">
                                        {recipe.prepared_steps.map((step, i) => (
                                            <li key={i} className="text-sm">{step.text}</li>
                                        ))}
                                    </ol>
                                </CardContent>
                            </Card>
                        )}

                        {/* Original Steps */}
                        {recipe.steps && recipe.steps.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Original Steps</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ol className="list-decimal space-y-3 pl-5">
                                        {recipe.steps.map((step, i) => (
                                            <li key={i} className="text-sm">
                                                {step.name && (
                                                    <span className="font-medium">{step.name}: </span>
                                                )}
                                                {step.text}
                                            </li>
                                        ))}
                                    </ol>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        {/* Time & Servings */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Time & Servings</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <InfoRow label="Prep Time" value={recipe.prep_time} />
                                <InfoRow label="Cook Time" value={recipe.cook_time} />
                                <InfoRow label="Total Time" value={recipe.total_time} />
                                <Separator />
                                <InfoRow label="Servings" value={recipe.servings} />
                            </CardContent>
                        </Card>

                        {/* Rating */}
                        {(recipe.rating_value || recipe.rating_count) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Rating</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    <InfoRow label="Rating" value={recipe.rating_value ? `${recipe.rating_value}/5` : null} />
                                    <InfoRow label="Reviews" value={recipe.rating_count?.toString()} />
                                    <InfoRow label="Comments" value={recipe.comment_count?.toString()} />
                                </CardContent>
                            </Card>
                        )}

                        {/* Nutrition */}
                        {recipe.nutrition && Object.keys(recipe.nutrition).length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Nutrition</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {Object.entries(recipe.nutrition).map(([key, value]) => (
                                        <InfoRow key={key} label={key} value={value} />
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function InfoRow({ label, value }: { label: string; value: string | null | undefined }) {
    if (!value) return null;
    return (
        <div className="flex justify-between text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}
