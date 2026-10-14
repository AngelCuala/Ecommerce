<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ALVY (platform) vouchers work on any seller's products and are paid by ALVY;
 * store vouchers work only on their seller's products and are paid by that seller.
 */
class VoucherTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $furniture;   // seller A
    private User $books;       // seller B
    private User $buyer;
    private Product $table;    // ₱2,000, seller A
    private Product $novel;    // ₱500, seller B

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin     = $this->user('admin', 'admin@alvy.test');
        $this->furniture = $this->user('seller', 'furniture@alvy.test');
        $this->books     = $this->user('seller', 'books@alvy.test');
        $this->buyer     = $this->user('buyer', 'buyer@alvy.test');

        $cat = Category::create(['name' => 'Home & Garden']);
        $this->table = Product::create(['category_id' => $cat->id, 'seller_id' => $this->furniture->id, 'title' => 'Dining Table', 'price' => 2000, 'stock' => 10, 'status' => 'active']);
        $this->novel = Product::create(['category_id' => $cat->id, 'seller_id' => $this->books->id, 'title' => 'Novel', 'price' => 500, 'stock' => 10, 'status' => 'active']);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
    }

    private function voucher(array $data): Voucher
    {
        return Voucher::create($data + [
            'name' => 'Promo', 'discount_type' => 'fixed', 'min_spend' => 0, 'per_user_limit' => 1, 'is_active' => true,
        ]);
    }

    /** ₱150 off a ₱2,000 spend in the furniture store. */
    private function storeVoucher(array $data = []): Voucher
    {
        return $this->voucher($data + ['code' => 'TABLE150', 'scope' => 'seller', 'seller_id' => $this->furniture->id,
            'discount_value' => 150, 'min_spend' => 2000]);
    }

    /** 11.11: 10% off anything, up to ₱200. */
    private function saleVoucher(array $data = []): Voucher
    {
        return $this->voucher($data + ['code' => 'SALE1111', 'scope' => 'platform',
            'discount_type' => 'percent', 'discount_value' => 10, 'max_discount' => 200]);
    }

    private function fillCart(): void
    {
        CartItem::create(['user_id' => $this->buyer->id, 'product_id' => $this->table->id, 'quantity' => 1]);
        CartItem::create(['user_id' => $this->buyer->id, 'product_id' => $this->novel->id, 'quantity' => 1]);
    }

    private function placeOrder(array $vouchers = [])
    {
        return $this->actingAs($this->buyer)->from(route('checkout.index'))->post(route('checkout.store'), $vouchers + [
            'full_name' => 'Buyer', 'phone' => '09170000000', 'email' => 'buyer@alvy.test', 'region' => 'NCR',
            'province' => 'Metro Manila', 'city' => 'Quezon City', 'barangay' => 'Commonwealth',
            'zip_code' => '1121', 'payment_method' => 'cod',
        ]);
    }

    // ── Making vouchers ────────────────────────────────────────

    public function test_admin_makes_alvy_vouchers_and_seller_makes_store_vouchers(): void
    {
        $form = ['name' => '11.11 Sale', 'discount_type' => 'percent', 'discount_value' => 10, 'max_discount' => 200,
                 'per_user_limit' => 1, 'is_active' => '1'];

        $this->actingAs($this->admin)->post(route('admin.vouchers.store'), $form + ['code' => 'sale1111'])
            ->assertRedirect(route('admin.vouchers.index'));
        $this->actingAs($this->furniture)->post(route('seller.vouchers.store'),
            ['code' => 'TABLE150', 'name' => 'Table deal', 'discount_type' => 'fixed', 'discount_value' => 150,
             'min_spend' => 2000, 'per_user_limit' => 1, 'is_active' => '1'])
            ->assertRedirect(route('seller.vouchers.index'));

        $this->assertDatabaseHas('vouchers', ['code' => 'SALE1111', 'scope' => 'platform', 'seller_id' => null]);
        $this->assertDatabaseHas('vouchers', ['code' => 'TABLE150', 'scope' => 'seller', 'seller_id' => $this->furniture->id]);

        // Bad input: duplicate code, more than 100%.
        $this->actingAs($this->admin)->post(route('admin.vouchers.store'), $form + ['code' => 'TABLE150'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post(route('admin.vouchers.store'), ['discount_value' => 150, 'code' => 'BIG'] + $form)
            ->assertSessionHasErrors('discount_value');

        $this->actingAs($this->admin)->get(route('admin.vouchers.index'))->assertOk()->assertSee('SALE1111');
        $this->actingAs($this->admin)->get(route('admin.vouchers.index', ['scope' => 'seller']))->assertOk()->assertSee('TABLE150');
        $this->actingAs($this->furniture)->get(route('seller.vouchers.index'))->assertOk()->assertSee('TABLE150')->assertDontSee('SALE1111');
    }

    public function test_sellers_only_manage_their_own_store_vouchers(): void
    {
        $mine  = $this->storeVoucher();
        $alvy  = $this->saleVoucher();

        $this->actingAs($this->books)->get(route('seller.vouchers.edit', $mine))->assertNotFound();
        $this->actingAs($this->books)->patch(route('seller.vouchers.toggle', $mine))->assertNotFound();
        $this->actingAs($this->furniture)->get(route('seller.vouchers.edit', $alvy))->assertNotFound();
        $this->actingAs($this->furniture)->get(route('admin.vouchers.index'))->assertForbidden();

        // Admins can turn a store voucher off, but not edit it.
        $this->actingAs($this->admin)->patch(route('admin.vouchers.toggle', $mine))->assertSessionHas('success');
        $this->assertFalse($mine->fresh()->is_active);
        $this->actingAs($this->admin)->get(route('admin.vouchers.edit', $mine))->assertNotFound();
    }

    // ── Using vouchers at checkout ─────────────────────────────

    public function test_checkout_offers_the_right_vouchers(): void
    {
        $this->storeVoucher();
        $this->saleVoucher();
        $this->voucher(['code' => 'BOOKS50', 'scope' => 'seller', 'seller_id' => $this->books->id, 'discount_value' => 50, 'min_spend' => 1000]);
        $this->voucher(['code' => 'LATER', 'scope' => 'platform', 'discount_value' => 99, 'starts_at' => now()->addDay()]);
        $this->fillCart();

        $this->actingAs($this->buyer)->get(route('checkout.index'))->assertOk()
            ->assertSee('TABLE150')->assertSee('SALE1111')
            ->assertSee('BOOKS50')->assertSee('needs a minimum spend of ₱1,000.00', false)   // shown, but can't be picked
            ->assertDontSee('LATER');                                                     // not started yet
    }

    public function test_store_and_alvy_vouchers_on_one_order(): void
    {
        $this->storeVoucher();
        $this->saleVoucher();
        $this->fillCart();   // table ₱2,000 (furniture) + novel ₱500 (books)

        $this->placeOrder(['seller_vouchers' => [$this->furniture->id => 'TABLE150'], 'platform_voucher' => 'sale1111'])
            ->assertSessionHasNoErrors()->assertRedirect(route('checkout.confirmation'));

        $order = Order::firstOrFail();
        // Store voucher: ₱150 off the table. ALVY voucher: 10% of (2,500 - 150) = 235 → capped at ₱200.
        $this->assertEquals(150, $order->seller_discount);
        $this->assertEquals(200, $order->platform_discount);
        $this->assertEquals(2500 - 150 - 200 + 50, $order->total_price);

        $table = OrderItem::where('product_id', $this->table->id)->firstOrFail();
        $novel = OrderItem::where('product_id', $this->novel->id)->firstOrFail();

        // The furniture seller pays for the store voucher: commission and earnings are on ₱1,850.
        $this->assertEquals(150, $table->seller_discount);
        $this->assertEquals(185, $table->commission_amount);
        $this->assertEquals(1665, $table->seller_earning);
        // The books seller is untouched; ALVY pays the platform discount.
        $this->assertEquals(0, $novel->seller_discount);
        $this->assertEquals(450, $novel->seller_earning);
        $this->assertEquals(200, round($table->platform_discount + $novel->platform_discount, 2));

        $this->assertSame(1, Voucher::where('code', 'TABLE150')->value('used_count'));
        $this->assertSame(1, Voucher::where('code', 'SALE1111')->value('used_count'));
        $this->actingAs($this->buyer)->get(route('checkout.confirmation'))->assertOk();
    }

    public function test_store_voucher_only_works_for_its_own_store(): void
    {
        $this->storeVoucher();
        $this->fillCart();

        // Used under the other seller, or typed in as an ALVY voucher.
        $this->placeOrder(['seller_vouchers' => [$this->books->id => 'TABLE150']])->assertSessionHasErrors('seller_vouchers.' . $this->books->id);
        $this->placeOrder(['platform_voucher' => 'TABLE150'])->assertSessionHasErrors('platform_voucher');
        $this->assertSame(0, Order::count());
    }

    public function test_minimum_spend_counts_only_that_stores_items(): void
    {
        $this->storeVoucher(['min_spend' => 2100]);   // the cart has ₱2,500, but only ₱2,000 from this store
        $this->fillCart();

        $this->placeOrder(['seller_vouchers' => [$this->furniture->id => 'TABLE150']])
            ->assertSessionHasErrors(['seller_vouchers.' . $this->furniture->id => 'Voucher TABLE150: needs a minimum spend of ₱2,100.00 (add ₱100.00 more).']);
        $this->assertSame(0, Order::count());
        $this->assertSame(2, CartItem::count());   // nothing was lost
    }

    public function test_limits_dates_and_cancelling(): void
    {
        $this->saleVoucher(['usage_limit' => 5]);
        $this->voucher(['code' => 'OLD', 'scope' => 'platform', 'discount_value' => 50, 'ends_at' => now()->subDay()]);
        $this->voucher(['code' => 'OFF', 'scope' => 'platform', 'discount_value' => 50, 'is_active' => false]);

        $this->fillCart();
        $this->placeOrder(['platform_voucher' => 'OLD'])->assertSessionHasErrors(['platform_voucher' => 'Voucher OLD: this voucher has ended.']);
        $this->placeOrder(['platform_voucher' => 'OFF'])->assertSessionHasErrors('platform_voucher');

        $this->placeOrder(['platform_voucher' => 'SALE1111'])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();

        // Once per buyer.
        $this->fillCart();
        $this->placeOrder(['platform_voucher' => 'SALE1111'])
            ->assertSessionHasErrors(['platform_voucher' => 'Voucher SALE1111: you have already used this voucher.']);

        // Cancelling the order gives the voucher back.
        $this->actingAs($this->buyer)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind']);
        $this->assertSame('Cancelled', $order->fresh()->status);
        $this->assertSame(0, Voucher::where('code', 'SALE1111')->value('used_count'));
        $this->placeOrder(['platform_voucher' => 'SALE1111'])->assertSessionHasNoErrors();
    }

    public function test_used_up_vouchers_and_used_vouchers_cannot_be_deleted(): void
    {
        $v = $this->saleVoucher(['usage_limit' => 1]);
        $this->fillCart();
        $this->placeOrder(['platform_voucher' => 'SALE1111'])->assertSessionHasNoErrors();

        $this->assertSame('Used up', $v->fresh()->stateLabel());
        $this->actingAs($this->admin)->delete(route('admin.vouchers.destroy', $v))->assertSessionHas('error');
        $this->assertDatabaseHas('vouchers', ['id' => $v->id]);

        // The code and discount are locked once used; other details can change.
        $this->actingAs($this->admin)->put(route('admin.vouchers.update', $v), [
            'code' => 'SALE1111', 'name' => 'Renamed', 'discount_type' => 'percent', 'discount_value' => 10,
            'per_user_limit' => 1, 'usage_limit' => 50, 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $v->fresh()->name);
        $this->actingAs($this->admin)->put(route('admin.vouchers.update', $v), [
            'code' => 'SALE1111', 'name' => 'Renamed', 'discount_type' => 'percent', 'discount_value' => 50,
            'per_user_limit' => 1, 'is_active' => '1',
        ])->assertSessionHasErrors('discount_value');
    }
}
