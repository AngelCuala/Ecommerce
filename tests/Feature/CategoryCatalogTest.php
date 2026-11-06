<?php

namespace Tests\Feature;

use App\Http\Controllers\CategoryPageController;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** The shop's categories are A to Z, and one an admin adds shows once it has products. */
class CategoryCatalogTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    public function test_the_categories_are_alphabetical(): void
    {
        $names = array_column(CategoryPageController::catalog(), 'name');
        $sorted = $names;
        usort($sorted, 'strcasecmp');
        $this->assertSame($sorted, $names);
        $this->assertSame('Automotive & Motorcycle', $names[0]);
    }

    public function test_a_category_the_admin_adds_shows_in_the_shop_once_it_has_products(): void
    {
        $this->seedSellerTestData();
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('x'), 'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.categories.index'), false);   // in the menu
        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Art & Crafts'])->assertSessionHas('success');
        $this->assertNotContains('Art & Crafts', array_column(CategoryPageController::catalog(), 'name'));   // no products yet

        $this->p02->update(['category_id' => Category::where('name', 'Art & Crafts')->value('id')]);
        $catalog = CategoryPageController::catalog();
        $this->assertContains('Art & Crafts', array_column($catalog, 'name'));
        $this->assertSame('art-and-crafts', collect($catalog)->firstWhere('name', 'Art & Crafts')['slug']);
        $this->assertSame(asset('images/logo-mark.png'), CategoryPageController::photo('art-and-crafts'));   // no photo of its own yet

        auth()->logout();   // the shop, as a shopper sees it
        $this->get(route('categories.show', 'art-and-crafts'))->assertOk()->assertSee($this->p02->title);
    }
}
