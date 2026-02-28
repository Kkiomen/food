<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScrapRecipeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'author' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'cuisine' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prep_time' => ['nullable', 'string', 'max:255'],
            'cook_time' => ['nullable', 'string', 'max:255'],
            'total_time' => ['nullable', 'string', 'max:255'],
            'servings' => ['nullable', 'string', 'max:255'],
            'rating_value' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'rating_count' => ['nullable', 'integer', 'min:0'],
            'comment_count' => ['nullable', 'integer', 'min:0'],
            'diet' => ['nullable', 'string', 'max:255'],
            'nutrition' => ['nullable', 'json'],
            'ingredients' => ['nullable', 'json'],
            'steps' => ['nullable', 'json'],
            'images' => ['nullable', 'json'],
            'keywords' => ['nullable', 'json'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $jsonFields = ['nutrition', 'ingredients', 'steps', 'images', 'keywords'];

        foreach ($jsonFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                // Keep as string for json validation rule; decode after validation
            }
        }
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        $jsonFields = ['nutrition', 'ingredients', 'steps', 'images', 'keywords'];

        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true);
            }
        }

        return $data;
    }
}
