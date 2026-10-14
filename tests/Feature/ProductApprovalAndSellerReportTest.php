<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * - A seller's new product waits for an admin before it shows in the shop; edits to an
 *   approved product don't.
 * - Admins see each seller's sales for a chosen date range.
 */
class ProductApprovalAndSellerReportTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\ChecksOut;

    private User $seller;
    private User $admin;
    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->seller = $this->user('seller', 'seller@alvy.test', 'Maria Seller');
        $this->admin  = $this->user('admin', 'admin@alvy.test', 'Admin');
        $this->cat    = Category::create(['name' => 'Pet Supplies']);
    }

    private function user(string $role, string $email, string $name): User
    {
        return User::create([
            'name' => $name, 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
    }

    private function product(array $data = []): array
    {
        return array_merge([
            'title' => 'Premium Dog Food', 'category_id' => $this->cat->id,
            'price' => 250, 'stock' => 10, 'status' => 'active', 'action' => 'publish',
            'language' => 'English', 'format' => 'Standard',
        ], $data);
    }

    private function createAsSeller(array $data = []): Product
    {
        $this->actingAs($this->seller)->post(route('seller.books.store'), $this->product($data))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'submitted for admin approval'));
        auth()->logout();

        return Product::latest('id')->firstOrFail();
    }

    // ── Product approval ───────────────────────────────────────

    public function test_new_seller_product_is_hidden_until_an_admin_approves_it(): void
    {
        $product = $this->createAsSeller();
        $this->assertSame('pending', $product->approval_status);

        $buyer = $this->user('buyer', 'buyer@alvy.test', 'Buyer');
        $this->get('/shop?search=Dog')->assertDontSee('Premium Dog Food');
        $this->get(route('products.show', $product->slug))->assertNotFound();
        $this->actingAs($buyer)->post(route('cart.store', $product->id))->assertSessionHas('error');
        $this->assertSame(0, CartItem::count());

        // Admins review it (with its details) on the approvals page.
        $this->actingAs($this->admin)->get(route('admin.product-approvals.index'))->assertOk()
            ->assertSee('Premium Dog Food')->assertSee('Maria Seller')->assertSee('₱250.00');

        $this->actingAs($this->admin)->patch(route('admin.product-approvals.approve', $product))->assertSessionHas('success');
        $this->assertTrue($product->fresh()->isPublished());
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->seller->id, 'title' => 'Product approved']);
        auth()->logout();

        $this->get('/shop?search=Dog')->assertSee('Premium Dog Food');
        $this->get(route('products.show', $product->slug))->assertOk();
    }

    public function test_editing_an_approved_product_does_not_need_approval_again(): void
    {
        $product = $this->createAsSeller();
        $this->actingAs($this->admin)->patch(route('admin.product-approvals.approve', $product));

        $this->actingAs($this->seller)->put(route('seller.books.update', $product), $this->product(['price' => 199]))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('approved', $product->approval_status);
        $this->assertEquals(199, $product->price);
    }

    public function test_rejected_product_needs_a_reason_and_goes_back_for_review_when_fixed(): void
    {
        $product = $this->createAsSeller();

        $this->actingAs($this->admin)->patch(route('admin.product-approvals.reject', $product))
            ->assertSessionHasErrors('approval_note');
        $this->actingAs($this->admin)->patch(route('admin.product-approvals.reject', $product), ['approval_note' => 'Photo is blurry'])
            ->assertSessionHas('success');

        $product->refresh();
        $this->assertTrue($product->isRejected());
        $this->assertSame('Photo is blurry', $product->approval_note);
        $this->actingAs($this->seller)->get(route('seller.books.index', ['status' => 'review']))
            ->assertSee('Rejected')->assertSee('Photo is blurry');

        $this->actingAs($this->seller)->put(route('seller.books.update', $product), $this->product(['title' => 'Premium Dog Food 2kg']));
        $product->refresh();
        $this->assertTrue($product->isPendingApproval());
        $this->assertNull($product->approval_note);
    }

    public function test_drafts_are_not_sent_for_approval_and_admin_only_pages(): void
    {
        $this->actingAs($this->seller)->post(route('seller.books.store'), $this->product(['action' => 'draft', 'status' => 'draft']));
        $this->actingAs($this->admin)->get(route('admin.product-approvals.index'))->assertOk()
            ->assertSee('No products are waiting for approval.');

        $this->actingAs($this->seller)->get(route('admin.product-approvals.index'))->assertForbidden();
    }

    public function test_checkout_refuses_items_that_are_not_approved(): void
    {
        $product = $this->createAsSeller();
        $buyer   = $this->user('buyer', 'buyer@alvy.test', 'Buyer');
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($buyer)->post(route('checkout.store'), $this->checkoutForm($this->addressFor($buyer)))
            ->assertRedirect(route('cart.index'))->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_page_itself_warns_about_unapproved_items(): void
    {
        $product = $this->createAsSeller();
        $buyer   = $this->user('buyer', 'buyer@alvy.test', 'Buyer');
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($buyer)->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Premium Dog Food'));
    }

    // ── Seller sales report ────────────────────────────────────

    private function sale(User $seller, string $date, int $qty, float $price, string $status = 'Processing'): void
    {
        $product = Product::create([
            'category_id' => $this->cat->id, 'seller_id' => $seller->id, 'title' => 'Item ' . uniqid(),
            'price' => $price, 'stock' => 50, 'status' => 'active',
        ]);
        $order = Order::create([
            'user_id' => $this->admin->id, 'total_price' => $qty * $price, 'status' => $status,
            'payment_method' => 'COD', 'payment_status' => 'Pending', 'full_name' => 'Buyer', 'phone' => '0917',
            'email' => 'b@x.test', 'address_line' => 'Somewhere', 'city' => 'QC', 'zip_code' => '1121',
            'shipping_address' => 'Somewhere',
        ]);
        $order->forceFill(['created_at' => $date . ' 10:00:00'])->save();
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'quantity' => $qty, 'price' => $price,
            'commission_amount' => $qty * $price * 0.1, 'seller_earning' => $qty * $price * 0.9,
        ]);
    }

    public function test_admin_sees_each_sellers_sales_for_the_chosen_dates(): void
    {
        $other = $this->user('seller', 'other@alvy.test', 'Juan Seller');
        $this->sale($this->seller, '2026-01-05', 2, 100);            // in range
        $this->sale($this->seller, '2026-01-20', 1, 300);            // in range
        $this->sale($this->seller, '2026-01-25', 5, 100, 'Cancelled'); // cancelled: not a sale
        $this->sale($this->seller, '2026-03-01', 9, 100);            // outside range
        $this->sale($other, '2026-02-01', 1, 50);                   // in range (last day)

        $page = $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['type' => 'sellers', 'from' => '2026-01-01', 'to' => '2026-02-01']))
            ->assertOk()
            ->assertSee('Maria Seller')->assertSee('Juan Seller')
            ->assertSee('₱500.00')     // Maria: 2×100 + 1×300
            ->assertSee('₱50.00')      // Juan
            ->assertSee('₱550.00');    // total
        $page->assertDontSee('₱1,400.00');

        // Reversed dates are read as the same range.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['type' => 'sellers', 'from' => '2026-02-01', 'to' => '2026-01-01']))
            ->assertSee('₱550.00');

        // One seller's detail and CSV.
        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['type' => 'sellers', 'from' => '2026-01-01', 'to' => '2026-02-01', 'seller' => $this->seller->id]))
            ->assertOk()->assertSee('Products sold')->assertSee('₱500.00')->assertDontSee('Juan Seller');

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['type' => 'sellers', 'from' => '2026-01-01', 'to' => '2026-02-01', 'export' => 'csv']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Maria Seller', $csv);
        $this->assertStringContainsString('2026-01-01 to 2026-02-01', $csv);
    }

    public function test_sales_summary_and_commission_leave_out_cancelled_orders(): void
    {
        $this->sale($this->seller, '2026-01-05', 2, 100);               // ₱200, counted
        $this->sale($this->seller, '2026-01-06', 5, 1000, 'Cancelled'); // ₱5,000, not counted

        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['type' => 'sales', 'from' => '2026-01-01', 'to' => '2026-01-31']))
            ->assertOk()->assertSee('₱200.00')->assertDontSee('₱5,200.00');

        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['type' => 'commission', 'from' => '2026-01-01', 'to' => '2026-01-31']))
            ->assertOk()->assertSee('₱20.00')->assertDontSee('₱520.00');   // 10% of ₱200 only
    }

    public function test_every_report_downloads_as_a_pdf(): void
    {
        $this->sale($this->seller, '2026-01-05', 2, 100);

        foreach ([['type' => 'sales'], ['type' => 'commission'], ['type' => 'sellers'], ['type' => 'sellers', 'seller' => $this->seller->id]] as $q) {
            $res = $this->actingAs($this->admin)
                ->get(route('admin.reports.index', $q + ['from' => '2026-01-01', 'to' => '2026-02-01', 'export' => 'pdf']))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $res->getContent());
            $this->assertStringContainsString('2026-01-01-to-2026-02-01.pdf', $res->headers->get('Content-Disposition'));
        }

        // Only admins.
        $this->actingAs($this->seller)
            ->get(route('admin.reports.index', ['type' => 'sales', 'export' => 'pdf']))
            ->assertForbidden();
    }

    public function test_suspended_sellers_and_alvy_listings_still_count_in_the_totals(): void
    {
        $this->sale($this->seller, '2026-01-05', 2, 100);   // ₱200
        $this->seller->update(['role' => 'suspended']);

        $alvyItem = Product::create(['category_id' => $this->cat->id, 'title' => 'ALVY Mug', 'price' => 75, 'stock' => 5, 'status' => 'active']);
        $order = Order::create([
            'user_id' => $this->admin->id, 'total_price' => 75, 'status' => 'Processing', 'payment_method' => 'COD',
            'payment_status' => 'Pending', 'full_name' => 'B', 'phone' => '0917', 'email' => 'b@x.test',
            'address_line' => 'X', 'city' => 'QC', 'zip_code' => '1121', 'shipping_address' => 'X',
        ]);
        $order->forceFill(['created_at' => '2026-01-06 10:00:00'])->save();
        OrderItem::create(['order_id' => $order->id, 'product_id' => $alvyItem->id, 'quantity' => 1, 'price' => 75,
            'commission_amount' => 0, 'seller_earning' => 75]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['type' => 'sellers', 'from' => '2026-01-01', 'to' => '2026-01-31']))
            ->assertOk()
            ->assertSee('Maria Seller')->assertSee('ALVY (no seller)')
            ->assertSee('₱275.00');   // 200 + 75
    }
}
