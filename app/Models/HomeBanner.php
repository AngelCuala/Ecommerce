<?php

namespace App\Models;

use App\Http\Controllers\CategoryPageController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A slide of the home page's big banner, added by the admin for an event or sale (website and app).
 * Live slides come first; ALVY's built-in slides follow unless the admin hides them.
 */
class HomeBanner extends Model
{
    /** Admin's choice: show only their slides (ALVY's built-in ones are hidden while any of theirs is live). */
    private const SETTINGS_FILE = 'settings/home-banners.json';

    public static function hideDefaults(): bool
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::SETTINGS_FILE) && (bool) (json_decode($disk->get(self::SETTINGS_FILE), true)['hide_defaults'] ?? false);
    }

    public static function setHideDefaults(bool $hide): void
    {
        Storage::disk('local')->put(self::SETTINGS_FILE, json_encode(['hide_defaults' => $hide]));
    }

    protected $fillable = [
        'image', 'eyebrow', 'title', 'highlight', 'subtitle', 'button_label', 'category_slug', 'focus',
        'starts_at', 'ends_at', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'is_active' => 'boolean',
    ];

    public const FOCUS = ['top' => 'center 20%', 'center' => 'center 50%', 'bottom' => 'center 80%'];

    /** On and within its dates. */
    public function scopeLive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** Live, Scheduled, Ended or Off — for the admin's list. */
    public function state(): string
    {
        if (! $this->is_active) {
            return 'Off';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'Scheduled';
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'Ended';
        }

        return 'Live';
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image);
    }

    /** ALVY's own slides (photos: Unsplash License, public/images/banners). */
    public static function defaults(): array
    {
        return [
            ['img' => asset('images/banners/shop.jpg'),    'pos' => 'center 30%', 'eyebrow' => 'Shop local', 'title' => 'Bringing more to your', 'mark' => 'cart', 'sub' => 'Everyday finds from local sellers, delivered to your barangay.', 'button' => 'Shop now', 'slug' => null],
            ['img' => asset('images/banners/fashion.jpg'), 'pos' => 'center 50%', 'eyebrow' => 'Apparel', 'title' => 'Refresh your', 'mark' => 'wardrobe', 'sub' => 'Tops, dresses and outerwear from shops near you.', 'button' => 'Shop now', 'slug' => 'womens-apparel'],
            ['img' => asset('images/banners/tech.jpg'),    'pos' => 'center 60%', 'eyebrow' => 'Electronics', 'title' => 'Gadgets for', 'mark' => 'every day', 'sub' => 'Laptops, phones, audio and the accessories that go with them.', 'button' => 'Shop now', 'slug' => 'electronics-and-gadgets'],
            ['img' => asset('images/banners/home.jpg'),    'pos' => 'center 60%', 'eyebrow' => 'Home & Garden', 'title' => 'Make home a little', 'mark' => 'cozier', 'sub' => 'Furniture, decor and plants to make a space your own.', 'button' => 'Shop now', 'slug' => 'home-and-garden'],
        ];
    }

    /** The slides the home page shows now: the admin's live ones first, then ALVY's own (unless hidden). */
    public static function slides(): array
    {
        $live = self::live()->orderBy('sort_order')->latest('id')->get()->map(fn (self $b) => [
            'id'      => $b->id,
            'img'     => $b->imageUrl(),
            'pos'     => self::FOCUS[$b->focus] ?? self::FOCUS['center'],
            'eyebrow' => $b->eyebrow,
            'title'   => $b->title,
            'mark'    => $b->highlight,
            'sub'     => $b->subtitle,
            'button'  => $b->button_label ?: 'Shop now',
            'slug'    => $b->category_slug,
        ])->all();

        $hide = $live && self::hideDefaults();

        return $hide ? $live : array_merge($live, self::defaults());
    }

    /** Where a slide's button goes: its category page, or all categories. */
    public static function urlFor(?string $slug): string
    {
        return $slug ? route('categories.show', $slug) : route('categories.page');
    }

    /** Category name for a slug (the app shows it as the page title). */
    public static function categoryName(?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        return collect(CategoryPageController::catalog())->firstWhere('slug', $slug)['name'] ?? null;
    }
}
