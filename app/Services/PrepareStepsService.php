<?php

namespace App\Services;

use App\Models\ScrapRecipe;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;

class PrepareStepsService
{
    private string $openaiModel = 'gpt-4o-mini';

    private string $systemPrompt = '
        Jesteś doświadczonym kucharzem i autorem przepisów kulinarnych.

        Twoim zadaniem jest przepisać kroki przepisu tak, aby były:
        1. **Jasne i zrozumiałe** - osoba czytająca przepis nie może się niczego domyślać
        2. **Kompletne** - każdy krok musi zawierać wszystkie potrzebne informacje (temperatury, czasy, ilości)
        3. **Szczegółowe** - opisz dokładnie co robić, jak mieszać, na co zwrócić uwagę
        4. **Zachęcające** - pisz w sposób, który zaciekawi czytelnika i zachęci do gotowania
        5. **Praktyczne** - dodaj wskazówki, triki i podpowiedzi tam gdzie to potrzebne

        ## Zasady:
        - Pisz po polsku
        - Każdy krok powinien być jedną logiczną czynnością
        - Jeśli oryginalny krok jest zbyt ogólny - rozwiń go
        - Jeśli brakuje informacji (np. temperatura pieczenia, czas smażenia) - dodaj sensowne wartości na podstawie kontekstu
        - Nie pomijaj żadnych informacji z oryginalnych kroków
        - Używaj przyjaznego, ale profesjonalnego tonu
        - Nie dodawaj kroków typu "podaj" czy "smacznego" - skup się na gotowaniu

        ## Format odpowiedzi:
        Zwróć tablicę kroków w formacie JSON. Każdy krok ma pole "text" z opisem.
    ';

    public function prepareSteps(ScrapRecipe $recipe): string
    {
        if (empty($recipe->steps)) {
            return json_encode(['steps' => []]);
        }

        $input = [
            'name' => $recipe->name,
            'steps' => $recipe->steps,
        ];

        $jsonSchema = [
            'type' => 'object',
            'properties' => [
                'steps' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => [
                                'type' => 'string',
                                'description' => 'Szczegółowy opis kroku przepisu',
                            ],
                        ],
                        'required' => ['text'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['steps'],
            'additionalProperties' => false,
        ];

        return $this->callOpenAIApi($input, $jsonSchema);
    }

    private function callOpenAIApi(array $input, array $jsonSchema): string
    {
        $apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        $baseUri = config('openai.base_uri');

        if (empty($baseUri) || $baseUri === '/' || !filter_var($baseUri, FILTER_VALIDATE_URL)) {
            $baseUri = 'https://api.openai.com/v1';
        }

        $baseUri = rtrim($baseUri, '/') . '/';

        $payload = [
            'model' => $this->openaiModel,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($input),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'steps_schema',
                    'strict' => true,
                    'schema' => $jsonSchema,
                ],
            ],
        ];

        $client = new GuzzleClient([
            'base_uri' => $baseUri,
            'timeout' => 60,
            'verify' => false,
        ]);

        try {
            $response = $client->post('chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (!isset($responseData['choices'][0]['message']['content'])) {
                throw new \Exception("Invalid response structure from OpenAI API");
            }

            $usage = $responseData['usage'] ?? [];
            $inputTokens = $usage['prompt_tokens'] ?? 0;
            $outputTokens = $usage['completion_tokens'] ?? 0;

            Log::info("OpenAI API usage (PrepareSteps)", [
                'model' => $this->openaiModel,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ]);

            return $responseData['choices'][0]['message']['content'];
        } catch (\Exception $e) {
            Log::error("Failed to call OpenAI API (PrepareSteps)", [
                'error' => $e->getMessage(),
                'base_uri' => $baseUri,
            ]);
            throw $e;
        }
    }
}
