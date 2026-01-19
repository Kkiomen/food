<?php

namespace App\Services;

use App\Services\Contracts\RecipeScraperInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PoprostuPychaScraper implements RecipeScraperInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const BASE_URL = 'https://poprostupycha.com.pl';

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

        // Recipe links are in article.article-blog a[itemprop="url"]
        $links = $xpath->query('//article[contains(@class, "article-blog")]//a[@itemprop="url"]');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (empty($href) || str_contains($href, '#')) {
                continue;
            }

            // Must contain /przepis/ to be a recipe
            if (str_contains($href, '/przepis/') && !in_array($href, $urls)) {
                $urls[] = rtrim($href, '/');
            }
        }

        return $urls;
    }

    public function getLastPageNumber(string $html): int
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // Pagination links contain /page/N/
        $paginationLinks = $xpath->query('//a[contains(@href, "/page/")]');

        $maxPage = 1;
        foreach ($paginationLinks as $link) {
            $href = $link->getAttribute('href');
            if (preg_match('#/page/(\d+)/?#', $href, $matches)) {
                $pageNum = (int) $matches[1];
                if ($pageNum > $maxPage) {
                    $maxPage = $pageNum;
                }
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

        return [
            'name' => $this->extractName($xpath),
            'url' => $url,
            'author' => 'Po Prostu Pycha',
            'published_at' => $this->extractDate($xpath),
            'modified_at' => $this->extractModifiedDate($xpath),
            'category' => $this->extractCategory($xpath),
            'cuisine' => null,
            'description' => $this->extractDescription($xpath),
            'prep_time' => $this->parseIsoDuration($this->extractMeta($xpath, 'prepTime')),
            'cook_time' => $this->parseIsoDuration($this->extractMeta($xpath, 'cookTime')),
            'total_time' => $this->calculateTotalTime($xpath),
            'servings' => $this->extractServings($xpath),
            'nutrition' => null,
            'ingredients' => $this->parseIngredients($xpath),
            'steps' => $this->parseSteps($xpath),
            'images' => $this->parseImages($xpath),
            'rating_value' => $this->extractRatingValue($xpath),
            'rating_count' => $this->extractRatingCount($xpath),
            'comment_count' => null,
            'diet' => null,
            'keywords' => $this->parseKeywords($xpath),
            'difficulty' => $this->extractDifficulty($xpath),
        ];
    }

    private function extractName(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//h1[@itemprop="name"] | //h1[contains(@class, "entry-title")]');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }

    private function extractMeta(DOMXPath $xpath, string $itemprop): ?string
    {
        $nodes = $xpath->query("//meta[@itemprop='{$itemprop}']/@content");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->nodeValue);
        }
        return null;
    }

    private function parseIsoDuration(?string $iso): ?string
    {
        if (!$iso || $iso === 'PT0M') {
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
                $parts[] = "{$minutes} min";
            }

            return implode(' ', $parts) ?: null;
        }

        return null;
    }

    private function calculateTotalTime(DOMXPath $xpath): ?string
    {
        $prepTime = $this->extractMeta($xpath, 'prepTime');
        $cookTime = $this->extractMeta($xpath, 'cookTime');

        $totalMinutes = 0;

        if ($prepTime && preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $prepTime, $matches)) {
            $totalMinutes += (isset($matches[1]) ? (int) $matches[1] * 60 : 0) + (isset($matches[2]) ? (int) $matches[2] : 0);
        }

        if ($cookTime && preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $cookTime, $matches)) {
            $totalMinutes += (isset($matches[1]) ? (int) $matches[1] * 60 : 0) + (isset($matches[2]) ? (int) $matches[2] : 0);
        }

        if ($totalMinutes === 0) {
            return null;
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours} " . ($hours === 1 ? 'godzina' : ($hours < 5 ? 'godziny' : 'godzin'));
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes} min";
        }

        return implode(' ', $parts) ?: null;
    }

    private function extractServings(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//*[@itemprop="recipeYield"]');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }

    private function extractDifficulty(DOMXPath $xpath): ?string
    {
        // Difficulty is near icon-easy in .prep-more-info
        $nodes = $xpath->query('//div[contains(@class, "prep-more-info")]//i[contains(@class, "icon-easy")]/following::p[1]');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        // Alternative: find "Poziom trudności" and get next p
        $nodes = $xpath->query('//div[contains(@class, "prep-more-info-p")]/p[contains(text(), "Poziom trudności")]/following-sibling::p[1]');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        return null;
    }

    private function extractDate(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//meta[@property="article:published_time"]/@content');
        if ($nodes->length > 0) {
            try {
                $date = new \DateTime($nodes->item(0)->nodeValue);
                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    private function extractModifiedDate(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//meta[@property="article:modified_time"]/@content');
        if ($nodes->length > 0) {
            try {
                $date = new \DateTime($nodes->item(0)->nodeValue);
                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    private function extractCategory(DOMXPath $xpath): ?string
    {
        // From breadcrumbs or category links
        $nodes = $xpath->query('//a[@rel="category tag"]');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }

    private function extractDescription(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//meta[@property="og:description"]/@content');
        if ($nodes->length > 0) {
            return trim(html_entity_decode($nodes->item(0)->nodeValue, ENT_QUOTES, 'UTF-8'));
        }

        // Fallback: first paragraph in recipe-desc
        $nodes = $xpath->query('//div[contains(@class, "recipe-desc")]//p[1]');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        return null;
    }

    private function extractRatingValue(DOMXPath $xpath): ?float
    {
        $nodes = $xpath->query('//*[@itemprop="ratingValue"]');
        if ($nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            return is_numeric($value) ? (float) $value : null;
        }
        return null;
    }

    private function extractRatingCount(DOMXPath $xpath): ?int
    {
        $nodes = $xpath->query('//*[@itemprop="ratingCount"]');
        if ($nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            return is_numeric($value) ? (int) $value : null;
        }
        return null;
    }

    private function parseIngredients(DOMXPath $xpath): array
    {
        $ingredients = [];
        $currentSection = null;
        $currentItems = [];

        // Check for ingredient groups (headers like "Składniki:" or section headers)
        $container = $xpath->query('//div[@id="ingredients"]');
        if ($container->length === 0) {
            // Fallback: just get all ingredients
            $nodes = $xpath->query('//li[@itemprop="recipeIngredient"]');
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if (!empty($text)) {
                    $currentItems[] = ['name' => $text, 'qty' => null];
                }
            }

            if (!empty($currentItems)) {
                $ingredients[] = ['section' => null, 'items' => $currentItems];
            }

            return $ingredients;
        }

        // Parse with sections support
        $ingredientContainer = $container->item(0);
        $headers = $xpath->query('.//p[contains(@class, "ingredients-p")]', $ingredientContainer);
        $lists = $xpath->query('.//ul', $ingredientContainer);

        if ($headers->length > 1) {
            // Multiple sections
            foreach ($headers as $index => $header) {
                $sectionName = trim($header->textContent);
                $sectionName = rtrim($sectionName, ':');
                $items = [];

                if ($lists->length > $index) {
                    $liNodes = $xpath->query('.//li', $lists->item($index));
                    foreach ($liNodes as $li) {
                        $text = trim($li->textContent);
                        if (!empty($text)) {
                            $items[] = ['name' => $text, 'qty' => null];
                        }
                    }
                }

                if (!empty($items)) {
                    $ingredients[] = [
                        'section' => $sectionName !== 'Składniki' ? $sectionName : null,
                        'items' => $items,
                    ];
                }
            }
        } else {
            // Single section
            $nodes = $xpath->query('.//li[contains(@class, "ingredient")]', $ingredientContainer);
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if (!empty($text)) {
                    $currentItems[] = ['name' => $text, 'qty' => null];
                }
            }

            if (!empty($currentItems)) {
                $ingredients[] = ['section' => null, 'items' => $currentItems];
            }
        }

        return $ingredients;
    }

    private function parseSteps(DOMXPath $xpath): array
    {
        $steps = [];

        // Steps are in div.step[itemprop="recipeInstructions"]
        $stepNodes = $xpath->query('//div[contains(@class, "step")][@itemprop="recipeInstructions"]');

        foreach ($stepNodes as $index => $stepNode) {
            // Get step number from .step-numb
            $numbNode = $xpath->query('.//div[contains(@class, "step-numb")]', $stepNode);
            $stepNum = $numbNode->length > 0 ? (int) trim($numbNode->item(0)->textContent) : $index + 1;

            // Get step text from div[itemprop="text"]
            $textNode = $xpath->query('.//*[@itemprop="text"]', $stepNode);
            $text = '';
            if ($textNode->length > 0) {
                $text = trim($textNode->item(0)->textContent);
            }

            // Get step image
            $imageNode = $xpath->query('.//img/@src | .//img/@data-lazy-src', $stepNode);
            $image = null;
            if ($imageNode->length > 0) {
                $imgSrc = trim($imageNode->item(0)->nodeValue);
                // Skip placeholder SVGs
                if (!str_starts_with($imgSrc, 'data:image/svg')) {
                    $image = $imgSrc;
                }
            }

            if (!empty($text)) {
                $steps[] = [
                    'step' => $stepNum,
                    'name' => null,
                    'text' => $text,
                    'image' => $image,
                ];
            }
        }

        return $steps;
    }

    private function parseImages(DOMXPath $xpath): ?array
    {
        $images = [];

        // Main image from picture[itemprop="image"] img
        $nodes = $xpath->query('//picture[@itemprop="image"]//img/@src');
        foreach ($nodes as $node) {
            $url = trim($node->nodeValue);
            if ($url && !str_starts_with($url, 'data:') && !in_array($url, $images)) {
                $images[] = $url;
            }
        }

        // Also try og:image
        if (empty($images)) {
            $nodes = $xpath->query('//meta[@property="og:image"]/@content');
            foreach ($nodes as $node) {
                $url = trim($node->nodeValue);
                if ($url && !in_array($url, $images)) {
                    $images[] = $url;
                }
            }
        }

        return !empty($images) ? $images : null;
    }

    private function parseKeywords(DOMXPath $xpath): ?array
    {
        $keywords = [];

        // Tags from category links
        $nodes = $xpath->query('//a[@rel="category tag"] | //a[@rel="tag"]');
        foreach ($nodes as $node) {
            $tag = trim($node->textContent);
            if ($tag && !in_array($tag, $keywords)) {
                $keywords[] = $tag;
            }
        }

        return !empty($keywords) ? $keywords : null;
    }
}
