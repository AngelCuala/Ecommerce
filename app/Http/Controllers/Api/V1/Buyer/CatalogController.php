<?php

namespace App\Http\Controllers\Api\V1\Buyer;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\CategoryPageController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StoreController;
use App\Support\ApiJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Browsing (B1–B3): the web's home, search, category, product and store pages, as data. */
class CatalogController extends Controller
{
    use CallsWeb;

    public function home(): JsonResponse
    {
        $d = $this->pageData($this->web(HomeController::class, 'index'));

        return response()->json([
            'featured'   => $d['featured']->map(fn ($p) => ApiJson::productCard($p))->values(),
            'trending'   => $d['trending']->map(fn ($p) => ApiJson::productCard($p))->values(),
            'categories' => $d['shortcuts']->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'count' => $c->count,
                'image_url' => CategoryPageController::photo($c->slug), 'wide_image_url' => CategoryPageController::photo($c->slug, true)])->values(),
            // The big banner: the admin's event slides (Admin → Home Banners) first, then ALVY's own — as on the website.
            'banners'    => collect(\App\Models\HomeBanner::slides())->map(fn ($s) => [
                'image_url'     => $s['img'],
                'focus'         => $s['pos'],
                'eyebrow'       => $s['eyebrow'],
                'title'         => $s['title'],
                'highlight'     => $s['mark'],
                'subtitle'      => $s['sub'],
                'button'        => $s['button'],
                'category_slug' => $s['slug'],
                'category_name' => \App\Models\HomeBanner::categoryName($s['slug']),
            ])->values(),
        ]);
    }

    /** Search and filters (search, category, min_price, max_price, rating, location, sort, page). */
    public function shop(Request $request): JsonResponse
    {
        $response = $this->web(ShopController::class, 'index');

        // The web opens a category page for a plain category link; so does the app.
        if ($response instanceof RedirectResponse && preg_match('#/categories/([^/?]+)#', $response->getTargetUrl(), $m)) {
            return $this->category($request, $m[1]);
        }

        $d = $this->pageData($response);

        return response()->json(ApiJson::page($d['products'], fn ($p) => ApiJson::productCard($p)) + [
            'term'       => $d['term'],
            'suggestion' => $d['suggestion'],
            'notes'      => $d['notes'],
            'categories' => $d['categories']->values(),
            'locations'  => $d['locations']->values(),
            // The store filter, and stores whose name matches the search (shown above the products).
            'stores'     => collect($d['stores'] ?? [])->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values(),
            'matched_stores' => collect($d['matchedStores'] ?? [])->map(fn ($s) => ['id' => (int) $s->user_id, 'name' => $s->shop_name,
                'location' => trim($s->municipality . ', ' . $s->province, ', '), 'logo_url' => ApiJson::file($s->logo_path)])->values(),
            'category_page' => null,
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['categories' => collect(CategoryPageController::catalog())->map(fn ($c) => [
            'name' => $c['name'], 'slug' => $c['slug'], 'subcategories' => $c['subs'] ?? $c['subcategories'] ?? [],
            'image_url' => CategoryPageController::photo($c['slug']),   // the same picture as the home shortcuts
            'wide_image_url' => CategoryPageController::photo($c['slug'], true),
        ])->values()]);
    }

    /** A category page (sort: newest, price_low, price_high). */
    public function category(Request $request, string $slug): JsonResponse
    {
        $d = $this->pageData($this->web(CategoryPageController::class, 'show', ['slug' => $slug]));

        return response()->json([
            'category'    => ['name' => $d['active']['name'], 'slug' => $d['active']['slug']],
            'subcategory' => $d['activeSub'],
            // Its subcategories, for the app's pills on the category page.
            'subcategories' => array_values($d['active']['subs'] ?? $d['active']['subcategories'] ?? []),
            'data'        => $d['products']->map(fn ($p) => ApiJson::productCard($p))->values(),
            'category_page' => $d['active']['slug'],
        ]);
    }

    public function product(string $slug): JsonResponse
    {
        $d = $this->pageData($this->web(ProductController::class, 'show', ['slug' => $slug]));

        return response()->json(['product' => ApiJson::productDetail($d)]);
    }

    /** A store page (scat = a store category id or "none", category, sub, page). */
    public function store(int $sellerId): JsonResponse
    {
        $d = $this->pageData($this->web(StoreController::class, 'show', ['sellerId' => $sellerId]));

        return response()->json(ApiJson::page($d['products'], fn ($p) => ApiJson::productCard($p)) + [
            'store'      => ApiJson::store($d['seller']) + ['followers' => $d['followers'], 'following' => $d['following'],
                'product_count' => (int) ($d['productCount'] ?? 0)],   // the website's "N products"
            'store_categories' => $d['storeCategories']->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'count' => $c->products_count,
                'children' => $c->children->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'count' => $s->products_count])->values(),
            ])->values(),
            'uncategorized' => $d['uncategorized'],
            'categories' => collect($d['categories'])->map(fn ($c, $name) => ['name' => $name, 'count' => $c['count'], 'subs' => $c['subs']])->values(),
            'chosen'     => $d['chosen'] ? ['id' => $d['chosen']->id, 'name' => $d['chosen']->name] : null,
            'vouchers'   => $d['vouchers']->map(fn ($v) => ApiJson::voucher($v, in_array($v->id, $d['claimed'])))->values(),
        ]);
    }

    public function policy(string $key): JsonResponse
    {
        $d = $this->pageData($this->web(PolicyController::class, 'show', ['key' => $key]));
        // The published text, else the website's default (its <style> block is for the web page only).
        $html = $d['policy']?->content ?? \App\Models\PlatformPolicy::defaultContent($key);
        $html = $html ? trim(preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html)) : null;

        return response()->json([
            'key'          => $d['key'],
            'title'        => $d['title'],
            'content'      => $html ?: null,
            'updated_at'   => $d['policy']?->updated_at?->toIso8601String(),
            'others'       => $key === 'about_us' ? [] : collect(\App\Models\PlatformPolicy::defaultPolicies())
                ->reject(fn ($p) => in_array($p['key'], [$key, 'about_us'], true))->map(fn ($p) => ['key' => $p['key'], 'title' => $p['title']])->values(),
        ]);
    }
}
