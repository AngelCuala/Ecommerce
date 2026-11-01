<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seller\BookController;
use App\Models\Product;
use App\Support\ApiJson;
use App\Support\SellerJson;
use Illuminate\Http\JsonResponse;

/** Inventory (S3–S5): the web's product list, form, and product actions (BookController). */
class ProductsController extends Controller
{
    use CallsWeb;

    /** status: active, draft, low_stock, out_of_stock, review, archived · search. */
    public function index(): JsonResponse
    {
        $d = $this->pageData($this->web(BookController::class, 'index'));

        // status, search, category_id, min_price, max_price, sort, page (25 a page, like the website).
        return response()->json(ApiJson::page($d['books'], fn ($p) => SellerJson::productRow($p)) + [
            'status'     => $d['status'],
            'counts'     => $d['counts'],
            'sort'       => $d['sort'],
            'sorts'      => \App\Http\Controllers\Seller\BookController::SORTS,
            'categories' => collect($d['categories'])->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values(),
            'low_stock_default' => (int) config('marketplace.low_stock_threshold', 10),
        ]);
    }

    /** The form's lists: platform categories and subcategories, specs per category, addresses, … */
    public function form(): JsonResponse
    {
        return response()->json(self::formOptions($this->pageData($this->web(BookController::class, 'create'))));
    }

    public function show(Product $product): JsonResponse
    {
        $d = $this->pageData($this->web(BookController::class, 'edit', ['book' => $product]));

        return response()->json(['product' => SellerJson::productForm($d['book'])] + self::formOptions($d));
    }

    private static function formOptions(array $d): array
    {
        return [
            'categories'      => $d['categories']->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            // The store's registered line of business: products are listed under it.
            'business_category_id' => $d['businessCategoryId'] ?? null,
            'low_stock_default' => (int) config('marketplace.low_stock_threshold', 10),
            'subcategories'   => (object) $d['subcategoryMap'],
            'spec_schemas'    => (object) $d['specSchemas'],
            'addresses'       => $d['addresses']->map(fn ($a) => ApiJson::address($a))->values(),
            'statuses'        => $d['statuses'],
            'conditions'      => $d['conditions'],
            // The seller's categories (ALVY category ids), listed first in the product's Category.
            'my_category_ids' => array_values($d['myCategoryIds']),
            'store_categories' => $d['storeCategories']->map(fn ($c) => ['id' => $c->id, 'name' => $c->name,
                'children' => $c->children->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()])->values(),
            'max_images'      => Product::MAX_IMAGES,
            'max_variation_types' => \App\Models\ProductVariation::MAX_TYPES,
            'max_variation_options' => \App\Models\ProductVariation::MAX_OPTIONS,
        ];
    }

    /**
     * Save a new product with the web's form fields (multipart): title, category_id, description, price,
     * stock, weight_kg, images[], variations[n][name|price|stock|sku|image], specs[…], action=draft, …
     */
    public function store(): JsonResponse
    {
        $before = Product::where('seller_id', auth()->id())->max('id') ?? 0;
        $json = $this->actionJson($this->web(BookController::class, 'store'));
        if ($json->getStatusCode() === 200 && ($p = Product::where('seller_id', auth()->id())->where('id', '>', $before)->latest('id')->first())) {
            $json->setData($json->getData(true) + ['product' => SellerJson::productForm($p)]);
        }
        return $json;
    }

    /** Same fields as store; removed_images[] and image_order[] change the saved photos. */
    public function update(Product $product): JsonResponse
    {
        return $this->withProduct($product, $this->web(BookController::class, 'update', ['book' => $product]));
    }

    public function destroy(Product $product): JsonResponse
    {
        return $this->actionJson($this->web(BookController::class, 'destroy', ['book' => $product]));
    }

    /** A copy saved as a draft; the answer has the copy to open. */
    public function duplicate(Product $product): JsonResponse
    {
        $response = $this->web(BookController::class, 'duplicate', ['book' => $product]);
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200 && preg_match('#/books/(\d+)/edit#', (string) $json->getData(true)['redirect'], $m)) {
            $json->setData($json->getData(true) + ['product' => SellerJson::productForm(Product::findOrFail((int) $m[1]))]);
        }
        return $json;
    }

    /** action: deactivate, activate or delete · ids[]. */
    public function bulk(): JsonResponse
    {
        return $this->actionJson($this->web(BookController::class, 'bulk'));
    }

    public function endSale(Product $product): JsonResponse
    {
        return $this->withProduct($product, $this->web(BookController::class, 'endSale', ['book' => $product]));
    }

    public function archive(Product $product): JsonResponse
    {
        return $this->withProduct($product, $this->web(BookController::class, 'archive', ['book' => $product]));
    }

    public function unarchive(Product $product): JsonResponse
    {
        return $this->withProduct($product, $this->web(BookController::class, 'unarchive', ['book' => $product]));
    }

    /** stock (0–99,999). */
    public function stock(Product $product): JsonResponse
    {
        return $this->withProduct($product, $this->web(BookController::class, 'updateStock', ['book' => $product]));
    }

    private function withProduct(Product $product, mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + ['product' => SellerJson::productForm($product->fresh())]);
        }
        return $json;
    }
}
