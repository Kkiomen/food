<?php

namespace App\Services;

use App\Services\Contracts\RecipeScraperInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZeSmakiemNatyScraper implements RecipeScraperInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const BASE_URL = 'https://zesmakiemnaty.pl';

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

        // Recipe links are in .post-card .thumbnail-box a
        $links = $xpath->query('//div[contains(@class, "post-card")]//div[contains(@class, "thumbnail-box")]/a');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (empty($href) || str_contains($href, '#')) {
                continue;
            }

            // Skip category, tag, and other non-recipe links
            if (str_contains($href, '/category/') || str_contains($href, '/tag/') || str_contains($href, '/page/')) {
                continue;
            }

            // Must be a full URL from zesmakiemnaty.pl
            if (str_starts_with($href, self::BASE_URL) && !in_array($href, $urls)) {
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
            'author' => $this->extractAuthor($xpath),
            'published_at' => $this->extractDate($xpath),
            'modified_at' => $this->extractModifiedDate($xpath),
            'category' => $this->extractCategory($xpath),
            'cuisine' => null,
            'description' => $this->extractDescription($xpath),
            'prep_time' => $this->extractPrepTime($xpath),
            'cook_time' => null,
            'total_time' => $this->extractPrepTime($xpath),
            'servings' => null,
            'nutrition' => null,
            'ingredients' => $this->parseIngredients($xpath),
            'steps' => $this->parseSteps($xpath),
            'images' => $this->parseImages($xpath),
            'rating_value' => null,
            'rating_count' => null,
            'comment_count' => null,
            'diet' => null,
            'keywords' => $this->parseKeywords($xpath),
            'difficulty' => $this->extractDifficulty($xpath),
        ];
    }

    private function extractName(DOMXPath $xpath): ?string
    {
        // Title is in h1 inside article or .header-bg
        $nodes = $xpath->query('//article//h1 | //main//h1');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }

    private function extractAuthor(DOMXPath $xpath): ?string
    {
        // Try meta tag first
        $nodes = $xpath->query('//meta[@name="author"]/@content');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->nodeValue);
        }

        // Default author for this blog
        return 'Sylwia';
    }

    private function extractDate(DOMXPath $xpath): ?string
    {
        // Date is in .info-bar .date
        $nodes = $xpath->query('//div[contains(@class, "info-bar")]//div[contains(@class, "date")]');
        if ($nodes->length > 0) {
            $text = trim($nodes->item(0)->textContent);
            // Extract date like "3.01.2026"
            if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $text, $matches)) {
                return "{$matches[3]}-{$matches[2]}-{$matches[1]} 00:00:00";
            }
        }

        // Try og:article:published_time
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
        // From breadcrumbs
        $nodes = $xpath->query('//p[@id="breadcrumbs"]//a');
        if ($nodes->length >= 2) {
            // Usually the second breadcrumb is the category
            return trim($nodes->item($nodes->length - 1)->textContent);
        }
        return null;
    }

    private function extractDescription(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//meta[@property="og:description"]/@content');
        if ($nodes->length > 0) {
            return trim(html_entity_decode($nodes->item(0)->nodeValue, ENT_QUOTES, 'UTF-8'));
        }
        return null;
    }

    private function extractPrepTime(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//div[contains(@class, "info-bar")]//div[contains(@class, "time")]//strong');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }

    private function extractDifficulty(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//div[contains(@class, "info-bar")]//div[contains(@class, "rank")]//strong');
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }

    private function parseIngredients(DOMXPath $xpath): array
    {
        $ingredients = [];
        $items = [];

        // Ingredients are in .wp-block-group .wp-block-list li
        $nodes = $xpath->query('//div[contains(@class, "wp-block-group")]//ul[contains(@class, "wp-block-list")]/li');

        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if (!empty($text)) {
                $items[] = ['name' => $text, 'qty' => null];
            }
        }

        if (!empty($items)) {
            $ingredients[] = [
                'section' => null,
                'items' => $items,
            ];
        }

        return $ingredients;
    }

    private function parseSteps(DOMXPath $xpath): array
    {
        $steps = [];

        // Steps are in paragraphs inside .gutenberg-wrapper after "Przygotowanie:"
        $paragraphs = $xpath->query('//div[contains(@class, "gutenberg-wrapper")]//p');

        $foundPrzygotowanie = false;
        $stepNumber = 1;

        foreach ($paragraphs as $p) {
            $text = trim($p->textContent);

            if (empty($text) || mb_strlen($text) < 5) {
                continue;
            }

            // Skip if contains only "Przygotowanie:" header
            if (preg_match('/^Przygotowanie\s*:?\s*$/iu', $text)) {
                $foundPrzygotowanie = true;
                continue;
            }

            // If text starts with "Przygotowanie:", extract the rest
            if (preg_match('/^Przygotowanie\s*:\s*(.+)$/iu', $text, $matches)) {
                $foundPrzygotowanie = true;
                $text = trim($matches[1]);
                if (empty($text)) {
                    continue;
                }
            }

            // Skip ingredient-like content and closing phrases
            if (preg_match('/^Składniki/iu', $text)) {
                continue;
            }

            // Skip closing phrases
            if (preg_match('/^(Ciesz się smakiem|Smacznego)/iu', $text)) {
                continue;
            }

            // Only add steps after we found "Przygotowanie"
            if ($foundPrzygotowanie) {
                $steps[] = [
                    'step' => $stepNumber,
                    'name' => null,
                    'text' => $text,
                    'image' => null,
                ];
                $stepNumber++;
            }
        }

        return $steps;
    }

    private function parseImages(DOMXPath $xpath): ?array
    {
        $images = [];

        // Main image from .gutenberg-wrapper figure img
        $nodes = $xpath->query('//div[contains(@class, "gutenberg-wrapper")]//figure//img/@src');
        foreach ($nodes as $node) {
            $url = trim($node->nodeValue);
            if ($url && !in_array($url, $images)) {
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

        // Tags are in .tags a[rel="tag"]
        $nodes = $xpath->query('//div[contains(@class, "tags")]//a[@rel="tag"]');
        foreach ($nodes as $node) {
            $tag = trim($node->textContent);
            if ($tag && !in_array($tag, $keywords)) {
                $keywords[] = $tag;
            }
        }

        return !empty($keywords) ? $keywords : null;
    }
}
