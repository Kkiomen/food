<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeRecipeJob;
use App\Models\ScrapCategory;
use App\Models\ScrapRecipe;
use App\Services\PrepareIngredientsService;
use GrokPHP\Client\Config\ChatOptions;
use GrokPHP\Client\Enums\Model;
use GrokPHP\Laravel\Facades\GrokAI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class TestController extends Controller
{
    public function index(Request $request, PrepareIngredientsService $prepareIngredientsService)
    {
//        $scrapRecipe = ScrapRecipe::where('id', 23)->first();
//        $prepareIngredientsService->prepareIngredients($scrapRecipe);
//
//        // Parametry z requestu lub domyślne wartości
//        $model = $request->input('model', 'gpt-4o-mini');
//        $systemPrompt = $request->input('system_prompt', 'You are a helpful assistant that returns data in JSON format.');
//        $userPrompt = $request->input('user_prompt', 'Hello!');
//
//        try {
//            $response = OpenAI::chat()->create([
//                'model' => $model,
//                'messages' => [
//                    [
//                        'role' => 'system',
//                        'content' => $systemPrompt,
//                    ],
//                    [
//                        'role' => 'user',
//                        'content' => $userPrompt,
//                    ],
//                ],
//                'response_format' => [
//                    'type' => 'json_object',
//                ],
//            ]);
//
//            $content = $response->choices[0]->message->content;
//            $json = json_decode($content, true);
//
//            return response()->json([
//                'success' => true,
//                'model' => $model,
//                'raw_response' => $content,
//                'parsed_json' => $json['response'],
//            ]);
//        } catch (\Exception $e) {
//            return response()->json([
//                'success' => false,
//                'error' => $e->getMessage(),
//            ], 500);
//        }

//        $response = GrokAI::chat(
//            [['role' => 'user', 'content' => 'Hello Grok!']],
//            new ChatOptions(model: Model::GROK_2)
//        );
//
//        echo $response->content();


//        $page = Http::get('https://aniagotuje.pl/przepisy/dania-miesne');
//        $page = Http::get('https://zesmakiemnaty.pl/jarzynowa-zupa-z-kasza-jeczmienna/');

//        echo $page->body();
//
//        $html = $page->body();
//
//        Storage::put('pages/przepis-jarzynowa-zupa.html', $html);


//- ======================================================================
//- ======================================================================

        $categories = ScrapCategory::where('is_scraped', 0)->where('type', 'smaker')->get();
        foreach ($categories as $category) {
            ScrapeRecipeJob::dispatch($category);
        }




//- ======================================================================
//- ======================================================================
//        $page = Http::get('https://www.kwestiasmaku.com/');
//        $page = Http::get('https://kuchnialidla.pl/ciulim-lelowski-zapiekanka-ziemniaczana-z-kurczakiem');
//        $page = Http::get('https://aniastarmach.pl/przepis/kurczak-z-dynia-i-pomidorami//');
//        $page = Http::get('https://zesmakiemnaty.pl/kebabiki-z-miesa-mielonego-na-patyczkach/');

//        $array = [
//            [
//                'url' => 'https://smaker.pl/przepisy-dania-glowne',
//                'path' => 'pages/przepisy-smaker-dania-glowne.html',
//            ],[
//                'url' => 'https://smaker.pl/przepisy-dania-glowne/przepis-pulpety-w-sosie-pieczarkowym,1947672,smaker.html',
//                'path' => 'pages/przepis-smaker-pulpety-w-sosie-pieczarkowym.html',
//            ],
//        ];

//        foreach ($array as $item) {
//            $page = Http::get($item['url']);
//
//            $html = $page->body();
//
//            Storage::put($item['path'], $html);
//        }


//        dd($page->body());

//        return 'fsd';
    }
}

