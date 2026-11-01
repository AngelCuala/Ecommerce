<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin → People → Sellers → View shows that seller's products, by state. */
class AdminSellerProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_a_sellers_products_with_filters(): void
    {
        $make = fn ($role, $email, $name) => User::create(['name' => $name, 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now()]);
        $admin  = $make('admin', 'admin@alvy.test', 'Admin');
        $seller = $make('seller', 'seller@alvy.test', 'Maria Seller');
        $other  = $make('seller', 'other@alvy.test', 'Other Seller');
        $cat = Category::create(['name' => 'Home & Garden']);

        $p = fn ($owner, $title, $extra = []) => Product::create($extra + ['category_id' => $cat->id, 'seller_id' => $owner->id,
            'title' => $title, 'price' => 500, 'stock' => 10, 'status' => 'active']);
        $p($seller, 'Live Lamp', ['sale_price' => 399]);
        $p($seller, 'Waiting Vase', ['approval_status' => 'pending']);
        $p($seller, 'Refused Rug', ['approval_status' => 'rejected', 'approval_note' => 'Blurry photo']);
        $p($seller, 'Draft Pot', ['status' => 'draft']);
        $p($seller, 'Old Chair', ['is_archived' => true]);
        $p($other, 'Someone Elses Mug');

        $page = fn ($q = []) => $this->actingAs($admin)->get(route('admin.sellers.show', ['id' => $seller->id] + $q))->assertOk();

        $page()->assertSee('Products')
            ->assertSee('Live Lamp')->assertSee('₱399.00')->assertSee('Waiting Vase')->assertSee('Refused Rug')
            ->assertSee('Blurry photo')->assertSee('Draft Pot')->assertSee('Old Chair')
            ->assertDontSee('Someone Elses Mug')
            ->assertSeeInOrder(['Pending approval', '(1)', 'Rejected', '(1)']);

        $page(['products' => 'live'])->assertSee('Live Lamp')->assertDontSee('Waiting Vase');
        $page(['products' => 'pending'])->assertSee('Waiting Vase')->assertDontSee('Live Lamp');
        $page(['products' => 'archived'])->assertSee('Old Chair')->assertDontSee('Draft Pot');
        $page(['product_search' => 'Rug'])->assertSee('Refused Rug')->assertDontSee('Live Lamp');
    }
}
