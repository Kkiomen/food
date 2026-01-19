<?php

namespace App\Services;

use App\Models\ScrapRecipe;
use GrokPHP\Client\Config\ChatOptions;
use GrokPHP\Client\Enums\Model;
use GrokPHP\Laravel\Facades\GrokAI;
use GuzzleHttp\Client as GuzzleClient;
use OpenAI\Laravel\Facades\OpenAI;

class PrepareIngredientsService
{
    public const API_OPENAI = 'openai';
    public const API_GROK = 'grok';

    private string $apiProvider = self::API_OPENAI;
    private string $openaiModel = 'gpt-4o-mini';
    private Model $grokModel = Model::GROK_2_1212; // GROK_2_1212 i nowsze wspierają structured outputs
    private string $systemPrompt = '
        Twoim zadaniem jest stworzyć ustruktaryzowany json ze skladnikami.

        Skladniki moga byc podzielone na sekcje.

        ## O nazwie składnika
        Nazwa ma być prosta w taki sposób aby można było utworzyć tabele ze składnikami i dodać relacje

        ### Zmiana nazwy składnika (Przykłady:
        "ser mozarella": "mozarella"
        "naturalne masło orzechowe": masło orzechowe
        "świeżo siekany szczypior": "szczypiorek"
        "tortille pszenne": "tortilla"
        "pół główki kapusty": "Kapusta"
        "płynny miód": "miód"

        ## WAŻNE: Parsowanie quantity i unit

        ### Quantity (ilość) - MUSI być liczbą (number), nie stringiem!
        - "1 łyżka" → quantity: 1, unit: "łyżka"
        - "2 łyżki" → quantity: 2, unit: "łyżka"
        - "pół łyżeczki" → quantity: 0.5, unit: "łyżeczka"
        - "1/2 łyżeczki" → quantity: 0.5, unit: "łyżeczka"
        - "70 ml" → quantity: 70, unit: "ml"
        - "20-22 sztuki" → quantity: 20 (użyj pierwszej wartości lub średniej)
        - "2 płaskie łyżeczki" → quantity: 2, unit: "łyżeczka"
        - "1 łyżka - do 30 g" → quantity: 1, unit: "łyżka" (ignoruj dodatkowe informacje)
        - "70 ml lub więcej" → quantity: 70, unit: "ml"

        ### Unit (jednostka) - MUSI być czystą jednostką bez liczby i w formie podstawowej (mianownik liczby pojedynczej)!
        - NIE: "1 łyżka", "2 łyżki", "pół łyżeczki", "łyżeczki", "sztuki"
        - TAK: "łyżka", "łyżeczka", "ml", "sztuka", "g", "kg", "szklanka", "szczypta"

        ### Ujednolicanie jednostek do formy podstawowej (mianownik liczby pojedynczej):
        ZAWSZE używaj formy podstawowej jednostki, niezależnie od liczby:
        - "łyżki" → "łyżka"
        - "łyżeczki" → "łyżeczka"
        - "sztuki" → "sztuka"
        - "szklanki" → "szklanka"
        - "szczypty" → "szczypta"
        - "gramy" → "gram"
        - "kilogramy" → "kilogram"
        - "litry" → "litr"
        - "mililitry" → "mililitr"
        - "kawałki" → "kawałek"
        - "ząbki" (czosnku) → "ząbek"
        - "główki" → "główka"
        - "pęczki" → "pęczek"
        - "plasterki" → "plasterek"
        - "szt" → "sztuka"
        - "g" → "g" (skrót pozostaje)
        - "kg" → "kg" (skrót pozostaje)
        - "ml" → "ml" (skrót pozostaje)
        - "l" → "l" (skrót pozostaje)

        ### Zasady parsowania:
        1. Quantity MUSI być liczbą (może być ułamkiem dziesiętnym jak 0.5)
        2. Unit MUSI być tylko jednostką, bez liczby
        3. Jeśli w tekście jest "pół" lub "1/2" → quantity: 0.5
        4. Jeśli jest zakres (np. "20-22") → użyj pierwszej wartości
        5. Ignoruj dodatkowe informacje po jednostce (np. "- do 30 g", "lub więcej")

        ## Informacje o składnikach:
        Musi składać sie z nazwy (name), ilosci (quantity jako liczba), jednostki (unit), kategorii (type), wymagalności (required) i zamiennikach (substitutes), które może być pustą tablicą jeśli nie istnieje

        ## Kategoria składnika (type):
        Określa kategorię składnika. Możliwe wartości:
        - "owoc" - owoce (jabłka, banany, pomarańcze, jagody, itp.)
        - "warzywo" - warzywa (marchew, pomidory, cebula, papryka, itp.)
        - "mięso" - mięso i produkty mięsne (kurczak, wołowina, wieprzowina, wędliny, itp.)
        - "ryba" - ryby i owoce morza (łosoś, tuńczyk, krewetki, itp.)
        - "nabiał" - produkty mleczne (mleko, ser, jogurt, śmietana, masło, itp.)
        - "zboże" - produkty zbożowe (mąka, makaron, ryż, kasza, chleb, itp.)
        - "przyprawa" - przyprawy i zioła (sól, pieprz, papryka, bazylia, oregano, itp.)
        - "tłuszcz" - tłuszcze i oleje (olej, oliwa, masło, margaryna, itp.)
        - "orzech" - orzechy i nasiona (orzechy włoskie, migdały, sezam, itp.)
        - "napój" - napoje (woda, mleko, sok, wino, itp.)
        - "słodycz" - słodycze i słodziki (cukier, miód, czekolada, itp.)
        - "inny" - inne składniki, które nie pasują do powyższych kategorii

        ## Wymagalność składnika (required):
        Określa czy składnik jest wymagany czy opcjonalny:
        - true - składnik wymagany (domyślnie dla większości składników głównych)
        - false - składnik opcjonalny (np. przyprawy, dodatki do smaku, dekoracje)

        Przykłady:
        - Mięso, warzywa główne, makaron → type: "mięso"/"warzywo"/"zboże", required: true
        - Przyprawy (sól, pieprz, papryka) → type: "przyprawa", required: false
        - Dekoracje (natka, sezam) → type: "przyprawa" lub "inny", required: false

        ## Przykłady poprawnego formatu:
        {
            "ingredients": [
                {
                    "section": "Składniki na sos",
                    "items": [
                        {
                            "name": "pasta miso",
                            "quantity": 1,
                            "unit": "łyżka",
                            "type": "przyprawa",
                            "required": true,
                            "substitutes": []
                        },
                        {
                            "name": "cukier puder",
                            "quantity": 0.5,
                            "unit": "łyżeczka",
                            "type": "słodycz",
                            "required": false,
                            "substitutes": []
                        },
                        {
                            "name": "woda",
                            "quantity": 70,
                            "unit": "ml",
                            "type": "napój",
                            "required": true,
                            "substitutes": []
                        },
                        {
                            "name": "natka pietruszki",
                            "quantity": 1,
                            "unit": "łyżka",
                            "type": "przyprawa",
                            "required": false,
                            "substitutes": []
                        },
                        {
                            "name": "kurczak",
                            "quantity": 500,
                            "unit": "gram",
                            "type": "mięso",
                            "required": true,
                            "substitutes": []
                        },
                        {
                            "name": "pomidor",
                            "quantity": 2,
                            "unit": "sztuka",
                            "type": "warzywo",
                            "required": true,
                            "substitutes": []
                        }
                    ]
                }
            ]
        }

    ';

    public function useOpenAI(string $model = 'gpt-4o-mini'): self
    {
        $this->apiProvider = self::API_OPENAI;
        $this->openaiModel = $model;

        return $this;
    }

    public function useGrok(Model $model = Model::GROK_2): self
    {
        $this->apiProvider = self::API_GROK;
        $this->grokModel = $model;

        return $this;
    }

    public function getApiProvider(): string
    {
        return $this->apiProvider;
    }

    public function prepareIngredients(ScrapRecipe $recipe): string
    {

        if (empty($recipe->ingredients)) {
            return json_encode(['ingredients' => []]);
        }


        $result = [];

        foreach ($recipe->ingredients as $ingredient) {
            $element = [];
            $sectionName = $this->prepareSectionName($ingredient['section'] ?? null);
            if(in_array($sectionName, [
                'Do podania'
            ])){
                continue;
            }


            $element['section'] = $sectionName;
            $items = [];

            if (!isset($ingredient['items']) || !is_array($ingredient['items'])) {
                continue;
            }

            foreach ($ingredient['items'] as $item) {
                if (!isset($item['name'])) {
                    continue;
                }

                $items[] = [
                    'name' => $item['name'],
                    'quantity' => $item['qty'] ?? $item['quantity'] ?? null,
                ];
            }

            if (!empty($items)) {
                $element['items'] = $items;
                $result[] = $element;
            }
        }


        $jsonSchema = [
            'type' => 'object',
            'properties' => [
                'ingredients' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'section' => [
                                'anyOf' => [
                                    ['type' => 'string'],
                                    ['type' => 'null'],
                                ],
                                'description' => 'Nazwa sekcji składników',
                            ],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => [
                                            'type' => 'string',
                                            'description' => 'Nazwa składnika w podstawowej formie',
                                        ],
                                        'quantity' => [
                                            'type' => 'number',
                                            'description' => 'Ilość składnika jako liczba (np. 1, 2, 0.5, 70). Musi być liczbą, nie stringiem z tekstem.',
                                        ],
                                        'unit' => [
                                            'type' => 'string',
                                            'description' => 'Jednostka miary w formie podstawowej (mianownik liczby pojedynczej), np. "łyżka" (nie "łyżki"), "łyżeczka" (nie "łyżeczki"), "sztuka" (nie "sztuki")',
                                        ],
                                        'type' => [
                                            'type' => 'string',
                                            'enum' => ['owoc', 'warzywo', 'mięso', 'ryba', 'nabiał', 'zboże', 'przyprawa', 'tłuszcz', 'orzech', 'napój', 'słodycz', 'inny'],
                                            'description' => 'Kategoria składnika: owoc, warzywo, mięso, ryba, nabiał, zboże, przyprawa, tłuszcz, orzech, napój, słodycz, inny',
                                        ],
                                        'required' => [
                                            'type' => 'boolean',
                                            'description' => 'Czy składnik jest wymagany: true - wymagany, false - opcjonalny (np. przyprawy, dekoracje)',
                                        ],
                                        'substitutes' => [
                                            'type' => 'array',
                                            'description' => 'Lista zamienników składnika',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'name' => [
                                                        'type' => 'string',
                                                        'description' => 'Nazwa zamiennika w podstawowej formie',
                                                    ],
                                                    'quantity' => [
                                                        'type' => 'number',
                                                        'description' => 'Ilość zamiennika jako liczba (np. 1, 2, 0.5, 70). Musi być liczbą, nie stringiem z tekstem.',
                                                    ],
                                                    'unit' => [
                                                        'type' => 'string',
                                                        'description' => 'Jednostka miary zamiennika w formie podstawowej (mianownik liczby pojedynczej), np. "łyżka" (nie "łyżki"), "łyżeczka" (nie "łyżeczki"), "sztuka" (nie "sztuki")',
                                                    ],
                                                ],
                                                'required' => ['name', 'quantity', 'unit'],
                                                'additionalProperties' => false,
                                            ],
                                        ],
                                    ],
                                    'required' => ['name', 'quantity', 'unit', 'type', 'required', 'substitutes'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['section', 'items'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['ingredients'],
            'additionalProperties' => false,
        ];

        if ($this->apiProvider === self::API_GROK) {
            $content = $this->callGrokApi($result, $jsonSchema);
        } else {
            $content = $this->callOpenAIApi($result, $jsonSchema);
        }


        return $content;
    }

    private function prepareSectionName(?string $section): ?string
    {
        if (empty($section)) {
            return null;
        }

        $section = str_replace([' [ więcej ]', '[ więcej ]', '[więcej]'], '', $section);

        return $section;
    }

    private function callOpenAIApi(array $result, array $jsonSchema): string
    {
        $apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        $baseUri = config('openai.base_uri');
        
        // Upewnij się, że base_uri jest pełnym URL - jeśli nie jest ustawione, użyj domyślnego
        if (empty($baseUri) || $baseUri === '/' || !filter_var($baseUri, FILTER_VALIDATE_URL)) {
            $baseUri = 'https://api.openai.com/v1';
        }
        
        // Upewnij się, że base_uri kończy się na /
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
                    'content' => json_encode($result),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'ingredients_schema',
                    'strict' => true,
                    'schema' => $jsonSchema,
                ],
            ],
        ];

        $client = new GuzzleClient([
            'base_uri' => $baseUri,
            'timeout' => 30,
            'verify' => false, // Wyłącz weryfikację SSL dla Windows (w produkcji użyj prawidłowego certyfikatu)
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

            // Logowanie informacji o tokenach i kosztach
            $usage = $responseData['usage'] ?? [];
            $inputTokens = $usage['prompt_tokens'] ?? 0;
            $outputTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? 0;
            
            \Illuminate\Support\Facades\Log::info("OpenAI API usage", [
                'model' => $this->openaiModel,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'cost' => $this->calculateOpenAICost($this->openaiModel, $inputTokens, $outputTokens),
            ]);

            return $responseData['choices'][0]['message']['content'];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to call OpenAI API", [
                'error' => $e->getMessage(),
                'base_uri' => $baseUri,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function callGrokApi(array $result, array $jsonSchema): string
    {
        // Grok API wspiera structured outputs (json_schema) dla modeli grok-2-1212 i nowszych
        // Używamy bezpośredniego wywołania HTTP, ponieważ PHP SDK może nie wspierać response_format jeszcze
        $apiKey = config('grok.api_key') ?? env('GROK_API_KEY');

        $baseUri = config('grok.base_uri', 'https://api.x.ai/v1');
        
        // Upewnij się, że base_uri kończy się na /
        $baseUri = rtrim($baseUri, '/') . '/';

        $payload = [
            'model' => $this->grokModel->value,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($result),
                ],
            ],
            'temperature' => 0.3,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'ingredients_schema',
                    'strict' => true,
                    'schema' => $jsonSchema,
                ],
            ],
        ];

        $client = new GuzzleClient([
            'base_uri' => $baseUri,
            'timeout' => 30,
            'verify' => false, // Wyłącz weryfikację SSL dla Windows (w produkcji użyj prawidłowego certyfikatu)
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

            // Logowanie informacji o tokenach i kosztach
            $usage = $responseData['usage'] ?? [];
            $inputTokens = $usage['prompt_tokens'] ?? 0;
            $outputTokens = $usage['completion_tokens'] ?? 0;
            $totalTokens = $usage['total_tokens'] ?? 0;
            
            \Illuminate\Support\Facades\Log::info("Grok API usage", [
                'model' => $this->grokModel->value,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
            ]);

            return $responseData['choices'][0]['message']['content'];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to call Grok API with structured outputs", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Jeśli Grok nie działa, użyj OpenAI jako fallback
            \Illuminate\Support\Facades\Log::info("Falling back to OpenAI API");
            return $this->callOpenAIApi($result, $jsonSchema);
        }
    }

    private function calculateOpenAICost(string $model, int $inputTokens, int $outputTokens): ?float
    {
        // Ceny za 1M tokenów (w USD) - aktualizuj zgodnie z aktualnymi cenami OpenAI
        $pricing = [
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60], // $0.15/$0.60 per 1M tokens
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00], // $2.50/$10.00 per 1M tokens
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00], // $10.00/$30.00 per 1M tokens
            'gpt-4' => ['input' => 30.00, 'output' => 60.00], // $30.00/$60.00 per 1M tokens
        ];

        if (!isset($pricing[$model])) {
            return null;
        }

        $inputCost = ($inputTokens / 1_000_000) * $pricing[$model]['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing[$model]['output'];

        return round($inputCost + $outputCost, 6);
    }
}
