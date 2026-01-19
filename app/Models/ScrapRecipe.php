<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapRecipe extends Model
{
    protected $fillable = [
        'name',
        'url',
        'author',
        'published_at',
        'modified_at',
        'category',
        'cuisine',
        'description',
        'prep_time',
        'cook_time',
        'total_time',
        'servings',
        'nutrition',
        'ingredients',
        'steps',
        'images',
        'rating_value',
        'rating_count',
        'comment_count',
        'diet',
        'keywords',
        'prepared_ingredients',
    ];

    protected function casts(): array
    {
        return [
            'nutrition' => 'array',
            'ingredients' => 'array',
            'steps' => 'array',
            'images' => 'array',
            'keywords' => 'array',
            'prepared_ingredients' => 'array',
            'published_at' => 'datetime',
            'modified_at' => 'datetime',
            'rating_value' => 'decimal:1',
        ];
    }
}
