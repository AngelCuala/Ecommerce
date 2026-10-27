<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seller\FlashSaleController;
use App\Http\Controllers\Seller\PromotionController;
use App\Http\Controllers\Seller\StoreCategoryController;
use App\Http\Controllers\Seller\VoucherController;
use App\Models\FlashSale;
use App\Models\Promotion;
use App\Models\StoreCategory;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;

/** Store vouchers, flash sales, bundles & promos and store categories (S2, S6, S7) through the web. */
class MarketingController extends Controller
{
    use CallsWeb;

    // ── Store vouchers ───────────────────────────────────────────

    public function vouchers(): JsonResponse
    {
        $d = $this->pageData($this->web(VoucherController::class, 'index'));
        return response()->json(['vouchers' => collect($d['vouchers'])->map(fn ($v) => self::voucher($v))->values()]);
    }

    /** The voucher form: an empty one, or $voucher to edit (locked fields once claimed). */
    public function voucherForm(?Voucher $voucher = null): JsonResponse
    {
        $d = $this->pageData($voucher
            ? $this->web(VoucherController::class, 'edit', ['voucher' => $voucher])
            : $this->web(VoucherController::class, 'create'));
        $v = $d['voucher'];

        return response()->json([
            'voucher'  => $v->exists ? self::voucher($v) : null,
            'locked'   => $v->exists && ($v->used_count > 0 || $v->claims()->exists()),
            'products' => collect($d['sellerProducts'])->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'product_code' => $p->product_code])->values(),
        ]);
    }

    /** code, name, discount_type (fixed/percent), discount_value, max_discount, min_spend, starts_at, ends_at, usage_limit, per_user_limit, product_ids[], is_active, is_claimable. */
    public function storeVoucher(): JsonResponse           { return $this->actionJson($this->web(VoucherController::class, 'store')); }
    public function updateVoucher(Voucher $voucher): JsonResponse { return $this->actionJson($this->web(VoucherController::class, 'update', ['voucher' => $voucher])); }
    public function toggleVoucher(Voucher $voucher): JsonResponse { return $this->actionJson($this->web(VoucherController::class, 'toggle', ['voucher' => $voucher])); }
    public function deleteVoucher(Voucher $voucher): JsonResponse { return $this->actionJson($this->web(VoucherController::class, 'destroy', ['voucher' => $voucher])); }

    private static function voucher(Voucher $v): array
    {
        return [
            'id' => $v->id, 'code' => $v->code, 'name' => $v->name, 'label' => $v->discountLabel(), 'state' => $v->stateLabel(),
            'discount_type' => $v->discount_type, 'discount_value' => (float) $v->discount_value,
            'max_discount' => $v->max_discount !== null ? (float) $v->max_discount : null, 'min_spend' => (float) $v->min_spend,
            'starts_at' => $v->starts_at?->format('Y-m-d\TH:i'), 'ends_at' => $v->ends_at?->format('Y-m-d\TH:i'),
            'usage_limit' => $v->usage_limit, 'per_user_limit' => $v->per_user_limit, 'used_count' => (int) $v->used_count,
            'claims' => $v->claims()->count(), 'is_active' => (bool) $v->is_active, 'is_claimable' => (bool) $v->is_claimable,
            'product_ids' => array_values(array_map('intval', (array) ($v->product_ids ?? []))),
        ];
    }

    // ── Flash sales ──────────────────────────────────────────────

    public function flashSales(): JsonResponse
    {
        $d = $this->pageData($this->web(FlashSaleController::class, 'index'));

        return response()->json([
            'sales' => collect($d['sales']->items())->map(fn ($s) => [
                'id' => $s->id, 'product' => $s->product?->title, 'product_id' => $s->product_id,
                'flash_price' => (float) $s->flash_price, 'quantity' => (int) $s->quantity, 'sold' => (int) $s->sold, 'remaining' => $s->remaining(),
                'starts_at' => $s->starts_at?->toIso8601String(), 'ends_at' => $s->ends_at?->toIso8601String(), 'state' => $s->stateLabel(),
                'reminders' => (int) $s->reminders_count,
            ])->values(),
            'has_more' => $d['sales']->hasMorePages(),
            'products' => collect($d['products'])->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'price' => $p->regular_price, 'stock' => (int) $p->stock])->values(),
        ]);
    }

    /** product_id, flash_price, quantity, starts_at, ends_at. */
    public function storeFlashSale(): JsonResponse             { return $this->actionJson($this->web(FlashSaleController::class, 'store')); }
    public function deleteFlashSale(FlashSale $flashSale): JsonResponse { return $this->actionJson($this->web(FlashSaleController::class, 'destroy', ['flashSale' => $flashSale])); }

    // ── Bundles & promos ─────────────────────────────────────────

    public function promotions(): JsonResponse
    {
        $d = $this->pageData($this->web(PromotionController::class, 'index'));

        return response()->json([
            'promotions' => collect($d['promotions'])->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'type' => $p->type, 'label' => $p->label(), 'is_active' => (bool) $p->is_active,
                'product_ids' => $p->product_ids, 'min_qty' => $p->min_qty, 'discount_percent' => $p->discount_percent,
                'free_product_id' => $p->free_product_id, 'free_qty' => $p->free_qty,
                'starts_at' => $p->starts_at?->toIso8601String(), 'ends_at' => $p->ends_at?->toIso8601String(),
            ])->values(),
            'products' => collect($d['products'])->map(fn ($p) => ['id' => $p->id, 'title' => $p->title])->values(),
            'types' => [Promotion::BUNDLE => 'Bundle deal', Promotion::BXGY => 'Buy X get Y free'],
        ]);
    }

    /** name, type (bundle/bxgy), product_ids[], min_qty, discount_percent | free_product_id + free_qty, starts_at, ends_at. */
    public function storePromotion(): JsonResponse                  { return $this->actionJson($this->web(PromotionController::class, 'store')); }
    public function togglePromotion(Promotion $promotion): JsonResponse { return $this->actionJson($this->web(PromotionController::class, 'toggle', ['promotion' => $promotion])); }
    public function deletePromotion(Promotion $promotion): JsonResponse { return $this->actionJson($this->web(PromotionController::class, 'destroy', ['promotion' => $promotion])); }

    // ── Store categories ─────────────────────────────────────────

    public function storeCategories(): JsonResponse
    {
        $d = $this->pageData($this->web(StoreCategoryController::class, 'index'));

        return response()->json(['categories' => collect($d['categories'])->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'is_hidden' => (bool) $c->is_hidden, 'products' => (int) $c->products_count,
            'category_id' => $c->category_id, 'from_alvy' => $c->category_id !== null,
            'children' => $c->children->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'is_hidden' => (bool) $s->is_hidden, 'products' => (int) $s->products_count])->values(),
        ])->values(), 'max_name' => StoreCategory::MAX_NAME,
            // ALVY categories the store can still add (POST category_id).
            'available' => collect($d['available'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()]);
    }

    /** category_id: an ALVY category (see "available"). */
    public function storeCategory(): JsonResponse { return $this->withCategories($this->web(StoreCategoryController::class, 'store')); }
    public function updateCategory(StoreCategory $storeCategory): JsonResponse { return $this->withCategories($this->web(StoreCategoryController::class, 'update', ['storeCategory' => $storeCategory])); }
    /** ids[] in the new order. */
    public function reorderCategories(): JsonResponse { return $this->withCategories($this->web(StoreCategoryController::class, 'reorder')); }
    public function toggleCategory(StoreCategory $storeCategory): JsonResponse { return $this->withCategories($this->web(StoreCategoryController::class, 'toggle', ['storeCategory' => $storeCategory])); }
    /** confirm=1 to delete a category that still has products or subcategories. */
    public function deleteCategory(StoreCategory $storeCategory): JsonResponse { return $this->withCategories($this->web(StoreCategoryController::class, 'destroy', ['storeCategory' => $storeCategory])); }

    private function withCategories(mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->storeCategories()->getData(true));
        }
        return $json;
    }
}
