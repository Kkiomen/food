<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScrapRecipeUpdateRequest;
use App\Models\ScrapRecipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScrapRecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ScrapRecipe::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('cuisine', 'like', "%{$search}%");
            });
        }

        return Inertia::render('scrap-recipes/index', [
            'recipes' => $query->latest()->paginate(25)->withQueryString(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(ScrapRecipe $scrapRecipe): Response
    {
        return Inertia::render('scrap-recipes/show', [
            'recipe' => $scrapRecipe,
        ]);
    }

    public function edit(ScrapRecipe $scrapRecipe): Response
    {
        return Inertia::render('scrap-recipes/edit', [
            'recipe' => $scrapRecipe,
        ]);
    }

    public function update(ScrapRecipeUpdateRequest $request, ScrapRecipe $scrapRecipe): RedirectResponse
    {
        $scrapRecipe->update($request->validated());

        return to_route('scrap-recipes.show', $scrapRecipe);
    }

    public function destroy(ScrapRecipe $scrapRecipe): RedirectResponse
    {
        $scrapRecipe->delete();

        return to_route('scrap-recipes.index');
    }
}
