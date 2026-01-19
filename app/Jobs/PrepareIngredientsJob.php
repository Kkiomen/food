<?php

namespace App\Jobs;

use App\Models\ScrapRecipe;
use App\Services\PrepareIngredientsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PrepareIngredientsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ScrapRecipe $recipe
    ) {
        $this->onQueue('prepare_ingredients');
    }

    /**
     * Execute the job.
     */
    public function handle(PrepareIngredientsService $prepareIngredientsService): void
    {
        if (empty($this->recipe->ingredients)) {
            Log::warning("Skipping recipe {$this->recipe->id} - no ingredients");
            return;
        }

        try {
            Log::info("Preparing ingredients for recipe: {$this->recipe->id} - {$this->recipe->name}");

            $preparedIngredientsJson = $prepareIngredientsService->prepareIngredients($this->recipe);
            
            Log::debug("API response for recipe {$this->recipe->id}", [
                'response_length' => strlen($preparedIngredientsJson),
                'response_preview' => substr($preparedIngredientsJson, 0, 200),
            ]);

            $preparedIngredients = json_decode($preparedIngredientsJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("JSON decode error for recipe {$this->recipe->id}", [
                    'json_error' => json_last_error_msg(),
                    'response' => substr($preparedIngredientsJson, 0, 500),
                ]);
                throw new \Exception("Invalid JSON response: " . json_last_error_msg());
            }

            if (!$preparedIngredients || !isset($preparedIngredients['ingredients'])) {
                Log::error("Failed to prepare ingredients for recipe {$this->recipe->id} - invalid response structure", [
                    'response' => $preparedIngredientsJson,
                ]);
                throw new \Exception("Invalid response structure - missing 'ingredients' key");
            }

            $this->recipe->update([
                'prepared_ingredients' => $preparedIngredients,
            ]);

            Log::info("Successfully prepared ingredients for recipe: {$this->recipe->id}");
        } catch (\Throwable $e) {
            Log::error("Error preparing ingredients for recipe {$this->recipe->id}", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to prepare ingredients for recipe: {$this->recipe->id}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
