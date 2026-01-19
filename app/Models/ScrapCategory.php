<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapCategory extends Model
{
    protected $fillable = [
        'url',
        'is_scraped',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'is_scraped' => 'boolean',
        ];
    }

    public function scopeUnscraped($query, string $type = 'ania-gotuje')
    {
        return $query->where('is_scraped', false)->where('type', $type);
    }

    public function markAsScraped(): void
    {
        $this->update(['is_scraped' => true]);
    }
}
