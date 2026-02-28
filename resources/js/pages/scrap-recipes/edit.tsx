import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type FormEvent } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { edit, index as scrapRecipesIndex, show, update } from '@/routes/scrap-recipes';
import { type BreadcrumbItem, type ScrapRecipe } from '@/types';

interface Props {
    recipe: ScrapRecipe;
}

function jsonStringify(value: unknown): string {
    if (value === null || value === undefined) return '';
    return JSON.stringify(value, null, 2);
}

export default function Edit({ recipe }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Scrap Recipes', href: scrapRecipesIndex().url },
        { title: recipe.name, href: show.url(recipe.id) },
        { title: 'Edit', href: edit.url(recipe.id) },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: recipe.name ?? '',
        url: recipe.url ?? '',
        author: recipe.author ?? '',
        category: recipe.category ?? '',
        cuisine: recipe.cuisine ?? '',
        description: recipe.description ?? '',
        prep_time: recipe.prep_time ?? '',
        cook_time: recipe.cook_time ?? '',
        total_time: recipe.total_time ?? '',
        servings: recipe.servings ?? '',
        rating_value: recipe.rating_value ?? '',
        rating_count: recipe.rating_count?.toString() ?? '',
        comment_count: recipe.comment_count?.toString() ?? '',
        diet: recipe.diet ?? '',
        nutrition: jsonStringify(recipe.nutrition),
        ingredients: jsonStringify(recipe.ingredients),
        steps: jsonStringify(recipe.steps),
        images: jsonStringify(recipe.images),
        keywords: jsonStringify(recipe.keywords),
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put(update.url(recipe.id));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit: ${recipe.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={show.url(recipe.id)}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold">Edit Recipe</h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Basic Information</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label htmlFor="name">Name *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="url">URL</Label>
                                <Input
                                    id="url"
                                    type="url"
                                    value={data.url}
                                    onChange={(e) => setData('url', e.target.value)}
                                />
                                <InputError message={errors.url} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="author">Author</Label>
                                <Input
                                    id="author"
                                    value={data.author}
                                    onChange={(e) => setData('author', e.target.value)}
                                />
                                <InputError message={errors.author} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="category">Category</Label>
                                <Input
                                    id="category"
                                    value={data.category}
                                    onChange={(e) => setData('category', e.target.value)}
                                />
                                <InputError message={errors.category} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="cuisine">Cuisine</Label>
                                <Input
                                    id="cuisine"
                                    value={data.cuisine}
                                    onChange={(e) => setData('cuisine', e.target.value)}
                                />
                                <InputError message={errors.cuisine} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="diet">Diet</Label>
                                <Input
                                    id="diet"
                                    value={data.diet}
                                    onChange={(e) => setData('diet', e.target.value)}
                                />
                                <InputError message={errors.diet} className="mt-1" />
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                />
                                <InputError message={errors.description} className="mt-1" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Time & Servings */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Time & Servings</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <Label htmlFor="prep_time">Prep Time</Label>
                                <Input
                                    id="prep_time"
                                    value={data.prep_time}
                                    onChange={(e) => setData('prep_time', e.target.value)}
                                />
                                <InputError message={errors.prep_time} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="cook_time">Cook Time</Label>
                                <Input
                                    id="cook_time"
                                    value={data.cook_time}
                                    onChange={(e) => setData('cook_time', e.target.value)}
                                />
                                <InputError message={errors.cook_time} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="total_time">Total Time</Label>
                                <Input
                                    id="total_time"
                                    value={data.total_time}
                                    onChange={(e) => setData('total_time', e.target.value)}
                                />
                                <InputError message={errors.total_time} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="servings">Servings</Label>
                                <Input
                                    id="servings"
                                    value={data.servings}
                                    onChange={(e) => setData('servings', e.target.value)}
                                />
                                <InputError message={errors.servings} className="mt-1" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Rating */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Rating</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <Label htmlFor="rating_value">Rating Value</Label>
                                <Input
                                    id="rating_value"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="5"
                                    value={data.rating_value}
                                    onChange={(e) => setData('rating_value', e.target.value)}
                                />
                                <InputError message={errors.rating_value} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="rating_count">Rating Count</Label>
                                <Input
                                    id="rating_count"
                                    type="number"
                                    min="0"
                                    value={data.rating_count}
                                    onChange={(e) => setData('rating_count', e.target.value)}
                                />
                                <InputError message={errors.rating_count} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="comment_count">Comment Count</Label>
                                <Input
                                    id="comment_count"
                                    type="number"
                                    min="0"
                                    value={data.comment_count}
                                    onChange={(e) => setData('comment_count', e.target.value)}
                                />
                                <InputError message={errors.comment_count} className="mt-1" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* JSON Fields */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Data (JSON)</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label htmlFor="ingredients">Ingredients</Label>
                                <Textarea
                                    id="ingredients"
                                    value={data.ingredients}
                                    onChange={(e) => setData('ingredients', e.target.value)}
                                    rows={6}
                                    className="font-mono text-sm"
                                    placeholder='["ingredient 1", "ingredient 2"]'
                                />
                                <InputError message={errors.ingredients} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="steps">Steps</Label>
                                <Textarea
                                    id="steps"
                                    value={data.steps}
                                    onChange={(e) => setData('steps', e.target.value)}
                                    rows={6}
                                    className="font-mono text-sm"
                                    placeholder='[{"text": "Step 1"}]'
                                />
                                <InputError message={errors.steps} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="nutrition">Nutrition</Label>
                                <Textarea
                                    id="nutrition"
                                    value={data.nutrition}
                                    onChange={(e) => setData('nutrition', e.target.value)}
                                    rows={4}
                                    className="font-mono text-sm"
                                    placeholder='{"calories": "200 kcal"}'
                                />
                                <InputError message={errors.nutrition} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="images">Images</Label>
                                <Textarea
                                    id="images"
                                    value={data.images}
                                    onChange={(e) => setData('images', e.target.value)}
                                    rows={3}
                                    className="font-mono text-sm"
                                    placeholder='["https://example.com/image.jpg"]'
                                />
                                <InputError message={errors.images} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="keywords">Keywords</Label>
                                <Textarea
                                    id="keywords"
                                    value={data.keywords}
                                    onChange={(e) => setData('keywords', e.target.value)}
                                    rows={3}
                                    className="font-mono text-sm"
                                    placeholder='["keyword1", "keyword2"]'
                                />
                                <InputError message={errors.keywords} className="mt-1" />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={show.url(recipe.id)}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
