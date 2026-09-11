<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'description'];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /** Generate a slug from the name on the fly */
    public function getSlugAttribute(): string
    {
        return Str::slug($this->name);
    }

    /** Default icon based on name if none stored */
    public function getIconAttribute(): string
    {
        $icons = [
            'fiction' => '📚', 'book' => '📚',
            'magazine' => '📰',
            'music' => '💿', 'vinyl' => '💿', 'cd' => '💿',
            'movie' => '🎬', 'film' => '🎬', 'dvd' => '🎬',
            'game' => '🎮',
            'education' => '🎓',
        ];
        $name = strtolower($this->name);
        foreach ($icons as $key => $icon) {
            if (str_contains($name, $key)) return $icon;
        }
        return '📖';
    }
}
