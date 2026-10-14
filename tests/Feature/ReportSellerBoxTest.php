<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Sales Reports shows a box with every seller and a "View sales" link to that seller's report. */
class ReportSellerBoxTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, string $name): User
    {
        return User::create([
            'name' => $name, 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
    }

    private function sale(User $seller, string $date, float $amount, string $status = 'Processing'): void
    {
        $cat = Category::firstOrCreate(['name' => 'Home']);
        $product = Product::create(['category_id' => $cat->id, 'seller_id' => $seller->id, 'title' => 'Item ' . uniqid(),
            'price' => $amount, 'stock' => 5, 'status' => 'active']);
        $order = Order::create(['user_id' => $seller->id, 'total_price' => $amount, 'status' => $status, 'payment_method' => 'COD',
            'payment_status' => 'Pending', 'full_name' => 'B', 'phone' => '0917', 'email' => 'b@x.test',
            'address_line' => 'X', 'city' => 'QC', 'zip_code' => '1121', 'shipping_address' => 'X']);
        $order->forceFill(['created_at' => $date . ' 10:00:00'])->save();
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $amount,
            'commission_amount' => $amount * 0.1, 'seller_earning' => $amount * 0.9]);
    }

    public function test_sales_report_lists_sellers_and_links_to_their_sales(): void
    {
        $admin = $this->user('admin', 'admin@alvy.test', 'Admin');
        $maria = $this->user('seller', 'maria@alvy.test', 'Maria Seller');
        $juan  = $this->user('seller', 'juan@alvy.test', 'Juan Seller');
        $this->sale($maria, '2026-03-05', 1200);
        $this->sale($maria, '2026-03-06', 800);
        $this->sale($maria, '2026-03-07', 999, 'Cancelled');   // not counted
        $this->sale($juan, '2026-05-01', 300);                  // outside the dates

        $link = route('admin.reports.index', ['type' => 'sellers', 'seller' => $maria->id, 'from' => '2026-03-01', 'to' => '2026-03-31']);

        foreach (['sales', 'commission'] as $type) {
            $this->actingAs($admin)
                ->get(route('admin.reports.index', ['type' => $type, 'from' => '2026-03-01', 'to' => '2026-03-31']))
                ->assertOk()
                ->assertSee('View sales')
                ->assertSee('Maria Seller')->assertSee('Juan Seller')
                ->assertSee('₱2,000.00')                // Maria: 1,200 + 800
                ->assertSee($link);                     // escaped like the page's href
        }

        // The link opens that seller's report with the same numbers.
        $this->actingAs($admin)->get($link)->assertOk()->assertSee('Products sold')->assertSee('₱2,000.00');

        // The "Sales by Seller" report already is the seller list, so no extra box there.
        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['type' => 'sellers', 'from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()->assertDontSee('id="seller-box"', false);
    }
}
