<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScrapCategoryUpdateRequest;
use App\Models\ScrapCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScrapCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ScrapCategory::query();

        if ($search = $request->input('search')) {
            $query->where('url', 'like', "%{$search}%");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('is_scraped', $request->input('status') === 'scraped');
        }

        return Inertia::render('scrap-categories/index', [
            'categories' => $query->latest()->paginate(25)->withQueryString(),
            'filters' => [
                'search' => $search,
                'type' => $request->input('type'),
                'status' => $request->input('status'),
            ],
        ]);
    }

    public function show(ScrapCategory $scrapCategory): Response
    {
        return Inertia::render('scrap-categories/show', [
            'category' => $scrapCategory,
        ]);
    }

    public function edit(ScrapCategory $scrapCategory): Response
    {
        return Inertia::render('scrap-categories/edit', [
            'category' => $scrapCategory,
        ]);
    }

    public function update(ScrapCategoryUpdateRequest $request, ScrapCategory $scrapCategory): RedirectResponse
    {
        $scrapCategory->update($request->validated());

        return to_route('scrap-categories.show', $scrapCategory);
    }

    public function destroy(ScrapCategory $scrapCategory): RedirectResponse
    {
        $scrapCategory->delete();

        return to_route('scrap-categories.index');
    }
}
