<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScrapCategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'type' => ['required', 'string', 'in:ania-gotuje,ze-smakiem-na-ty,poprostupycha,smaker'],
            'is_scraped' => ['required', 'boolean'],
        ];
    }
}
