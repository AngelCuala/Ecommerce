<?php

namespace Tests\Feature\Seller;

use App\Models\CartItem;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Seller workbook S7 (Store Vouchers) and S8 (Flash Sales, Campaigns & Bundles). */
class S07VouchersFlashAndBundlesTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    private function voucherForm(array $over = []): array
    {
        return array_merge([
            'code' => 'MCH50', 'name' => 'Maria Clara ₱50 off', 'discount_type' => 'fixed', 'discount_value' => '50',
            'min_spend' => '300', 'usage_limit' => '100', 'per_user_limit' => '1',
            'starts_at' => now()->startOfMonth()->format('Y-m-d\TH:i'), 'ends_at' => now()->endOfMonth()->format('Y-m-d\TH:i'),
            'is_active' => '1', 'is_claimable' => '1',
        ], $over);
    }

    private function createVoucher(array $over = [], $seller = null)
    {
        return $this->actingAs($seller ?? $this->seller1)->from(route('seller.vouchers.create'))->post(route('seller.vouchers.store'), $this->voucherForm($over));
    }

    /** What a store voucher takes off these cart lines (lenient checkout preview). */
    private function storeDiscount(array $lines, string $code, $seller = null): array
    {
        $q = app(VoucherService::class)->quote(collect($lines)->map->fresh(), $this->buyer1, null, [($seller ?? $this->seller1)->id => $code], null, [], false);
        return [$q['seller_discount'], $q['notices']];
    }

    private function sellerProduct(string $title, float $price): Product
    {
        return $this->product($this->seller1, $this->homeLiving, $title, $price, 50, 0.5);
    }

    // ── S7 Store Vouchers ───────────────────────────────────────

    /** SEL-VOU-001 */
    public function test_sel_vou_001_fixed_amount_claimable_voucher(): void
    {
        Voucher::where('code', 'MCH50')->delete();
        $this->createVoucher()->assertSessionHasNoErrors()->assertSessionHas('success', 'Store voucher MCH50 created.');

        $v = Voucher::where('code', 'MCH50')->sole();
        $this->assertSame([Voucher::SCOPE_SELLER, 50.0, 300.0, 100, 1, true], [$v->scope, $v->discount_value, $v->min_spend, $v->usage_limit, $v->per_user_limit, $v->is_claimable]);
        $this->assertNull($v->product_ids);

        $html = $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()->assertSee('₱50 off')->assertSee('Min spend ₱300')->getContent();
        $this->assertStringContainsString(route('vouchers.claim', $v), $html);
    }

    /** SEL-VOU-002 */
    public function test_sel_vou_002_percentage_voucher_with_a_cap(): void
    {
        Voucher::where('code', 'MCH10')->delete();
        $this->createVoucher(['code' => 'MCH10', 'discount_type' => 'percent', 'discount_value' => '10', 'max_discount' => '100', 'min_spend' => '500', 'usage_limit' => '50'])
            ->assertSessionHasNoErrors();

        [$off] = $this->storeDiscount([$this->cart($this->buyer1, $this->sellerProduct('Six hundred', 600))], 'MCH10');
        $this->assertEquals(60, $off);
        CartItem::query()->delete();
        [$off] = $this->storeDiscount([$this->cart($this->buyer1, $this->sellerProduct('Fifteen hundred', 1500))], 'MCH10');
        $this->assertEquals(100, $off);
    }

    /** SEL-VOU-003 */
    public function test_sel_vou_003_code_only_voucher_is_hidden(): void
    {
        Voucher::where('code', 'RVSECRET')->delete();
        $this->createVoucher(['code' => 'RVSECRET', 'discount_value' => '100', 'min_spend' => '1000', 'usage_limit' => '20', 'is_claimable' => '0'], $this->seller2)
            ->assertSessionHasNoErrors();
        $this->flushSession();

        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller2->id))->assertOk()->assertDontSee('RVSECRET')->assertDontSee('₱100 off');

        $line = $this->cart($this->buyer1, $this->p06, 1, 'Black');
        $this->openCheckout($this->buyer1, [$line])->assertOk()->assertDontSee('RVSECRET');
        $res = $this->actingAs($this->buyer1)->postJson(route('checkout.preview'), ['address_id' => $this->addr1->id, 'voucher_code' => 'rvsecret'])->assertOk()->json();
        $this->assertSame('RVSECRET', $res['applied']['sellers'][$this->seller2->id]);
        $this->assertEquals(100, $res['seller_discount']);
    }

    /** SEL-VOU-004 */
    public function test_sel_vou_004_duplicate_code_is_not_case_sensitive(): void
    {
        foreach (['MCH50', 'mch50'] as $code) {
            $this->createVoucher(['code' => $code])->assertSessionHasErrors(['code' => 'That code is already used by another voucher.']);
        }
        $this->assertSame(1, Voucher::where('code', 'MCH50')->count());
    }

    /** SEL-VOU-005 */
    public function test_sel_vou_005_discount_larger_than_minimum_spend(): void
    {
        $this->createVoucher(['code' => 'MCH500', 'discount_value' => '500', 'min_spend' => '100'])
            ->assertSessionHasErrors(['discount_value' => 'The discount (₱500.00) can\'t be more than the minimum spend (₱100.00).']);
        $this->assertFalse(Voucher::where('code', 'MCH500')->exists());
    }

    /** SEL-VOU-006 */
    public function test_sel_vou_006_field_validation(): void
    {
        $this->createVoucher(['code' => 'V1', 'discount_value' => '0'])->assertSessionHasErrors('discount_value');
        $this->createVoucher(['code' => 'VPCT0', 'discount_type' => 'percent', 'discount_value' => '0'])->assertSessionHasErrors('discount_value');
        $this->createVoucher(['code' => 'VPCT100', 'discount_type' => 'percent', 'discount_value' => '100'])
            ->assertSessionHasErrors(['discount_value' => 'A percent discount must be from 1% to 99%.']);
        $this->createVoucher(['code' => 'VUSE0', 'usage_limit' => '0'])->assertSessionHasErrors(['usage_limit' => 'The total uses must be at least 1 (leave it empty for unlimited).']);
        $this->createVoucher(['code' => 'VPER', 'usage_limit' => '5', 'per_user_limit' => '6'])
            ->assertSessionHasErrors(['per_user_limit' => 'Uses per buyer can\'t be more than the total uses allowed.']);
        $this->createVoucher(['code' => 'VDATE', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('ends_at');
        $this->assertSame(0, Voucher::whereIn('code', ['V1', 'VPCT0', 'VPCT100', 'VUSE0', 'VPER', 'VDATE'])->count());
    }

    /** SEL-VOU-007 */
    public function test_sel_vou_007_voucher_for_selected_products_only(): void
    {
        $this->createVoucher(['code' => 'MCHTOTE', 'discount_value' => '30', 'min_spend' => '100', 'product_ids' => [$this->p01->id]])->assertSessionHasNoErrors();
        $this->assertSame([$this->p01->id], Voucher::where('code', 'MCHTOTE')->value('product_ids'));
        // Another seller's product can't be chosen.
        $this->createVoucher(['code' => 'MCHBAD', 'product_ids' => [$this->p05->id]])->assertSessionHasErrors(['product_ids.0' => 'Choose only your own products.']);

        [$off, $notices] = $this->storeDiscount([$this->cart($this->buyer1, $this->p02)], 'MCHTOTE');
        $this->assertEquals(0, $off);
        $this->assertStringContainsString('only for selected products', $notices["seller_vouchers.{$this->seller1->id}"]);

        CartItem::query()->delete();
        [$off] = $this->storeDiscount([$this->cart($this->buyer1, $this->p01, 1, 'Natural / S')], 'MCHTOTE');
        $this->assertEquals(30, $off);
    }

    /** SEL-VOU-008 */
    public function test_sel_vou_008_edit_or_delete_a_claimed_voucher(): void
    {
        $v = Voucher::where('code', 'MCH50')->sole();
        VoucherClaim::create(['user_id' => $this->buyer1->id, 'voucher_id' => $v->id]);
        $edit = $this->voucherForm(['name' => $v->name, 'starts_at' => '', 'ends_at' => $v->ends_at->format('Y-m-d\TH:i')]);

        // A27: after a claim only the end date (later) and the usage limit (higher) can change.
        $this->actingAs($this->seller1)->put(route('seller.vouchers.update', $v), ['min_spend' => '500'] + $edit)
            ->assertSessionHasErrors(['min_spend' => 'The minimum spend can\'t be changed after buyers claimed this voucher. You can only extend the end date or raise the usage limit.']);
        $this->assertEquals(300, $v->fresh()->min_spend);
        $this->actingAs($this->seller1)->put(route('seller.vouchers.update', $v), ['usage_limit' => '150', 'ends_at' => $v->ends_at->copy()->addMonth()->format('Y-m-d\TH:i')] + $edit)
            ->assertSessionHasNoErrors();
        $this->assertSame(150, $v->fresh()->usage_limit);

        $this->actingAs($this->seller1)->delete(route('seller.vouchers.destroy', $v))
            ->assertSessionHas('success', 'Buyers already claimed MCH50, so it was ended instead of deleted. It no longer works at checkout.');
        $this->assertNotNull($v->fresh());
        $this->assertFalse($v->fresh()->is_active);

        // Ending it removes it from the wallets of buyers who never used it.
        $this->flushSession();
        $this->assertSame(0, VoucherClaim::where('voucher_id', $v->id)->count());
        $this->actingAs($this->buyer1)->get(route('vouchers.wallet'))->assertOk()->assertDontSee('MCH50');
        $line = $this->cart($this->buyer1, $this->p02);
        $this->openCheckout($this->buyer1, [$line])->assertOk();
        $res = $this->actingAs($this->buyer1)->postJson(route('checkout.preview'), ['address_id' => $this->addr1->id, 'seller_vouchers' => [$this->seller1->id => 'MCH50']])->assertOk()->json();
        $this->assertEquals(0, $res['seller_discount']);
    }

    /** SEL-VOU-009 */
    public function test_sel_vou_009_usage_counter(): void
    {
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)], null, ['seller_vouchers' => [$this->seller1->id => 'MCH50']]);
        $this->assertSame(1, Voucher::where('code', 'MCH50')->value('used_count'));
        $this->actingAs($this->seller1)->get(route('seller.vouchers.index'))->assertSee('1 / 100');
    }

    // ── S8 Flash Sales, Campaigns & Bundles ─────────────────────

    private function flash(int $qty = 3, array $extra = []): FlashSale
    {
        return FlashSale::create($extra + ['product_id' => $this->p02->id, 'seller_id' => $this->seller1->id, 'flash_price' => 299,
            'quantity' => $qty, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2)]);
    }

    /** SEL-FLASH-001 (A5) */
    public function test_sel_flash_001_create_a_flash_sale_in_a_time_slot(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $slot = ['product_id' => $this->p02->id, 'flash_price' => 299, 'quantity' => 3,
            'starts_at' => now()->setTime(12, 0)->format('Y-m-d H:i'), 'ends_at' => now()->setTime(14, 0)->format('Y-m-d H:i')];
        $this->actingAs($this->seller1)->post(route('seller.flash-sales.store'), $slot)->assertSessionHas('success');
        $this->actingAs($this->seller1)->get(route('seller.flash-sales.index'))->assertSee('Upcoming');

        $this->travelTo(now()->setTime(12, 30));
        Product::forgetFlashSales();
        $html = $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee('FLASH SALE')->assertSee('₱299.00')->getContent();
        $this->assertStringContainsString('id="pd-countdown" data-ends="' . now()->setTime(14, 0)->toIso8601String() . '"', $html);
    }

    /** SEL-FLASH-002 */
    public function test_sel_flash_002_flash_quantity_cannot_exceed_stock(): void
    {
        $slot = fn ($day, $qty) => ['product_id' => $this->p02->id, 'flash_price' => 299, 'quantity' => $qty,
            'starts_at' => now()->addDays($day)->format('Y-m-d H:i'), 'ends_at' => now()->addDays($day)->addHour()->format('Y-m-d H:i')];
        $this->actingAs($this->seller1)->post(route('seller.flash-sales.store'), $slot(1, 21))->assertSessionHasErrors(['quantity' => 'Only 20 in stock.']);
        $this->actingAs($this->seller1)->post(route('seller.flash-sales.store'), $slot(2, 20))->assertSessionHasNoErrors();
        $this->assertSame(1, FlashSale::count());
    }

    /** SEL-FLASH-003 (A5) */
    public function test_sel_flash_003_price_returns_when_flash_quantity_runs_out(): void
    {
        $this->flash(3);
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 3)])->first();
        $this->assertEquals(299, OrderItem::where('order_id', $order->id)->sole()->price);

        Product::forgetFlashSales();
        $this->flushSession();
        $this->actingAs($this->buyer2)->get(route('products.show', $this->p02->slug))->assertDontSee('FLASH SALE')->assertSee('₱450.00');
    }

    /** SEL-FLASH-004 (A5) */
    public function test_sel_flash_004_more_than_the_remaining_flash_quantity(): void
    {
        $sale = $this->flash(3);
        $sale->forceFill(['sold' => 2])->save();
        Product::forgetFlashSales();

        $line = $this->cart($this->buyer1, $this->p02, 2);
        $this->assertEquals(749, $line->fresh()->subtotal());   // 1 × ₱299 + 1 × ₱450
        $this->openCheckout($this->buyer1, [$line])->assertOk()->assertSee('₱299.00 × 1 (flash) + ₱450.00 × 1');

        $order = $this->placeOrder($this->buyer1, [$line])->first();
        $items = OrderItem::where('order_id', $order->id)->orderBy('price')->get();
        $this->assertSame([[1, 299.0], [1, 450.0]], $items->map(fn ($i) => [$i->quantity, (float) $i->price])->all());
        $this->assertSame($sale->id, $items[0]->flash_sale_id);
        $this->assertNull($items[1]->flash_sale_id);
        $this->assertSame(3, $sale->fresh()->sold);
        $this->assertEquals(749 + 65, $order->total_price);   // + shipping within NCR (0.8 kg × 2 = 1.6 → 2 kg)
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('299.00')->assertSee('450.00');
    }

    /** SEL-FLASH-005 */
    public function test_sel_flash_005_countdown_and_slot_end(): void
    {
        $sale = $this->flash(3, ['ends_at' => now()->addMinutes(10)]);
        Product::forgetFlashSales();
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee('id="pd-countdown"', false);
        // At 0 the page reloads itself so the normal price shows.
        $this->assertStringContainsString('window.location.reload()', file_get_contents(public_path('js/product-page.js')));

        $this->travelTo($sale->ends_at->copy()->addSecond());
        Product::forgetFlashSales();
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertOk()->assertDontSee('FLASH SALE')->assertSee('₱450.00');
    }

    /** SEL-FLASH-006 (A5) */
    public function test_sel_flash_006_store_sale_and_flash_sale_together(): void
    {
        $this->p02->update(['discount_percent' => 20]);   // store sale ₱360
        $this->flash(3);
        Product::forgetFlashSales();

        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee('₱299.00');
        $line = $this->cart($this->buyer1, $this->p02);
        $this->assertEquals(299, $line->fresh()->unit_price);
        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertSee('₱299.00');
        $this->openCheckout($this->buyer1, [$line])->assertOk()->assertSee('₱299.00');
    }

    /** SEL-FLASH-007 */
    public function test_sel_flash_007_bundle_deal_counting(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.promotions.store'), ['name' => 'Tote trio', 'type' => 'bundle',
            'product_ids' => [$this->p01->id], 'min_qty' => 3, 'discount_percent' => 15])->assertSessionHasNoErrors();
        $this->actingAs($this->seller1)->get(route('seller.promotions.index'))->assertSee('Tote trio')->assertSee('Buy 3, get 15% off');

        $lines = [$this->cart($this->buyer1, $this->p01, 1, 'Natural / S'), $this->cart($this->buyer1, $this->p01, 1, 'Black / L')];
        $q = app(VoucherService::class)->quote(collect($lines), $this->buyer1, null, [], null, [], false);
        $this->assertEquals(0, $q['promo_discount']);

        $lines[] = $this->cart($this->buyer1, $this->p01, 1, 'Natural / M');   // 199 + 299 + 249 = 747
        $q = app(VoucherService::class)->quote(collect($lines)->map->fresh(), $this->buyer1, null, [], null, [], false);
        $this->assertEquals(112.05, $q['promo_discount']);   // 15% of ₱747

        $order = $this->placeOrder($this->buyer1, $lines)->first();
        $this->assertEquals(112.05, $order->promo_discount);
        $this->assertEquals(round(747 - 112.05 + 65, 2), (float) $order->total_price);   // 1.2 kg → 2 kg within NCR
        // Seller-funded: commission (5%) is on what was charged.
        $this->assertEquals(round((747 - 112.05) * 0.95, 2), round(OrderItem::where('order_id', $order->id)->sum('seller_earning'), 2));
    }

    /** SEL-FLASH-008 */
    public function test_sel_flash_008_buy_x_get_y(): void
    {
        $this->p04->update(['stock' => 10]);
        $this->actingAs($this->seller1)->post(route('seller.promotions.store'), ['name' => 'Basket + placemat', 'type' => 'bxgy',
            'product_ids' => [$this->p02->id], 'min_qty' => 2, 'free_product_id' => $this->p04->id, 'free_qty' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Buy 2, get 1 free', Promotion::sole()->label());

        $basket = $this->cart($this->buyer1, $this->p02, 2);
        $mat    = $this->cart($this->buyer1, $this->p04, 1);
        $q = app(VoucherService::class)->quote(collect([$basket, $mat]), $this->buyer1, null, [], null, [], false);
        $this->assertEquals(350, $q['promo_discount']);   // the placemat is free
        $this->assertEquals(900, $q['subtotal']);

        $basket->update(['quantity' => 1]);
        $q = app(VoucherService::class)->quote(collect([$basket->fresh(), $mat->fresh()]), $this->buyer1, null, [], null, [], false);
        $this->assertEquals(0, $q['promo_discount']);   // back to its normal price
    }
}
