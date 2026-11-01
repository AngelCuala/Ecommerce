<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'description'];

    /** A renamed ALVY category is renamed in every store that uses it. */
    protected static function booted(): void
    {
        static::updated(function (Category $c) {
            if ($c->wasChanged('name')) {
                StoreCategory::where('category_id', $c->id)->update(['name' => $c->name]);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
