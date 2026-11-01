<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admins can look at sellers' products but can't add, edit or delete them (the compliance page warns instead). */
class AdminProductsViewOnlyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin  = $this->user('admin', 'admin@alvy.test');
        $this->seller = $this->user('seller', 'seller@alvy.test');
        $cat = Category::create(['name' => 'Shoes & Footwear']);
        $this->product = Product::create(['category_id' => $cat->id, 'seller_id' => $this->seller->id, 'title' => 'Leather Slides',
            'price' => 590, 'stock' => 39, 'status' => 'active']);
    }

    private function user(string $role, string $email): User
    {
        return User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    public function test_admin_product_pages_have_no_edit_or_delete(): void
    {
        // The admin Products page is gone (sidebar link too); products are seen on each seller's page.
        $this->actingAs($this->admin)->get('/admin/products')->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee(url('/admin/products') . '"', false);

        $this->actingAs($this->admin)->get(route('admin.sellers.show', $this->seller))->assertOk()
            ->assertSee('Leather Slides')->assertDontSee('/products/' . $this->product->id . '/edit', false);

        foreach (['index', 'create', 'edit', 'store', 'update', 'destroy'] as $name) {
            $this->assertFalse(\Route::has('admin.products.' . $name), "admin.products.$name should not exist");
        }
    }

    public function test_admin_cannot_change_or_delete_a_product_by_url(): void
    {
        $url = '/admin/products/' . $this->product->id;
        $this->actingAs($this->admin)->get($url . '/edit')->assertNotFound();
        foreach ([['put', $url], ['delete', $url], ['post', '/admin/products']] as [$verb, $to]) {
            $status = $this->actingAs($this->admin)->{$verb}($to, ['title' => 'Changed', 'price' => 1])->status();
            $this->assertContains($status, [404, 405], "$verb $to answered $status");
        }

        $this->assertSame('Leather Slides', $this->product->fresh()->title);
        $this->assertNull($this->product->fresh()->deleted_at);
    }

    public function test_compliance_warns_the_seller_but_never_removes_the_product(): void
    {
        $this->actingAs($this->admin)->post(route('admin.compliance.store'), [
            'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'type' => 'wrong_category',
            'action' => 'product_removed', 'note' => 'Wrong category.',
        ])->assertSessionHasErrors('action');
        $this->assertNotNull($this->product->fresh());

        $this->actingAs($this->admin)->post(route('admin.compliance.store'), [
            'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'type' => 'wrong_category',
            'action' => 'warning', 'note' => 'Wrong category.',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($this->product->fresh());
        $this->assertDatabaseHas('seller_violations', ['seller_id' => $this->seller->id, 'action' => 'warning']);

        $this->actingAs($this->admin)->get(route('admin.compliance.show', $this->seller->id))->assertOk()
            ->assertDontSee('<option value="product_removed">', false);
    }
}
