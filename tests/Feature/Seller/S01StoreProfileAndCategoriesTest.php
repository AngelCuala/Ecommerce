<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\StoreCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Seller workbook S1 (Store Profile & Settings, SEL-PROF-001…013) and S2 (Store Categories, SEL-SCAT-001…013). */
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

    /** SEL-PROF-001 (A16, A25) */
    public function test_sel_prof_001_update_store_profile_with_valid_data(): void
    {
        $this->actingAs($this->seller1)->get(route('seller.store-settings'))->assertOk()->assertSee('Store Settings');

        $this->saveSettings(['logo' => UploadedFile::fake()->image('store_logo.png')->size(300), 'banner' => $this->jpg('store_banner.jpg', 1800)])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Store settings saved.');

        $app = $this->seller1->sellerApplication->fresh();
        $this->assertSame('Maria Clara Handicrafts PH', $app->shop_name);
        $this->assertSame('09189876543', $app->phone);
        $this->assertNotNull($app->logo_path);
        $this->assertNotNull($app->banner_path);

        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()
            ->assertSee('Maria Clara Handicrafts PH')->assertSee('Handwoven bags and home decor from Bulacan artisans.')
            ->assertSee('data-store-logo', false)->assertSee('data-store-banner', false);
        // No other store changed.
        $this->assertSame('RV Gadget Hub', $this->seller2->sellerApplication->fresh()->shop_name);

        // The new pickup address goes on the next waybill.
        $this->assertStringContainsString('88 IBP Road', $app->address);
        $this->assertStringContainsString('Batasan Hills', $app->address);
    }

    /** SEL-PROF-002 (A25) */
    /** Regression: an approved seller with no application on file gets a message, not a 404. */
    public function test_store_settings_without_an_application_explains_instead_of_404(): void
    {
        $demo = $this->account('seller', 'demo.seller@example.com', 'Demo Seller');

        $this->actingAs($demo)->get(route('seller.store-settings'))->assertRedirect(route('seller.dashboard'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'no seller application on file'));
        $this->actingAs($demo)->patch(route('seller.store-settings.update'), ['store_name' => 'Demo Store'])->assertRedirect(route('seller.dashboard'));
    }

    public function test_sel_prof_002_each_required_field_left_blank(): void
    {
        foreach (['shop_name' => 'Please enter your store name.', 'phone' => 'Please enter a contact number.',
                  'region' => 'Please choose the pickup address region.', 'municipality' => 'Please choose the pickup address city or municipality.',
                  'barangay' => 'Please choose the pickup address barangay.', 'street' => 'Please enter the pickup street address.'] as $field => $message) {
            $this->saveSettings([$field => ''])->assertSessionHasErrors([$field => $message]);
        }
        $app = $this->seller1->sellerApplication->fresh();
        $this->assertSame('Maria Clara Handicrafts', $app->shop_name);
        $this->assertStringContainsString('12 Tandang Sora Ave.', $app->address);
    }

    /** SEL-PROF-003 (A25) */
    public function test_sel_prof_003_fields_containing_only_spaces(): void
    {
        $this->saveSettings(['shop_name' => '     '])->assertSessionHasErrors(['shop_name' => 'Please enter your store name.']);
        $this->assertSame('Maria Clara Handicrafts', $this->seller1->sellerApplication->fresh()->shop_name);
    }

    /** SEL-PROF-004 (A25) */
    public function test_sel_prof_004_store_name_already_used_by_another_store(): void
    {
        foreach (['RV Gadget Hub', 'rv gadget hub', '  RV Gadget Hub  '] as $name) {
            $this->saveSettings(['shop_name' => $name])->assertSessionHasErrors(['shop_name' => 'This store name is already taken. Please choose another.']);
        }
        $this->assertSame('Maria Clara Handicrafts', $this->seller1->sellerApplication->fresh()->shop_name);
    }

    /** SEL-PROF-005 (A25) */
    public function test_sel_prof_005_store_name_length_at_the_limits(): void
    {
        $this->saveSettings(['shop_name' => 'MC'])->assertSessionHasErrors(['shop_name' => 'Store name must be 3 to 30 characters.']);
        $this->saveSettings(['shop_name' => 'Maria Clara Handicrafts Bulacan'])->assertSessionHasErrors(['shop_name' => 'Store name must be 3 to 30 characters.']);
        $this->saveSettings(['shop_name' => 'MCH'])->assertSessionHasNoErrors();
        $this->assertSame('MCH', $this->seller1->sellerApplication->fresh()->shop_name);
        $this->saveSettings(['shop_name' => 'Maria Clara Handicrafts Bulaca'])->assertSessionHasNoErrors();
        // Saved exactly as typed, never cut.
        $this->assertSame('Maria Clara Handicrafts Bulaca', $this->seller1->sellerApplication->fresh()->shop_name);
    }

    /** SEL-PROF-006 (A25) */
    public function test_sel_prof_006_description_length_at_the_limit(): void
    {
        $this->saveSettings(['description' => str_repeat('a', 500)])->assertSessionHasNoErrors();
        $this->assertSame(500, mb_strlen($this->seller1->sellerApplication->fresh()->description));
        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertSee(str_repeat('a', 500));

        $this->saveSettings(['description' => str_repeat('b', 501)])->assertSessionHasErrors(['description' => 'The store description can be up to 500 characters.']);
        $this->assertSame(str_repeat('a', 500), $this->seller1->sellerApplication->fresh()->description);
    }

    /** SEL-PROF-007 */
    public function test_sel_prof_007_contact_number_format(): void
    {
        $message = 'Please enter a valid mobile number: 11 digits starting with 09 (e.g. 09171234567).';
        foreach (['0918987654', '091898765432', '0918-ABC-6543', '08189876543'] as $bad) {
            $this->saveSettings(['phone' => $bad])->assertSessionHasErrors(['phone' => $message]);
        }
        $this->saveSettings(['phone' => '+639189876543'])->assertSessionHasNoErrors();
        $this->assertSame('09189876543', $this->seller1->sellerApplication->fresh()->phone);
        $this->saveSettings(['phone' => '09189876543'])->assertSessionHasNoErrors();
    }

    /** SEL-PROF-008 (A16) */
    public function test_sel_prof_008_logo_and_banner_file_type_and_size(): void
    {
        foreach (['logo', 'banner'] as $field) {
            $this->saveSettings([$field => $this->jpg('size_4.9MB.jpg', 5018)])->assertSessionHasNoErrors();
            $this->saveSettings([$field => $this->jpg('size_5.0MB.jpg', 5120)])->assertSessionHasNoErrors();
            $kept = $this->seller1->sellerApplication->fresh()->{$field . '_path'};

            $this->saveSettings([$field => $this->jpg('size_5.1MB.jpg', 5222)])->assertSessionHasErrors([$field => "The {$field} must be 5 MB or smaller."]);
            $this->saveSettings([$field => UploadedFile::fake()->create('price_list.docx', 320, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
                ->assertSessionHasErrors([$field => "The {$field} must be a JPG or PNG image."]);
            $this->saveSettings([$field => UploadedFile::fake()->create('installer.exe', 2100, 'application/x-msdownload')])->assertSessionHasErrors($field);
            $this->saveSettings([$field => UploadedFile::fake()->create('renamed_exe.jpg', 2100, 'application/x-msdownload')])->assertSessionHasErrors($field);
            // The previous image is still shown.
            $this->assertSame($kept, $this->seller1->sellerApplication->fresh()->{$field . '_path'});
        }
    }

    /** SEL-PROF-009 (A18) */
    public function test_sel_prof_009_turn_on_vacation_mode(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->saveSettings(['is_on_vacation' => '1'])->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()->assertSee('On vacation')->assertSee('Rattan Fruit Basket');
        $this->actingAs($this->buyer2)->get(route('shop.index', ['search' => 'Rattan']))->assertSee('Rattan Fruit Basket');
        $page = $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertOk()->assertSee('On vacation');
        $this->assertMatchesRegularExpression('/value="cart" class="pd-btn pd-btn-line" disabled/', $page->getContent());
        $this->assertMatchesRegularExpression('/value="buy_now" class="pd-btn pd-btn-solid" disabled/', $page->getContent());

        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p02->id), ['quantity' => 1])->assertSessionHas('error', 'This store is on vacation and is not taking orders right now.');
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p02->id), ['quantity' => 1, 'action' => 'buy_now'])->assertSessionHas('error');
        $this->openCheckout($this->buyer1, [$line])->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'On vacation'));
        $this->assertSame(0, \App\Models\Order::count());
    }

    /** SEL-PROF-010 (A18) */
    public function test_sel_prof_010_turn_off_vacation_mode(): void
    {
        $this->saveSettings(['is_on_vacation' => '1']);
        $this->saveSettings(['is_on_vacation' => '0'])->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertOk()->assertDontSee('On vacation');
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p02->id), ['quantity' => 1])->assertSessionHas('success');
        $orders = $this->placeOrder($this->buyer1, [\App\Models\CartItem::sole()]);
        $this->assertCount(1, $orders);
    }

    /** SEL-PROF-011 (A1, A18) */
    public function test_sel_prof_011_seller_that_is_not_approved_tries_to_publish(): void
    {
        $form = $this->productForm(['title' => 'Handwoven Coin Purse', 'price' => '120.00', 'stock' => '10', 'weight_kg' => '0.1']);

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

    /** SEL-PROF-012 (A25) */
    public function test_sel_prof_012_script_and_html_in_text_fields(): void
    {
        $this->saveSettings(['description' => "<b>Sale!</b><script>alert('x')</script>"])->assertSessionHasNoErrors();
        $html = $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()->getContent();
        $this->assertStringNotContainsString("<script>alert('x')</script>", $html);
        $this->assertStringContainsString('&lt;b&gt;Sale!&lt;/b&gt;&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', $html);

        $this->saveSettings(['description' => '"><img src=x onerror=alert(1)>'])->assertSessionHasNoErrors();
        $html = $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()->getContent();
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    /** SEL-PROF-013 (A22) */
    public function test_sel_prof_013_seller_cannot_edit_another_stores_settings(): void
    {
        // The settings page always edits the signed-in seller's own store; a store ID in the request is ignored.
        $this->actingAs($this->seller1)->get(route('seller.store-settings', ['seller' => $this->seller2->id]))->assertOk()
            ->assertSee('Maria Clara Handicrafts')->assertDontSee('RV Gadget Hub');
        $this->saveSettings(['seller_id' => $this->seller2->id, 'user_id' => $this->seller2->id, 'id' => $this->seller2->sellerApplication->id,
            'shop_name' => 'Hijacked Store'])->assertSessionHasNoErrors();

        $this->assertSame('RV Gadget Hub', $this->seller2->sellerApplication->fresh()->shop_name);
        $this->assertSame('Hijacked Store', $this->seller1->sellerApplication->fresh()->shop_name);
        // Another role can't open the seller settings at all.
        $this->assertContains($this->actingAs($this->buyer1)->get(route('seller.store-settings'))->getStatusCode(), [302, 403]);
    }

    // ── S2 Store Categories ─────────────────────────────────────

    /** SEL-SCAT-001: the seller adds ALVY categories to the store; a product saved in one shows under it. */
    public function test_sel_scat_001_create_a_store_category_and_assign_a_product(): void
    {
        $beauty = \App\Models\Category::firstOrCreate(['name' => 'Health & Beauty']);
        $page = $this->actingAs($this->seller1)->get(route('seller.store-categories.index'))->assertOk()->assertSee('Add a category from ALVY');
        $page->assertSee('<option value="' . $beauty->id . '"', false);

        $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['category_id' => $beauty->id])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Category "Health & Beauty" added to your store. Pick it when you add a product.');
        $cat = StoreCategory::where('seller_id', $this->seller1->id)->where('category_id', $beauty->id)->sole();
        $this->assertSame('Health & Beauty', $cat->name);
        // Already added: not offered again.
        $this->actingAs($this->seller1)->get(route('seller.store-categories.index'))->assertDontSee('<option value="' . $beauty->id . '"', false);

        // The product's Category lists the store's categories first; saving it there tags it.
        $form = $this->actingAs($this->seller1)->get(route('seller.books.edit', $this->p01))->assertOk()->getContent();
        $this->assertLessThan(strpos($form, 'Other ALVY categories'), strpos($form, 'Your store categories'));
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $this->editForm($this->p01, ['category_id' => $beauty->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($beauty->id, $this->p01->fresh()->category_id);
        $this->assertTrue($this->p01->storeCategories()->whereKey($cat->id)->exists());

        $html = $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $cat->id]))->assertOk()
            ->assertSee('data-store-category="Health &amp; Beauty"', false)->assertSee('Abaca Woven Tote Bag')->getContent();
        $this->assertStringNotContainsString('alt="Rattan Fruit Basket"', $html);
    }

    /** SEL-SCAT-002: only an ALVY category can be added. */
    public function test_sel_scat_002_empty_spaces_only_and_over_length_names(): void
    {
        $store = fn (array $data) => $this->actingAs($this->seller1)->from(route('seller.store-categories.index'))->post(route('seller.store-categories.store'), $data);

        $store([])->assertSessionHasErrors(['category_id' => 'Please choose a category.']);
        $store(['category_id' => 999999])->assertSessionHasErrors(['category_id' => 'Please choose one of the ALVY categories.']);
        // A typed name is not a category any more.
        $store(['name' => 'Pasalubong Picks'])->assertSessionHasErrors(['category_id' => 'Please choose a category.']);
        $this->assertFalse(StoreCategory::where('name', 'Pasalubong Picks')->exists());
    }

    /** SEL-SCAT-003: the same ALVY category can't be added twice to a store. */
    public function test_sel_scat_003_duplicate_category_name_in_the_same_store(): void
    {
        $toys = \App\Models\Category::firstOrCreate(['name' => 'Toys & Games']);
        $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['category_id' => $toys->id])->assertSessionHasNoErrors();
        $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['category_id' => $toys->id])
            ->assertSessionHasErrors(['category_id' => 'That category is already in your store.']);
        $this->assertSame(1, StoreCategory::where('seller_id', $this->seller1->id)->where('category_id', $toys->id)->count());
    }

    /** SEL-SCAT-004: another store can add the same ALVY category. */
    public function test_sel_scat_004_same_category_name_in_a_different_store(): void
    {
        $toys = \App\Models\Category::firstOrCreate(['name' => 'Toys & Games']);
        $before = StoreCategory::where('seller_id', $this->seller1->id)->pluck('name', 'id')->all();
        $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['category_id' => $toys->id])->assertSessionHasNoErrors();
        $this->actingAs($this->seller2)->post(route('seller.store-categories.store'), ['category_id' => $toys->id])->assertSessionHasNoErrors();

        $this->assertSame(2, StoreCategory::where('category_id', $toys->id)->count());
        $this->assertSame($before + [StoreCategory::where('seller_id', $this->seller1->id)->where('category_id', $toys->id)->value('id') => 'Toys & Games'],
            StoreCategory::where('seller_id', $this->seller1->id)->pluck('name', 'id')->all());
    }

    /** SEL-SCAT-005 (A25) */
    public function test_sel_scat_005_rename_a_category_to_a_duplicate_name(): void
    {
        $this->actingAs($this->seller1)->put(route('seller.store-categories.update', $this->cat5), ['name' => 'bags'])
            ->assertSessionHasErrors(['name' => 'A category named "bags" already exists in your store.']);
        $this->assertSame('Kitchen & Dining', $this->cat5->fresh()->name);
    }

    /** SEL-SCAT-006 */
    public function test_sel_scat_006_rename_and_reorder_categories(): void
    {
        $this->p04->storeCategories()->attach($this->cat5);
        $this->actingAs($this->seller1)->put(route('seller.store-categories.update', $this->cat5), ['name' => 'Kitchen'])->assertSessionHasNoErrors();
        $this->actingAs($this->seller1)->post(route('seller.store-categories.reorder'), ['ids' => [$this->cat5->id, $this->cat1->id, $this->cat2->id]])->assertSessionHasNoErrors();
        $this->p04->update(['stock' => 5]);

        $html = $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'data-store-category="Bags"'), strpos($html, 'data-store-category="Kitchen"'));
        $this->assertLessThan(strpos($html, 'data-store-category="Home Decor"'), strpos($html, 'data-store-category="Bags"'));
        $this->assertTrue($this->p04->storeCategories()->whereKey($this->cat5->id)->exists());
    }

    /** SEL-SCAT-007 */
    public function test_sel_scat_007_hide_a_category(): void
    {
        $this->actingAs($this->seller1)->patch(route('seller.store-categories.toggle', $this->cat1))->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertDontSee('data-store-category="Bags"', false);
        // The old category URL goes back to the store page.
        $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $this->cat1->id]))
            ->assertRedirect(route('stores.show', $this->seller1->id));
        // Only the category is hidden: P-01 is still found and buyable.
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Abaca Tote']))->assertSee('Abaca Woven Tote Bag');
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertOk();
    }

    /** SEL-SCAT-008 */
    public function test_sel_scat_008_show_a_hidden_category(): void
    {
        $this->cat1->update(['is_hidden' => true]);
        $this->actingAs($this->seller1)->patch(route('seller.store-categories.toggle', $this->cat1));

        $html = $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $this->cat1->id]))
            ->assertSee('data-store-category="Bags"', false)->assertSee('Abaca Woven Tote Bag')->getContent();
        // Back in its previous position (first).
        $this->assertLessThan(strpos($html, 'data-store-category="Home Decor"'), strpos($html, 'data-store-category="Bags"'));
    }

    /** SEL-SCAT-009 (A19) */
    public function test_sel_scat_009_delete_a_category_that_still_has_products(): void
    {
        $this->actingAs($this->seller1)->delete(route('seller.store-categories.destroy', $this->cat4))
            ->assertSessionHas('warning', fn ($m) => str_contains($m, '1 product will move to Uncategorized'));
        $this->assertNotNull($this->cat4->fresh());   // Cancel leaves everything unchanged

        $this->actingAs($this->seller1)->delete(route('seller.store-categories.destroy', $this->cat4), ['confirm' => 1])
            ->assertSessionHas('success', 'Category "Baskets" deleted. 1 product moved to Uncategorized.');
        $this->assertNull($this->cat4->fresh());
        $this->assertTrue($this->p02->fresh()->isPublished());

        $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => 'none']))
            ->assertSee('data-store-category="Uncategorized"', false)->assertSee('Rattan Fruit Basket');
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertOk();
        $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertSee('Rattan Fruit Basket');
    }

    /** SEL-SCAT-010 (A19) */
    public function test_sel_scat_010_delete_a_parent_category_with_subcategories(): void
    {
        $this->actingAs($this->seller1)->delete(route('seller.store-categories.destroy', $this->cat2))
            ->assertSessionHas('warning', fn ($m) => str_contains($m, '"Lamps" and "Baskets"') && str_contains($m, '2 products will move to Uncategorized'));
        $this->assertNotNull($this->cat2->fresh());

        $this->actingAs($this->seller1)->delete(route('seller.store-categories.destroy', $this->cat2), ['confirm' => 1])->assertSessionHas('success');
        $this->assertNull($this->cat2->fresh());
        $this->assertNull($this->cat3->fresh());
        $this->assertNull($this->cat4->fresh());
        $this->assertTrue($this->p02->fresh()->isPublished());
        $this->assertTrue($this->p03->fresh()->isPublished());
    }

    /** SEL-SCAT-011: an ALVY category keeps ALVY's name (it can't be renamed); an older typed one can. */
    public function test_sel_scat_011_subcategory_nesting_at_the_depth_limit(): void
    {
        $toys = \App\Models\Category::firstOrCreate(['name' => 'Toys & Games']);
        $cat = StoreCategory::forPlatformCategory($this->seller1->id, $toys->id);

        $this->actingAs($this->seller1)->from(route('seller.store-categories.index'))->put(route('seller.store-categories.update', $cat), ['name' => 'Laruan'])
            ->assertSessionHas('error', "This category comes from ALVY, so its name can't be changed. Delete it and add another one instead.");
        $this->assertSame('Toys & Games', $cat->fresh()->name);
        $this->actingAs($this->seller1)->put(route('seller.store-categories.update', $this->cat5), ['name' => 'Kitchen'])->assertSessionHasNoErrors();

        // The page marks it as an ALVY category and offers no Rename for it.
        $html = $this->actingAs($this->seller1)->get(route('seller.store-categories.index'))->assertSee('ALVY category')->getContent();
        $this->assertSame(1, substr_count($html, 'action="' . route('seller.store-categories.update', $cat) . '"'));         // Delete only
        $this->assertSame(2, substr_count($html, 'action="' . route('seller.store-categories.update', $this->cat5) . '"'));  // Rename + Delete

        // When ALVY renames the category, the store's name follows.
        $toys->update(['name' => 'Toys, Games & Hobbies']);
        $this->assertSame('Toys, Games & Hobbies', $cat->fresh()->name);
    }

    /** SEL-SCAT-012: a product sits under its ALVY category; moving it moves it; a new category is added to the store. */
    public function test_sel_scat_012_product_in_several_store_categories_at_the_limit(): void
    {
        $home = $this->p03->category_id;
        $toys = \App\Models\Category::firstOrCreate(['name' => 'Toys & Games']);
        $this->p03->storeCategories()->sync([$this->cat3->id]);   // an older typed category

        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p03), $this->editForm($this->p03))->assertSessionHasNoErrors();
        $homeCat = StoreCategory::where('seller_id', $this->seller1->id)->where('category_id', $home)->sole();
        $this->assertEqualsCanonicalizing([$this->cat3->id, $homeCat->id], $this->p03->storeCategories()->pluck('store_categories.id')->all());

        // Another ALVY category the store didn't have: added to the store, and the product moves there.
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p03), $this->editForm($this->p03, ['category_id' => $toys->id]))->assertSessionHasNoErrors();
        $toysCat = StoreCategory::where('seller_id', $this->seller1->id)->where('category_id', $toys->id)->sole();
        $this->assertEqualsCanonicalizing([$this->cat3->id, $toysCat->id], $this->p03->storeCategories()->pluck('store_categories.id')->all());

        foreach ([$toysCat, $this->cat3] as $cat) {
            $html = $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $cat->id]))->getContent();
            $this->assertSame(1, substr_count($html, 'alt="Capiz Shell Lamp"'), $cat->name);
        }
    }

    /** SEL-SCAT-013: the store tab is the product's ALVY category; sellers still can't make ALVY categories. */
    public function test_sel_scat_013_store_category_is_separate_from_platform_category(): void
    {
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $this->editForm($this->p01))->assertSessionHasNoErrors();
        $cat = StoreCategory::where('seller_id', $this->seller1->id)->where('category_id', $this->p01->category_id)->sole();
        $this->assertSame($this->p01->category->name, $cat->name);

        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Tote', 'category' => 'Bags & Luggage']))->assertSee('Abaca Woven Tote Bag');
        $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $cat->id]))->assertSee('Abaca Woven Tote Bag');
        // Sellers have no way to create or rename platform categories.
        $this->actingAs($this->seller1)->post('/admin/categories', ['name' => 'Seller Made'])->assertStatus(403);
        $this->assertFalse(\App\Models\Category::where('name', 'Seller Made')->exists());
    }
}
