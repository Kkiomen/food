<?php

namespace App\Services\Contracts;

interface RecipeScraperInterface
{
    public function fetchPage(string $url): ?string;

    public function parseCategoryPage(string $html): array;

    public function getLastPageNumber(string $html): int;

    public function scrapeRecipe(string $url): ?array;

    public function parseRecipe(string $html, string $url): array;
}
