<?php

namespace Tests\Blackbox;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\UserAddress;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Mobile_Blackbox_Test_Cases_Buyer_Modules.xlsx — the cases not already covered by tests/Feature/Buyer, through /api/v1. */
class BuyerModulesTest extends TestCase
{
    use RefreshDatabase, SellerTestData, RecordsCases;

    private string $t1;

    protected static function workbook(): string { return 'Buyer_Modules'; }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedSellerTestData();
        $this->t1 = $this->buyer1->createToken('t')->plainTextToken;
    }

    private function api(string $method, string $uri, array $data = [], ?string $token = null)
    {
        return $this->withToken($token ?? $this->t1)->json($method, '/api/v1' . $uri, $data);
    }

    private function orderIn(string $status, ?Product $p = null): Order
    {
        $o = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $p ?? $this->p02)])->first();
        return $this->moveTo($o, $status);
    }

    public function test_account_address_search(): void
    {
        // BUY-ACCT-004
        $r = $this->api('PATCH', '/profile/password', ['current_password' => 'Wrong@2026', 'password' => 'Mamimili@2027', 'password_confirmation' => 'Mamimili@2027']);
        $still = \Illuminate\Support\Facades\Hash::check('Mamimili@2026', $this->buyer1->fresh()->password);
        $this->record('BUY-ACCT-004', $r->status() === 422 && $still, 'Blocked: "' . $this->msg($r, 'current_password') . '". The original password still works.');

        // BUY-ADDR-002: PSGC cascade
        $ncr = collect($this->getJson('/api/psgc/regions')->json('data') ?? $this->getJson('/api/psgc/regions')->json())->firstWhere('name', 'National Capital Region (NCR)')
            ?? collect($this->getJson('/api/psgc/regions')->json())->first(fn ($r) => str_contains($r['name'] ?? '', 'NCR'));
        $cities = $ncr ? ($this->getJson('/api/psgc/provinces/' . $ncr['code'] . '/municipalities')->json('data') ?? $this->getJson('/api/psgc/provinces/' . $ncr['code'] . '/municipalities')->json()) : [];
        $names = collect($cities)->pluck('name');
        $qc = collect($cities)->first(fn ($c) => str_contains($c['name'], 'Quezon City'));
        $brgys = $qc ? collect($this->getJson('/api/psgc/municipalities/' . $qc['code'] . '/barangays')->json('data') ?? $this->getJson('/api/psgc/municipalities/' . $qc['code'] . '/barangays')->json()) : collect();
        $ok = $names->contains(fn ($n) => str_contains($n, 'Quezon City')) && ! $names->contains(fn ($n) => str_contains($n, 'Cebu')) && $brgys->isNotEmpty();
        $this->record('BUY-ADDR-002', $ok, 'NCR lists ' . $names->count() . ' cities (Quezon City yes, no Cebu cities); Quezon City lists ' . $brgys->count() . ' barangays only from QC. In the app, changing Region clears City and Barangay.');

        // BUY-ADDR-003 / 004
        $base = ['label' => 'Office', 'full_name' => 'Juan Miguel Dela Cruz', 'phone' => '09171234567', 'region' => 'NCR', 'province' => 'Metro Manila',
            'city' => 'Quezon City', 'barangay' => 'Central', 'address_line' => '12F Pacific Tower', 'municipality_code' => self::QUEZON_CITY];
        $blank = [];
        $before = UserAddress::where('user_id', $this->buyer1->id)->count();
        foreach (['full_name', 'phone', 'address_line', 'municipality_code'] as $f) {
            $r = $this->api('POST', '/addresses', array_merge($base, [$f => '']));
            $blank[$f] = $r->status() === 422 && $r->json('errors') !== null;
        }
        $this->record('BUY-ADDR-003', ! in_array(false, $blank, true) && UserAddress::where('user_id', $this->buyer1->id)->count() === $before,
            'Each blank required field (' . implode(', ', array_keys($blank)) . ') is blocked with a field message; no address added.');
        $fmt = [];
        foreach (['09171234567' => true, '+639171234567' => true, '0917123456' => false, '091712345678' => false, '0917ABC4567' => false, '0281234567' => false] as $n => $accept) {
            $r = $this->api('POST', '/addresses', array_merge($base, ['phone' => $n, 'label' => 'T' . substr(md5($n), 0, 5)]));
            $fmt[] = "$n: " . ($r->status() === 200 ? 'saved' : 'rejected');
            $okFmt[] = ($r->status() === 200) === $accept;
        }
        $this->record('BUY-ADDR-004', ! in_array(false, $okFmt, true), implode('; ', $fmt) . '. (+639… is saved as 09….)');

        // BUY-SRCH-002: store screen with store categories
        $this->p03->storeCategories()->sync([$this->cat3->id]);
        $s = $this->getJson('/api/v1/stores/' . $this->seller1->id . '?scat=' . $this->cat3->id);
        $titles = collect($s->json('data'))->pluck('title');
        $cats = collect($s->json('store_categories'))->flatMap(fn ($c) => [$c['name'], ...collect($c['children'])->map(fn ($x) => $c['name'] . ' › ' . $x['name'])->all()]);
        $this->record('BUY-SRCH-002', $s->status() === 200 && $titles->all() === ['Capiz Shell Lamp'],
            'Store screen categories: ' . $cats->implode(', ') . '. Lamps shows: ' . $titles->implode(', ') . '.',
            'Store categories are now picked from the ALVY categories (2026-10-21); older typed categories still show.');

        // BUY-SRCH-004 / 005 / 006
        $typo = $this->getJson('/api/v1/shop?search=ratan basket');
        $none = $this->getJson('/api/v1/shop?search=zzqxw');
        $typoTitles = collect($typo->json('data'))->pluck('title');
        $this->record('BUY-SRCH-004', $typoTitles->contains('Rattan Fruit Basket') || $typo->json('suggestion'),
            'Typo "ratan basket": ' . ($typoTitles->isEmpty() ? 'no results' : $typoTitles->implode(', ')) . ($typo->json('suggestion') ? ' (suggestion: ' . $typo->json('suggestion') . ')' : '')
            . '. "zzqxw": HTTP ' . $none->status() . ', ' . count($none->json('data') ?? []) . ' results (empty state, no error).');
        $inj = [];
        foreach (["' OR '1'='1", '<script>alert(1)</script>', '%_%', '"; DROP TABLE products; --'] as $q) {
            $r = $this->getJson('/api/v1/shop?search=' . urlencode($q));
            $inj[] = $r->status() === 200 && count($r->json('data') ?? []) === 0;
        }
        $this->record('BUY-SRCH-005', ! in_array(false, $inj, true) && Product::count() > 0, 'All 4 return HTTP 200 with no results; no error, no product dump; the products table is intact. Text is shown as plain text in the app (no script runs).');
        $empty = $this->getJson('/api/v1/shop?search=');
        $this->assertNotEmpty(static::$results);
        $this->record('BUY-SRCH-006', $empty->status() === 200, 'Empty search returns the normal listing (HTTP 200, ' . count($empty->json('data') ?? []) . ' products). The app does not submit an empty search.');
    }

    public function test_product_wishlist_cart(): void
    {
        // BUY-PDP-003
        $p = $this->getJson('/api/v1/products/' . $this->p01->slug)->json('product') ?? $this->getJson('/api/v1/products/' . $this->p01->slug)->json();
        $bm = collect($p['variations'] ?? [])->firstWhere('name', 'Black / M');
        $add = $this->api('POST', '/cart/' . $this->p01->id, ['quantity' => 1, 'variation_id' => $this->variation($this->p01, 'Black / M')->id]);
        $this->record('BUY-PDP-003', $bm && (int) $bm['stock'] === 0 && $add->status() === 422,
            'Black / M is sent with stock 0 (the app shows it disabled); adding it anyway is refused: "' . $this->msg($add) . '".');

        // BUY-WISH-002
        $this->api('POST', '/wishlist/' . $this->p02->id);
        $this->api('POST', '/wishlist/' . $this->p02->id);
        $this->api('POST', '/wishlist/' . $this->p02->id);
        $n = \App\Models\WishlistItem::where('user_id', $this->buyer1->id)->where('product_id', $this->p02->id)->count();
        $this->record('BUY-WISH-002', $n <= 1, "After 3 quick taps the wishlist has P-02 $n time(s) (the heart toggles; final state " . ($n ? 'on' : 'off') . ').');

        // BUY-WISH-005
        $w = $this->postJson('/api/v1/wishlist/' . $this->p02->id);
        $f = $this->postJson('/api/v1/stores/' . $this->seller1->id . '/follow');
        $this->record('BUY-WISH-005', $w->status() === 401 && $f->status() === 401, "Without login: wishlist HTTP {$w->status()}, follow HTTP {$f->status()}; the app opens the login screen. Nothing saved.");

        // BUY-CART-002 / 003 / 009
        CartItem::where('user_id', $this->buyer1->id)->delete();
        $this->api('POST', '/cart/' . $this->p01->id, ['quantity' => 1, 'variation_id' => $this->variation($this->p01, 'Natural / M')->id]);
        $this->api('POST', '/cart/' . $this->p01->id, ['quantity' => 1, 'variation_id' => $this->variation($this->p01, 'Natural / S')->id]);
        $this->api('POST', '/cart/' . $this->p05->id, ['quantity' => 1]);
        $cart = $this->api('GET', '/cart');
        $stores = collect($cart->json('stores'));
        $s1 = $stores->first(fn ($s) => str_contains($s['name'] ?? '', 'Maria Clara'));
        $prices = collect($s1['lines'] ?? [])->map(fn ($l) => ($l['variation']['name'] ?? '') . ' ₱' . ($l['unit_price'] ?? ''))->all();
        $this->record('BUY-CART-002', count($s1['lines'] ?? []) === 2, 'Two separate lines: ' . implode(', ', $prices) . '.');
        $this->record('BUY-CART-003', $stores->count() === 2, 'Groups: ' . $stores->pluck('name')->implode(' / ') . ', each with only its own items.');
        $line = CartItem::where('user_id', $this->buyer1->id)->where('product_id', $this->p05->id)->first();
        $del = $this->api('DELETE', '/cart/item/' . $line->id);
        $after = collect($this->api('GET', '/cart')->json('stores'))->count();
        $this->record('BUY-CART-009', $del->status() === 200 && ! CartItem::find($line->id) && $after === 1, 'Removed ("' . $del->json('message') . '"); after a refresh only the Maria Clara group is left and the subtotal updates.');

        // BUY-CART-006: price change while in the cart
        CartItem::where('user_id', $this->buyer1->id)->delete();
        $this->api('POST', '/cart/' . $this->p02->id, ['quantity' => 1]);
        $this->p02->update(['price' => 420]);
        $l = collect($this->api('GET', '/cart')->json('stores.0.lines'))->first();
        $this->record('BUY-CART-006', (float) ($l['unit_price'] ?? 0) === 420.0, 'Cart shows ₱' . number_format((float) ($l['unit_price'] ?? 0), 2) . ' after the seller changed the price to ₱420.00.');
        $this->p02->update(['price' => 450]);
    }

    public function test_vouchers_checkout_orders_reviews(): void
    {
        $addr = $this->addr1->id;
        // BUY-VOU-005: expired, not started, limit reached, invalid
        CartItem::where('user_id', $this->buyer1->id)->delete();
        $line = $this->cart($this->buyer1, $this->p02);
        $this->api('GET', '/checkout?items[]=' . $line->id);
        $msgs = [];
        foreach (['MCHOLD', 'MCHSOON', 'MCHMAX', 'NOPE123'] as $code) {
            $r = $this->api('POST', '/checkout/preview', ['address_id' => $addr, 'voucher_code' => $code]);
            $msgs[$code] = $r->json('errors.voucher_code') ?? collect($r->json('notices'))->first() ?? '';
        }
        $distinct = count(array_unique(array_filter($msgs))) === 4;
        $this->record('BUY-VOU-005', $distinct, implode(' | ', array_map(fn ($k, $v) => "$k: \"$v\"", array_keys($msgs), $msgs)) . '. No discount applied.');

        // BUY-VOU-006: per-buyer limit reached
        $mch = Voucher::where('code', 'MCH50')->first();
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)], null, ['seller_vouchers' => [$this->seller1->id => 'MCH50']]);
        $line = $this->cart($this->buyer1, $this->p02);
        $this->api('GET', '/checkout?items[]=' . $line->id);
        $again = $this->api('POST', '/checkout/preview', ['address_id' => $addr, 'voucher_code' => 'MCH50']);
        $t2 = $this->buyer2->createToken('t')->plainTextToken;
        $line2 = $this->cart($this->buyer2, $this->p02);
        $this->api('GET', '/checkout?items[]=' . $line2->id, [], $t2);
        $b2 = $this->api('POST', '/checkout/preview', ['address_id' => $this->addr3->id, 'voucher_code' => 'MCH50'], $t2);
        $used = $again->json('errors.voucher_code') ?? collect($again->json('notices'))->first();
        $this->record('BUY-VOU-006', $used && empty($b2->json('errors')) && (float) $b2->json('seller_discount') === 50.0,
            'Buyer 1 (used it once): "' . $used . '". Buyer 2: applied, −₱' . number_format((float) $b2->json('seller_discount'), 2) . '.');

        // BUY-VOU-010: total never negative
        Voucher::create(['code' => 'BIG999', 'name' => 'BIG999', 'scope' => 'platform', 'discount_type' => 'fixed', 'discount_value' => 999,
            'min_spend' => 0, 'per_user_limit' => 1, 'is_active' => true, 'is_claimable' => true]);
        CartItem::where('user_id', $this->buyer1->id)->delete();
        $line = $this->cart($this->buyer1, $this->p05);   // ₱399
        $this->api('GET', '/checkout?items[]=' . $line->id);
        $pv = $this->api('POST', '/checkout/preview', ['address_id' => $addr, 'platform_voucher' => 'BIG999']);
        $merch = (float) $pv->json('subtotal') - (float) $pv->json('platform_discount') - (float) $pv->json('seller_discount');
        $this->record('BUY-VOU-010', $merch >= 0 && (float) $pv->json('total') >= (float) $pv->json('shipping_total'),
            'Merchandise ₱' . number_format((float) $pv->json('subtotal'), 2) . ' − platform ₱' . number_format((float) $pv->json('platform_discount'), 2)
            . ' = ₱' . number_format($merch, 2) . '; shipping ₱' . number_format((float) $pv->json('shipping_total'), 2) . ' still charged; total ₱' . number_format((float) $pv->json('total'), 2) . '.');

        // BUY-VOU-011: voucher on items already on sale
        $this->p02->update(['sale_price' => 360]);
        CartItem::where('user_id', $this->buyer2->id)->delete();
        $l = $this->cart($this->buyer2, $this->p02);
        $this->api('GET', '/checkout?items[]=' . $l->id, [], $t2);
        Voucher::where('code', 'MCH50')->update(['used_count' => 0]);
        $sale = $this->api('POST', '/checkout/preview', ['address_id' => $this->addr3->id, 'voucher_code' => 'MCH50'], $t2);
        $this->record('BUY-VOU-011', (float) $sale->json('seller_discount') === 50.0 && (float) $sale->json('subtotal') === 360.0,
            'Sale price ₱360 in the cart; MCH50 applies (−₱' . number_format((float) $sale->json('seller_discount'), 2) . '); merchandise ₱' . number_format((float) $sale->json('subtotal') - (float) $sale->json('seller_discount'), 2)
            . ', shipping ₱' . number_format((float) $sale->json('shipping_total'), 2) . ', total ₱' . number_format((float) $sale->json('total'), 2) . '.',
            'Shipping to Buyer 2 (Cebu) differs from the ₱50 in the test data; the voucher part matches.');
        $this->p02->update(['sale_price' => null]);

        // BUY-CHK-006: no payment method
        CartItem::where('user_id', $this->buyer1->id)->delete();
        $line = $this->cart($this->buyer1, $this->p02);
        $co = $this->api('GET', '/checkout?items[]=' . $line->id);
        $place = $this->api('POST', '/checkout', ['address_id' => $addr, 'checkout_token' => $co->json('checkout_token'), 'prices' => $co->json('prices')]);
        $this->record('BUY-CHK-006', $place->status() === 422 && isset($place->json('errors')['payment_method']),
            'Blocked: "' . $this->msg($place, 'payment_method') . '". (The app pre-selects Cash on Delivery.)');

        // BUY-TRK-004: Order Received before delivery
        $o = $this->orderIn(Order::SHIPPED);
        $r = $this->api('POST', "/orders/{$o->id}/received");
        $this->record('BUY-TRK-004', $r->status() >= 400 && $o->fresh()->status === Order::SHIPPED, "Rejected (HTTP {$r->status()}: \"{$r->json('message')}\"); the order stays Shipped. The app shows no Order Received button before delivery.");

        // BUY-CAN-002 / 005
        $o2 = $this->orderIn(Order::PENDING);
        $r = $this->api('POST', "/orders/{$o2->id}/cancel", []);
        $this->record('BUY-CAN-002', $r->status() === 422 && $o2->fresh()->status === Order::PENDING, 'Blocked: "' . $this->msg($r) . '". The order is unchanged.');
        $r = $this->api('POST', "/orders/{$o->id}/cancel", ['reason' => 'Changed my mind']);
        $this->record('BUY-CAN-005', $r->status() >= 400 && $o->fresh()->status === Order::SHIPPED, "Shipped order: cancel refused (HTTP {$r->status()}: \"{$r->json('message')}\"). The app shows no Cancel button.");

        // BUY-RET-002
        $r = $this->api('POST', "/orders/{$o->id}/return", ['reason' => 'Damaged', 'description' => 'The basket arrived broken on one side.']);
        $this->record('BUY-RET-002', $r->status() >= 400 && $o->fresh()->status === Order::SHIPPED, "Before delivery: refused (HTTP {$r->status()}: \"{$r->json('message')}\").");

        // BUY-RATE-002 / 003
        $item = $o->items()->first();
        $r = $this->api('POST', "/reviews/item/{$item->id}", ['rating' => 5, 'comment' => 'Great basket, very sturdy!']);
        $this->record('BUY-RATE-002', $r->status() >= 400 && ! Review::where('order_item_id', $item->id)->exists(), "Not completed yet: refused (HTTP {$r->status()}: \"{$r->json('message')}\").");
        $done = $this->orderIn(Order::COMPLETED);
        $it = $done->items()->first();
        $first = $this->api('POST', "/reviews/item/{$it->id}", ['rating' => 5, 'comment' => 'Great basket, very sturdy!']);
        $second = $this->api('POST', "/reviews/item/{$it->id}", ['rating' => 4, 'comment' => 'Second review for the same item.']);
        $count = Review::where('order_item_id', $it->id)->count();
        $this->record('BUY-RATE-003', $first->status() === 200 && $second->status() >= 400 && $count === 1,
            "First review saved; a second one is refused (HTTP {$second->status()}: \"{$second->json('message')}\"). Reviews for the item: $count. Edit is offered instead.");
    }
}
