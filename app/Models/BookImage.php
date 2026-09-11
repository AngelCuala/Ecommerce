<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookImage extends Model
{
    protected $fillable = ['book_id', 'path', 'label', 'sort_order'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** Full public URL */
    public function url(): string
    {
        return asset('storage/' . $this->path);
    }
}
