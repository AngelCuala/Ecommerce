<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\StoreCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Seller workbook S1 (Store Profile & Settings) and S2 (Store Categories). */
class S01StoreProfileAndCategoriesTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    private function settingsForm(array $over = []): array
    {
        return array_merge([
            'shop_name' => 'Maria Clara Handicrafts PH', 'description' => 'Handwoven bags and home decor from Bulacan artisans.',
            'phone' => '09189876543', 'region' => 'NCR', 'province' => 'Metro Manila', 'municipality' => 'Quezon City',
            'barangay' => 'Batasan Hills', 'street' => '88 IBP Road', 'zip_code' => '1126', 'municipality_code' => self::QUEZON_CITY,
            'is_on_vacation' => '0',
        ], $over);
    }

    private function saveSettings(array $over = [])
    {
        return $this->actingAs($this->seller1)->from(route('seller.store-settings'))->patch(route('seller.store-settings.update'), $this->settingsForm($over));
    }

    // ── S1 Store Profile & Settings ─────────────────────────────

    /** SEL-PROF-001 */
    public function test_sel_prof_001_update_store_profile_with_valid_data(): void
    {
        $this->actingAs($this->seller1)->get(route('seller.store-settings'))->assertOk()->assertSee('Store Settings');

        $this->saveSettings(['logo' => UploadedFile::fake()->image('store_logo.png')->size(300), 'banner' => $this->jpg('store_banner.jpg', 1800)])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Store settings saved.');

        $app = $this->seller1->sellerApplication->fresh();
        $this->assertSame('Maria Clara Handicrafts PH', $app->shop_name);
        $this->assertNotNull($app->logo_path);
        $this->assertNotNull($app->banner_path);

        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()
            ->assertSee('Maria Clara Handicrafts PH')->assertSee('Handwoven bags and home decor from Bulacan artisans.')
            ->assertSee('data-store-logo', false)->assertSee('data-store-banner', false);

        // The new pickup address goes on the next waybill.
        $this->assertStringContainsString('88 IBP Road', $app->address);
        $this->assertStringContainsString('Batasan Hills', $app->address);
    }

    /** SEL-PROF-002 */
    public function test_sel_prof_002_required_fields_left_blank(): void
    {
        foreach (['shop_name' => 'Please enter your store name.', 'street' => 'Please enter the pickup street address.',
                  'municipality' => 'Please choose the pickup address city or municipality.', 'phone' => 'Please enter a contact number.'] as $field => $message) {
            $this->saveSettings([$field => ''])->assertSessionHasErrors([$field => $message]);
        }
        $this->assertSame('Maria Clara Handicrafts', $this->seller1->sellerApplication->fresh()->shop_name);
    }

    /** SEL-PROF-003 */
    public function test_sel_prof_003_store_name_already_used(): void
    {
        foreach (['RV Gadget Hub', 'rv gadget hub'] as $name) {
            $this->saveSettings(['shop_name' => $name])->assertSessionHasErrors(['shop_name' => 'This store name is already taken. Please choose another.']);
        }
    }

    /** SEL-PROF-004 */
    public function test_sel_prof_004_store_name_length_limits(): void
    {
        $this->saveSettings(['shop_name' => 'MC'])->assertSessionHasErrors(['shop_name' => 'Store name must be 3 to 30 characters.']);
        $this->saveSettings(['shop_name' => 'Maria Clara Handicrafts Bulacan'])->assertSessionHasErrors(['shop_name' => 'Store name must be 3 to 30 characters.']);
        $this->saveSettings(['shop_name' => 'MCH'])->assertSessionHasNoErrors();
        $this->saveSettings(['shop_name' => 'Maria Clara Handicrafts Bulaca'])->assertSessionHasNoErrors();
        $this->assertSame(30, mb_strlen($this->seller1->sellerApplication->fresh()->shop_name));
    }

    /** SEL-PROF-005 (A16) */
    public function test_sel_prof_005_logo_and_banner_type_and_size(): void
    {
        $this->saveSettings(['logo' => $this->jpg('store_logo.jpg', 300)])->assertSessionHasNoErrors();
        $kept = $this->seller1->sellerApplication->fresh()->logo_path;

        foreach (['logo', 'banner'] as $field) {
            $this->saveSettings([$field => $this->jpg('size_4.9MB.jpg', 5018)])->assertSessionHasNoErrors();
            $this->saveSettings([$field => $this->jpg('size_5.0MB.jpg', 5120)])->assertSessionHasNoErrors();
            $kept = $this->seller1->sellerApplication->fresh()->{$field . '_path'};

            $label = ucfirst($field);
            $this->saveSettings([$field => $this->jpg('size_5.1MB.jpg', 5222)])->assertSessionHasErrors([$field => "The {$field} must be 5 MB or smaller."]);
            $this->saveSettings([$field => UploadedFile::fake()->create('price_list.docx', 320, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
                ->assertSessionHasErrors($field);
            $this->saveSettings([$field => UploadedFile::fake()->create('renamed_exe.jpg', 2100, 'application/x-msdownload')])
                ->assertSessionHasErrors($field);
            // The previous image is kept.
            $this->assertSame($kept, $this->seller1->sellerApplication->fresh()->{$field . '_path'}, $label);
        }
    }

    /** SEL-PROF-006 (A18) */
    public function test_sel_prof_006_turn_on_vacation_mode(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->saveSettings(['is_on_vacation' => '1'])->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()->assertSee('On vacation')->assertSee('Rattan Fruit Basket');
        $page = $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertOk()->assertSee('On vacation');
        $this->assertMatchesRegularExpression('/value="cart" class="pd-btn pd-btn-line" disabled/', $page->getContent());
        $this->assertMatchesRegularExpression('/value="buy_now" class="pd-btn pd-btn-solid" disabled/', $page->getContent());

        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p02->id), ['quantity' => 1])->assertSessionHas('error', 'This store is on vacation and is not taking orders right now.');
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p02->id), ['quantity' => 1, 'action' => 'buy_now'])->assertSessionHas('error');
        $this->openCheckout($this->buyer1, [$line])->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'On vacation'));
    }

    /** SEL-PROF-007 (A18) */
    public function test_sel_prof_007_turn_off_vacation_mode(): void
    {
        $this->saveSettings(['is_on_vacation' => '1']);
        $this->saveSettings(['is_on_vacation' => '0'])->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertOk()->assertDontSee('On vacation');
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p02->id), ['quantity' => 1])->assertSessionHas('success');
        $orders = $this->placeOrder($this->buyer1, [\App\Models\CartItem::sole()]);
        $this->assertCount(1, $orders);
    }

    /** SEL-PROF-008 (A1, A18) */
    public function test_sel_prof_008_unapproved_seller_can_only_save_drafts(): void
    {
        $form = $this->productForm(['title' => 'Handwoven Coin Purse', 'price' => '120.00', 'stock' => '10']);

        $this->actingAs($this->seller3)->get(route('seller.books.create'))->assertOk()
            ->assertSee('Your store must be verified before you can publish');
        $this->actingAs($this->seller3)->from(route('seller.books.create'))->post(route('seller.books.store'), $form)
            ->assertSessionHasErrors(['publish' => 'Your store must be verified before you can publish products. You can save this product as a draft for now.']);
        $this->assertSame(0, Product::where('seller_id', $this->seller3->id)->count());

        $this->actingAs($this->seller3)->post(route('seller.books.store'), ['action' => 'draft'] + $form)->assertSessionHasNoErrors();
        $draft = Product::where('seller_id', $this->seller3->id)->sole();
        $this->assertSame('draft', $draft->status);

        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Coin Purse']))->assertDontSee('Handwoven Coin Purse');
        $this->actingAs($this->buyer1)->get(route('products.show', $draft->slug))->assertNotFound();
    }

    /** SEL-PROF-009 */
    public function test_sel_prof_009_description_with_html_or_script(): void
    {
        $this->saveSettings(['description' => "<b>Sale!</b><script>alert('x')</script>"])->assertSessionHasNoErrors();

        $html = $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()->getContent();
        $this->assertStringNotContainsString("<script>alert('x')</script>", $html);
        $this->assertStringContainsString('&lt;b&gt;Sale!&lt;/b&gt;&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', $html);
    }

    // ── S2 Store Categories ─────────────────────────────────────

    /** SEL-SCAT-001 */
    public function test_sel_scat_001_create_a_store_category(): void
    {
        $this->actingAs($this->seller1)->get(route('seller.store-categories.index'))->assertOk()->assertSee('Kitchen &amp; Dining', false);
        $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['name' => 'Pasalubong Picks'])->assertSessionHasNoErrors();
        $cat = StoreCategory::where('name', 'Pasalubong Picks')->sole();

        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $this->editForm($this->p01, ['store_category_ids' => [$cat->id]]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $cat->id]))->assertOk()
            ->assertSee('data-store-category="Pasalubong Picks"', false)->assertSee('Abaca Woven Tote Bag');
    }

    /** SEL-SCAT-002 */
    public function test_sel_scat_002_empty_whitespace_or_long_name(): void
    {
        $store = fn ($name) => $this->actingAs($this->seller1)->from(route('seller.store-categories.index'))->post(route('seller.store-categories.store'), ['name' => $name]);

        $store('')->assertSessionHasErrors(['name' => 'Please enter a category name.']);
        $store('   ')->assertSessionHasErrors(['name' => 'Please enter a category name.']);
        $store(str_repeat('a', 51))->assertSessionHasErrors(['name' => 'Category names can be up to 50 characters.']);
        $store(str_repeat('b', 200))->assertSessionHasErrors('name');
        $store(str_repeat('c', 50))->assertSessionHasNoErrors();
        $this->actingAs($this->seller1)->get(route('seller.store-categories.index'))->assertSee(str_repeat('c', 50));
    }

    /** SEL-SCAT-003 */
    public function test_sel_scat_003_duplicate_name_in_the_same_store(): void
    {
        foreach (['Bags', 'bags', ' Bags '] as $name) {
            $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['name' => $name])
                ->assertSessionHasErrors(['name' => 'A category named "' . trim($name) . '" already exists in your store.']);
        }
        $this->assertSame(1, StoreCategory::where('seller_id', $this->seller1->id)->whereRaw('LOWER(name) = ?', ['bags'])->count());
    }

    /** SEL-SCAT-004 */
    public function test_sel_scat_004_same_name_in_a_different_store(): void
    {
        $this->actingAs($this->seller2)->post(route('seller.store-categories.store'), ['name' => 'Bags'])->assertSessionHasNoErrors();
        $this->assertSame(2, StoreCategory::where('name', 'Bags')->count());
    }

    /** SEL-SCAT-005 */
    public function test_sel_scat_005_rename_and_reorder(): void
    {
        $this->actingAs($this->seller1)->put(route('seller.store-categories.update', $this->cat5), ['name' => 'Kitchen'])->assertSessionHasNoErrors();
        $this->actingAs($this->seller1)->post(route('seller.store-categories.reorder'), ['ids' => [$this->cat5->id, $this->cat1->id, $this->cat2->id]])->assertSessionHasNoErrors();
        $this->p04->update(['stock' => 5]);
        $this->p04->storeCategories()->attach($this->cat5);

        $html = $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'data-store-category="Bags"'), strpos($html, 'data-store-category="Kitchen"'));
        $this->assertTrue($this->p04->storeCategories()->whereKey($this->cat5->id)->exists());
    }

    /** SEL-SCAT-006 */
    public function test_sel_scat_006_hide_a_category(): void
    {
        $this->actingAs($this->seller1)->patch(route('seller.store-categories.toggle', $this->cat1))->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertDontSee('data-store-category="Bags"', false);
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Abaca Tote']))->assertSee('Abaca Woven Tote Bag');
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertOk();
    }

    /** SEL-SCAT-007 */
    public function test_sel_scat_007_show_a_hidden_category_again(): void
    {
        $this->cat1->update(['is_hidden' => true]);
        $this->actingAs($this->seller1)->patch(route('seller.store-categories.toggle', $this->cat1));
        $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $this->cat1->id]))
            ->assertSee('data-store-category="Bags"', false)->assertSee('Abaca Woven Tote Bag');
    }

    /** SEL-SCAT-008 (A19) */
    public function test_sel_scat_008_delete_a_category_that_has_products(): void
    {
        $this->actingAs($this->seller1)->delete(route('seller.store-categories.destroy', $this->cat4))
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'still has 1 product') && str_contains($m, 'Uncategorized'));
        $this->assertNotNull($this->cat4->fresh());

        $this->actingAs($this->seller1)->delete(route('seller.store-categories.destroy', $this->cat4), ['confirm' => 1])
            ->assertSessionHas('success', 'Category "Baskets" deleted. 1 product moved to Uncategorized.');
        $this->assertNull($this->cat4->fresh());
        $this->assertNotNull($this->p02->fresh());

        $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => 'none']))
            ->assertSee('data-store-category="Uncategorized"', false)->assertSee('Rattan Fruit Basket');
        $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertSee('Rattan Fruit Basket');
    }

    /** SEL-SCAT-009 (A7) */
    public function test_sel_scat_009_subcategory_depth_limit(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['name' => 'Wall Decor', 'parent_id' => $this->cat2->id])->assertSessionHasNoErrors();
        $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['name' => 'Table Lamps', 'parent_id' => $this->cat3->id])
            ->assertSessionHasErrors(['parent_id' => 'Store categories have at most 2 levels: a subcategory can\'t have its own subcategory.']);
        $this->assertFalse(StoreCategory::where('name', 'Table Lamps')->exists());

        // Level 3 isn't offered: only top-level categories can be chosen as the parent.
        $html = $this->actingAs($this->seller1)->get(route('seller.store-categories.index'))->getContent();
        $this->assertStringNotContainsString('<option value="' . $this->cat3->id . '"', $html);
    }

    /** SEL-SCAT-010 */
    public function test_sel_scat_010_one_product_in_several_categories(): void
    {
        $best = StoreCategory::create(['seller_id' => $this->seller1->id, 'name' => 'Best Sellers', 'sort_order' => 9]);
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p03), $this->editForm($this->p03, ['store_category_ids' => [$this->cat3->id, $best->id]]))
            ->assertSessionHasNoErrors();

        foreach ([$this->cat3, $best] as $cat) {
            $html = $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $cat->id]))->getContent();
            $this->assertSame(1, substr_count($html, 'alt="Capiz Shell Lamp"'));
        }
    }

    /** SEL-SCAT-011 */
    public function test_sel_scat_011_store_category_is_separate_from_platform_category(): void
    {
        $platform = $this->p01->category_id;
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $this->editForm($this->p01, ['store_category_ids' => [$this->cat5->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame($platform, $this->p01->fresh()->category_id);
        $this->assertSame([$this->cat5->id], $this->p01->storeCategories()->pluck('store_categories.id')->all());
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Tote', 'category' => 'Bags & Luggage']))->assertSee('Abaca Woven Tote Bag');
        $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $this->cat5->id]))->assertSee('Abaca Woven Tote Bag');
    }
}
