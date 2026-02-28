<?php

namespace App\Jobs;

use App\Models\ScrapRecipe;
use App\Services\PrepareStepsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PrepareStepsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ScrapRecipe $recipe
    ) {
        $this->onQueue('chatgpt');
    }

    public function uniqueId(): string
    {
        return 'prepare-steps-' . $this->recipe->id;
    }

    public function handle(PrepareStepsService $prepareStepsService): void
    {
        if (empty($this->recipe->steps)) {
            Log::warning("Skipping recipe {$this->recipe->id} - no steps");
            return;
        }

        if ($this->recipe->prepared_steps !== null) {
            Log::info("Skipping recipe {$this->recipe->id} - already has prepared_steps");
            return;
        }

        try {
            Log::info("Preparing steps for recipe: {$this->recipe->id} - {$this->recipe->name}");

            $preparedStepsJson = $prepareStepsService->prepareSteps($this->recipe);

            $preparedSteps = json_decode($preparedStepsJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON response: " . json_last_error_msg());
            }

            if (!$preparedSteps || !isset($preparedSteps['steps'])) {
                throw new \Exception("Invalid response structure - missing 'steps' key");
            }

            $this->recipe->update([
                'prepared_steps' => $preparedSteps['steps'],
            ]);

            Log::info("Successfully prepared steps for recipe: {$this->recipe->id}");
        } catch (\Throwable $e) {
            Log::error("Error preparing steps for recipe {$this->recipe->id}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to prepare steps for recipe: {$this->recipe->id}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
