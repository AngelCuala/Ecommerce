<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryPageController extends Controller
{
    // ── Full catalog ──────────────────────────────────────────────────────────
    public static function catalog(): array
    {
        return [
            [
                'name'  => 'Pet Supplies',
                'slug'  => 'pet-supplies',
                'icon'  => '🐾',
                'color' => '#FF6300',
                'subs'  => ['Dog Food & Treats', 'Cat Litter & Accessories', 'Aquariums & Fish Supplies', 'Bird Feeders & Food', 'Pet Grooming Products', 'Pet Health & Wellness'],
            ],
            [
                'name'  => 'Kids & Baby',
                'slug'  => 'kids-and-baby',
                'icon'  => '🍼',
                'color' => '#F472B6',
                'subs'  => ['Baby Clothes & Accessories', 'Toys & Games', 'Educational Materials', 'Strollers & Gear', 'Nursery Furniture', 'Safety and Health'],
            ],
            [
                'name'  => 'Electronics & Gadgets',
                'slug'  => 'electronics-and-gadgets',
                'icon'  => '📱',
                'color' => '#3B82F6',
                'subs'  => ['Mobile Phones & Accessories', 'Laptops, Desktops & Monitors', 'Audio & Video Equipment', 'Smart Home Devices', 'Cameras & Photography', 'Wearable Technology'],
            ],
            [
                'name'  => "Women's Apparel",
                'slug'  => 'womens-apparel',
                'icon'  => '👗',
                'color' => '#EC4899',
                'subs'  => ['Dresses & Skirts', 'Tops & Blouses', 'Activewear & Yoga Pants', 'Lingerie & Sleepwear', 'Jackets & Coats', 'Shoes & Accessories'],
            ],
            [
                'name'  => 'Sports & Outdoors',
                'slug'  => 'sports-and-outdoors',
                'icon'  => '⚽',
                'color' => '#10B981',
                'subs'  => ['Fitness Equipment', 'Camping & Hiking Gear', 'Sports Apparel', 'Cycling & Bikes', 'Water Sports', 'Team Sports Equipment'],
            ],
            [
                'name'  => 'Home & Garden',
                'slug'  => 'home-and-garden',
                'icon'  => '🏡',
                'color' => '#F59E0B',
                'subs'  => ['Kitchen Appliances', 'Furniture & Decor', 'Gardening Tools', 'Outdoor Living', 'Home Improvement Tools', 'Bedding & Bath'],
            ],
            [
                'name'  => "Men's Apparel",
                'slug'  => 'mens-apparel',
                'icon'  => '👔',
                'color' => '#6366F1',
                'subs'  => ['Suits & Blazers', 'Casual Shirts & Pants', 'Outerwear & Jackets', 'Activewear & Fitness Gear', 'Shoes & Accessories', 'Grooming Products'],
            ],
            [
                'name'  => 'Health & Beauty',
                'slug'  => 'health-and-beauty',
                'icon'  => '💄',
                'color' => '#EF4444',
                'subs'  => ['Skincare Products', 'Haircare Solutions', 'Makeup & Cosmetics', 'Personal Care Appliances', "Men's Grooming", 'Health Supplements'],
            ],
            [
                'name'  => 'Books & Media',
                'slug'  => 'books-and-media',
                'icon'  => '📚',
                'color' => '#8B5CF6',
                'subs'  => [],
            ],
            [
                'name'  => 'Food & Gourmet',
                'slug'  => 'food-and-gourmet',
                'icon'  => '🍽️',
                'color' => '#F97316',
                'subs'  => ['Baking Supplies & Ingredients', 'Coffee, Tea & Beverages', 'Snacks & Candy', 'Specialty Foods & International Cuisine', 'Organic and Health Foods', 'Meal Kits & Prepped Foods'],
            ],
            [
                'name'  => 'Furniture & Office',
                'slug'  => 'furniture-and-office',
                'icon'  => '🪑',
                'color' => '#78716C',
                'subs'  => ['Office Furniture', 'Desks & Tables', 'Office Chairs', 'Storage & Organization', 'Office Supplies', 'Printers & Office Equipment'],
            ],
            [
                'name'  => 'Jewelry & Watches',
                'slug'  => 'jewelry-and-watches',
                'icon'  => '💍',
                'color' => '#D97706',
                'subs'  => ['Necklaces & Pendants', 'Rings & Earrings', 'Bracelets & Bangles', 'Watches for Men & Women', 'Fashion Jewelry', 'Jewelry Storage & Care'],
            ],
        ];
    }

    // ── Index: redirect straight to the first category ───────────────────────
    public function index()
    {
        $catalog = self::catalog();
        return redirect()->route('categories.show', $catalog[0]['slug']);
    }

    // ── Show: products under a specific category / subcategory ───────────────
    public function show(Request $request, string $slug)
    {
        $catalog   = self::catalog();
        $active    = collect($catalog)->firstWhere('slug', $slug);
        $activeSub = $request->query('sub');

        if (! $active) {
            abort(404);
        }

        // Fetch matching products from the books table
        $query = Book::with(['category', 'images'])
            ->active()
            ->where('stock', '>', 0);

        // Match by category name (approximate) or slug
        $query->whereHas('category', function ($q) use ($active) {
            $q->where('name', 'like', '%' . $active['name'] . '%');
        });

        // Sort
        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'price_low'  => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            default      => $query->latest(),
        };

        $products = $query->get();

        // Subcategory filter is display-only for now (no sub-column in DB)
        // Products already filtered by main category above.

        return view('categories.show', compact('catalog', 'active', 'activeSub', 'products'));
    }
}
