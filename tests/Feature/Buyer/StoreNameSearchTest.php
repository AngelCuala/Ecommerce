<?php

namespace Tests\Feature\Buyer;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Searching a store's name finds the store and its products. */
class StoreNameSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $sarap;
    private User $hidden;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = Category::create(['name' => 'Food & Gourmet']);
        $this->sarap  = $this->seller('sarap@alvy.test', 'Sarap Laguna', 'approved');
        $this->hidden = $this->seller('misha@alvy.test', 'Sarap Pending', 'pending');   // not approved: never shown

        Product::create(['category_id' => $cat->id, 'seller_id' => $this->sarap->id, 'title' => 'Classic Buko Pie', 'price' => 345, 'stock' => 36, 'status' => 'active']);
        Product::create(['category_id' => $cat->id, 'seller_id' => $this->hidden->id, 'title' => 'Hidden Espasol', 'price' => 130, 'stock' => 5, 'status' => 'active']);
    }

    private function seller(string $email, string $shop, string $status): User
    {
        $u = User::create(['name' => $shop, 'email' => $email, 'password' => Hash::make('Secret@123'), 'role' => 'seller',
            'approval_status' => 'approved', 'email_verified_at' => now()]);
        SellerApplication::create(['user_id' => $u->id, 'full_name' => $shop, 'shop_name' => $shop, 'status' => $status, 'phone' => '09170000000',
            'address' => 'Santa Cruz', 'municipality' => 'Santa Cruz', 'province' => 'Laguna', 'government_id_path' => 'ids/x.jpg']);
        return $u;
    }

    public function test_searching_the_store_name_shows_the_store_and_its_products(): void
    {
        foreach (['Sarap Laguna', 'sarap laguna', 'laguna sarap', 'Sarap'] as $term) {
            $this->get(route('shop.index', ['search' => $term]))->assertOk()
                ->assertSee('data-store-results', false)
                ->assertSee('Sarap Laguna')
                ->assertSee(route('stores.show', $this->sarap->id), false)
                ->assertSee('Classic Buko Pie')                          // the store's products come up too
                ->assertDontSee('Sarap Pending')->assertDontSee('Hidden Espasol');
        }
    }

    public function test_other_searches_are_unchanged(): void
    {
        $this->get(route('shop.index', ['search' => 'buko']))->assertOk()
            ->assertSee('Classic Buko Pie')->assertDontSee('data-store-results', false);
        $this->get(route('shop.index', ['search' => 'nothing like this']))->assertOk()
            ->assertSee('No results')->assertDontSee('data-store-results', false);
        $this->get(route('shop.index'))->assertOk()->assertDontSee('data-store-results', false);
    }
}
