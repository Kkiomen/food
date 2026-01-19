<?php

namespace App\Services;

use App\Services\Contracts\RecipeScraperInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmakerScraper implements RecipeScraperInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const BASE_URL = 'https://smaker.pl';

    public function fetchPage(string $url): ?string
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
                ])->timeout(30)->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning("Failed to fetch page: {$url}", ['status' => $response->status()]);
            return null;
        } catch (\Exception $e) {
            Log::error("Error fetching page: {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function parseCategoryPage(string $html): array
    {
        $urls = [];
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // Match links with pattern: /przepisy-{category}/przepis-{name},{id},smaker.html
        $links = $xpath->query('//a[contains(@href, ",smaker.html")]');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            // Skip if it's a hash link
            if (str_contains($href, '#')) {
                continue;
            }

            // Match the smaker recipe URL pattern
            if (preg_match('#/przepisy-[\w-]+/przepis-[\w-]+,\d+,[\w]+\.html$#', $href)) {
                // Ensure full URL
                if (!str_starts_with($href, 'http')) {
                    $href = self::BASE_URL . $href;
                }

                if (!in_array($href, $urls)) {
                    $urls[] = $href;
                }
            }
        }

        return $urls;
    }

    public function getLastPageNumber(string $html): int
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // Look for pagination links with ?page= parameter
        $paginationLinks = $xpath->query('//div[contains(@class, "pagination--component")]//a[contains(@href, "page=")]');

        $maxPage = 1;
        foreach ($paginationLinks as $link) {
            $href = $link->getAttribute('href');
            if (preg_match('/[?&]page=(\d+)/', $href, $matches)) {
                $pageNum = (int) $matches[1];
                if ($pageNum > $maxPage) {
                    $maxPage = $pageNum;
                }
            }
        }

        // Also check for the last page span element
        $lastPageSpan = $xpath->query('//span[contains(@class, "pagination--item") and contains(@class, "-last")]//a');
        if ($lastPageSpan->length > 0) {
            $lastPageText = trim($lastPageSpan->item(0)->textContent);
            if (is_numeric($lastPageText) && (int) $lastPageText > $maxPage) {
                $maxPage = (int) $lastPageText;
            }
        }

        return $maxPage;
    }

    public function scrapeRecipe(string $url): ?array
    {
        $html = $this->fetchPage($url);
        if (!$html) {
            return null;
        }

        return $this->parseRecipe($html, $url);
    }

    public function parseRecipe(string $html, string $url): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // Extract JSON-LD structured data
        $jsonLd = $this->extractJsonLd($html);

        // Extract recipe ID from URL and try to get sectioned ingredients from JavaScript
        $recipeId = $this->extractRecipeIdFromUrl($url);
        $jsIngredients = $recipeId ? $this->extractIngredientsFromJs($html, $recipeId) : null;

        return [
            'name' => $jsonLd['name'] ?? $this->extractTitle($xpath),
            'url' => $url,
            'author' => $jsonLd['author']['name'] ?? null,
            'published_at' => $this->parseDate($jsonLd['datePublished'] ?? null),
            'modified_at' => null,
            'category' => $jsonLd['recipeCategory'] ?? null,
            'cuisine' => $jsonLd['recipeCuisine'] ?? null,
            'description' => $jsonLd['description'] ?? null,
            'prep_time' => $this->parseIsoDuration($jsonLd['prepTime'] ?? null),
            'cook_time' => $this->parseIsoDuration($jsonLd['cookTime'] ?? null),
            'total_time' => $this->parseIsoDuration($jsonLd['totalTime'] ?? null),
            'servings' => $jsonLd['recipeYield'] ?? null,
            'nutrition' => null,
            'ingredients' => $jsIngredients ?? $this->parseIngredients($jsonLd),
            'steps' => $this->parseSteps($jsonLd),
            'images' => $this->parseImages($jsonLd),
            'rating_value' => $this->parseRatingValue($jsonLd),
            'rating_count' => $this->parseRatingCount($jsonLd),
            'comment_count' => null,
            'diet' => null,
            'keywords' => $jsonLd['keywords'] ?? null,
        ];
    }

    private function extractRecipeIdFromUrl(string $url): ?int
    {
        // URL pattern: /przepisy-{category}/przepis-{name},{id},{author}.html
        if (preg_match('/,(\d+),[\w]+\.html/', $url, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function extractIngredientsFromJs(string $html, int $recipeId): ?array
    {
        // Search for the recipe data in JavaScript with the specific ID
        // Pattern: "id":1947672,...,"ingredients":[...]
        $pattern = '/"id"\s*:\s*' . $recipeId . '[^"]*".*?"ingredients"\s*:\s*(\[(?:[^\[\]]|\[(?:[^\[\]]|\[[^\]]*\])*\])*\])/s';

        if (preg_match($pattern, $html, $matches)) {
            $ingredientsJson = $matches[1];
            $ingredientsData = json_decode($ingredientsJson, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($ingredientsData)) {
                return $this->parseJsIngredients($ingredientsData);
            }
        }

        return null;
    }

    private function parseJsIngredients(array $ingredientsData): array
    {
        $sections = [];

        foreach ($ingredientsData as $group) {
            $sectionName = $group['name'] ?? null;
            $items = [];

            if (isset($group['items']) && is_array($group['items'])) {
                foreach ($group['items'] as $item) {
                    if (isset($item['name'])) {
                        $items[] = [
                            'name' => trim($item['name']),
                            'qty' => null,
                        ];
                    }
                }
            }

            if (!empty($items)) {
                $sections[] = [
                    'section' => $sectionName,
                    'items' => $items,
                ];
            }
        }

        return $sections;
    }

    private function extractJsonLd(string $html): array
    {
        // Find all JSON-LD scripts and look for Recipe type
        if (preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches)) {
            foreach ($matches[1] as $jsonString) {
                // Clean control characters that might break JSON parsing
                $jsonString = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $jsonString);
                $jsonString = preg_replace('/\s+/', ' ', $jsonString);

                $data = json_decode($jsonString, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['@type']) && $data['@type'] === 'Recipe') {
                    return $data;
                }
            }
        }

        return [];
    }

    private function extractTitle(DOMXPath $xpath): ?string
    {
        $titleNode = $xpath->query('//h1[contains(@class, "title")]');
        if ($titleNode->length > 0) {
            return trim($titleNode->item(0)->textContent);
        }
        return null;
    }

    private function parseIsoDuration(?string $iso): ?string
    {
        if (!$iso) {
            return null;
        }

        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $iso, $matches)) {
            $hours = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;
            $minutes = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;

            $parts = [];
            if ($hours > 0) {
                $parts[] = "{$hours} " . ($hours === 1 ? 'godzina' : ($hours < 5 ? 'godziny' : 'godzin'));
            }
            if ($minutes > 0) {
                $parts[] = "{$minutes} " . ($minutes === 1 ? 'minuta' : ($minutes < 5 ? 'minuty' : 'minut'));
            }

            return implode(' ', $parts) ?: null;
        }

        return null;
    }

    private function parseDate(?string $dateString): ?string
    {
        if (!$dateString) {
            return null;
        }

        try {
            $date = new \DateTime($dateString);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseIngredients(array $jsonLd): array
    {
        if (!isset($jsonLd['recipeIngredient']) || !is_array($jsonLd['recipeIngredient'])) {
            return [];
        }

        $items = [];
        foreach ($jsonLd['recipeIngredient'] as $ingredient) {
            $items[] = [
                'name' => trim($ingredient),
                'qty' => null,
            ];
        }

        return [
            [
                'section' => null,
                'items' => $items,
            ],
        ];
    }

    private function parseSteps(array $jsonLd): array
    {
        if (!isset($jsonLd['recipeInstructions']) || !is_array($jsonLd['recipeInstructions'])) {
            return [];
        }

        $steps = [];
        $stepNumber = 1;

        foreach ($jsonLd['recipeInstructions'] as $instruction) {
            if (isset($instruction['@type']) && $instruction['@type'] === 'HowToStep') {
                $steps[] = [
                    'step' => $stepNumber,
                    'name' => $instruction['name'] ?? null,
                    'text' => $instruction['text'] ?? '',
                    'image' => $instruction['image'] ?? null,
                ];
                $stepNumber++;
            }
        }

        return $steps;
    }

    private function parseImages(array $jsonLd): ?array
    {
        $images = [];

        if (isset($jsonLd['image'])) {
            if (is_array($jsonLd['image'])) {
                if (isset($jsonLd['image']['url'])) {
                    $images[] = $jsonLd['image']['url'];
                } elseif (isset($jsonLd['image'][0])) {
                    foreach ($jsonLd['image'] as $img) {
                        if (is_string($img)) {
                            $images[] = $img;
                        } elseif (isset($img['url'])) {
                            $images[] = $img['url'];
                        }
                    }
                }
            } elseif (is_string($jsonLd['image'])) {
                $images[] = $jsonLd['image'];
            }
        }

        return !empty($images) ? $images : null;
    }

    private function parseRatingValue(array $jsonLd): ?float
    {
        if (isset($jsonLd['aggregateRating']['ratingValue'])) {
            $value = $jsonLd['aggregateRating']['ratingValue'];
            return is_numeric($value) ? (float) $value : null;
        }
        return null;
    }

    private function parseRatingCount(array $jsonLd): ?int
    {
        if (isset($jsonLd['aggregateRating']['ratingCount'])) {
            $value = $jsonLd['aggregateRating']['ratingCount'];
            return is_numeric($value) ? (int) $value : null;
        }
        return null;
    }
}
