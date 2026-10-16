<?php

namespace Tests\Feature\Seller;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Seller workbook S3 (Product Management, SEL-PROD-001…018) and S4 (Product Variants, SEL-VAR-001…012). */
class S03ProductsAndVariantsTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    private const PRICE_MSG = 'Enter a price from ₱1.00 to ₱1,000,000.00 with up to 2 decimal places.';
    private const STOCK_MSG = 'Stock must be a whole number from 0 to 99,999.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    private function publish(array $over = [])
    {
        return $this->actingAs($this->seller1)->from(route('seller.books.create'))->post(route('seller.books.store'), $this->productForm($over));
    }

    private function approve(Product $p): Product
    {
        $p->update(['approval_status' => 'approved']);   // ALVY approves new listings before buyers see them
        return $p->fresh();
    }

    /** Buri Coin Purse: Color Red, Blue × Size S ₱99, M ₱129, L ₱149, 5 each, SKU BCP-<COLOR>-<SIZE> (SEL-VAR-001 test data). */
    private function variantForm(array $rowOver = [], array $over = []): array
    {
        $rows = [];
        foreach (['Red', 'Blue'] as $c) {
            foreach (['S' => 99, 'M' => 129, 'L' => 149] as $size => $price) {
                $rows[] = ['name' => "{$c} / {$size}", 'price' => $price, 'stock' => 5, 'sku' => 'BCP-' . strtoupper($c) . "-{$size}"];
            }
        }
        foreach ($rowOver as $i => $row) {
            $rows[$i] = array_merge($rows[$i], $row);
        }
        return $this->productForm(array_merge([
            'title' => 'Buri Coin Purse',
            'variation_types' => [['name' => 'Color', 'options' => ['Red', 'Blue']], ['name' => 'Size', 'options' => ['S', 'M', 'L']]],
            'variations' => $rows,
        ], $over));
    }

    // ── S3 Product Management ───────────────────────────────────

    /** SEL-PROD-001 (A16, A17, A25) */
    public function test_sel_prod_001_add_and_publish_a_product_with_all_valid_data(): void
    {
        $this->publish(['description' => 'Set of 6 placemats, handwoven buri palm.', 'store_category_ids' => [$this->cat5->id]])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'submitted for admin approval'));
        $p = Product::where('title', 'Buri Placemat Set')->sole();
        $this->assertSame(['active', 'New', 30], [$p->status, $p->condition, $p->stock]);
        $this->assertEquals(299, $p->price);
        $this->assertEquals(0.6, $p->weight_kg);
        $this->assertSame(2, $p->images()->count());
        $this->assertSame([$this->cat5->id], $p->storeCategories()->pluck('store_categories.id')->all());

        $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertSee('Buri Placemat Set')->assertSee('Active');
        $this->approve($p);
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Buri Placemat']))->assertSee('Buri Placemat Set');
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Buri', 'category' => $this->homeLiving->name]))->assertSee('Buri Placemat Set');
        $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $this->cat5->id]))->assertSee('Buri Placemat Set');
        $this->actingAs($this->buyer1)->get(route('products.show', $p->slug))->assertOk()->assertSee('₱299.00');
    }

    /** SEL-PROD-002 */
    public function test_sel_prod_002_each_required_field_left_blank(): void
    {
        $this->publish(['title' => ''])->assertSessionHasErrors(['title' => 'Please enter a product name.'])->assertSessionHasInput('price', '299.00');
        $this->publish(['description' => ''])->assertSessionHasErrors(['description' => 'Please describe the product (20 to 3,000 characters).']);
        $this->publish(['images' => []])->assertSessionHasErrors(['images' => 'Please add at least one photo.']);
        $this->publish(['category_id' => ''])->assertSessionHasErrors(['category_id' => 'Please choose a platform category.']);
        $this->publish(['price' => ''])->assertSessionHasErrors(['price' => 'Please enter a price.'])->assertSessionHasInput('title', 'Buri Placemat Set');
        $this->publish(['stock' => ''])->assertSessionHasErrors(['stock' => 'Please enter the available stock.']);
        $this->publish(['weight_kg' => ''])->assertSessionHasErrors(['weight_kg' => 'Please enter the weight.']);
        $this->assertFalse(Product::where('title', 'Buri Placemat Set')->exists());
    }

    /** SEL-PROD-003 (A25) */
    public function test_sel_prod_003_product_name_and_description_length(): void
    {
        $name = 'The product name must be 5 to 120 characters.';
        $desc = 'The description must be 20 to 3,000 characters.';

        $this->publish(['title' => 'Tote'])->assertSessionHasErrors(['title' => $name]);
        $this->publish(['title' => str_repeat('n', 121)])->assertSessionHasErrors(['title' => $name]);
        $this->publish(['description' => str_repeat('d', 19)])->assertSessionHasErrors(['description' => $desc]);
        $this->publish(['description' => str_repeat('d', 3001)])->assertSessionHasErrors(['description' => $desc]);

        $this->publish(['title' => 'Totes'])->assertSessionHasNoErrors();
        $this->publish(['title' => str_repeat('n', 120)])->assertSessionHasNoErrors();
        $this->publish(['title' => 'Twenty char desc', 'description' => str_repeat('d', 20)])->assertSessionHasNoErrors();
        $this->publish(['title' => 'Long desc item', 'description' => str_repeat('d', 3000)])->assertSessionHasNoErrors();
        $this->assertSame(3000, mb_strlen(Product::where('title', 'Long desc item')->value('description')));
        $this->assertTrue(Product::where('title', 'Totes')->exists() && Product::where('title', str_repeat('n', 120))->exists());

        // Symbols and emoji are shown as text, never as HTML.
        $this->publish(['title' => 'Abaca Tote 👜 "Limited" & <New>'])->assertSessionHasNoErrors();
        $p = $this->approve(Product::where('title', 'Abaca Tote 👜 "Limited" & <New>')->sole());
        $html = $this->actingAs($this->buyer1)->get(route('products.show', $p->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Abaca Tote 👜 &quot;Limited&quot; &amp; &lt;New&gt;', $html);
        $this->assertStringNotContainsString('<New>', $html);
    }

    /** SEL-PROD-004 (A17) */
    public function test_sel_prod_004_price_validation(): void
    {
        foreach (['0', '-50', '0.99', '199.999', '1000000.01', 'abc'] as $price) {
            $this->publish(['price' => $price])->assertSessionHasErrors(['price' => self::PRICE_MSG]);
        }
        $this->publish(['price' => ''])->assertSessionHasErrors(['price' => 'Please enter a price.']);
        $this->assertSame(0, Product::where('title', 'Buri Placemat Set')->count());

        foreach (['1.00', '1.01', '999999.99', '1000000.00'] as $i => $price) {
            $this->publish(['price' => $price, 'title' => "Price ok {$i}"])->assertSessionHasNoErrors();
            $this->assertEquals((float) $price, Product::where('title', "Price ok {$i}")->value('price'));
        }
    }

    /** SEL-PROD-005 (A17) */
    public function test_sel_prod_005_stock_validation(): void
    {
        foreach (['-1', '100000', '2.5'] as $stock) {
            $this->publish(['stock' => $stock])->assertSessionHasErrors(['stock' => self::STOCK_MSG]);
        }
        foreach (['0', '1', '99999'] as $stock) {
            $this->publish(['stock' => $stock, 'title' => "Stock {$stock} item"])->assertSessionHasNoErrors();
        }
        $soldOut = $this->approve(Product::where('title', 'Stock 0 item')->sole());
        $this->assertSame(0, $soldOut->stock);
        $this->actingAs($this->buyer1)->get(route('products.show', $soldOut->slug))->assertSee('Sold out');
        $this->assertSame(99999, Product::where('title', 'Stock 99999 item')->value('stock'));
    }

    /** SEL-PROD-006 (A16) */
    public function test_sel_prod_006_image_count_at_the_limits(): void
    {
        $photos = fn ($n) => array_map(fn ($i) => $this->jpg("p{$i}.jpg", 100), range(1, $n));

        $this->publish(['images' => []])->assertSessionHasErrors(['images' => 'Please add at least one photo.']);
        $this->publish(['title' => 'One photo', 'images' => $photos(1)])->assertSessionHasNoErrors();
        $this->publish(['title' => 'Nine photos', 'images' => $photos(9)])->assertSessionHasNoErrors();
        $this->publish(['title' => 'Ten photos', 'images' => $photos(10)])->assertSessionHasErrors('images');
        $this->assertSame(9, Product::where('title', 'Nine photos')->sole()->images()->count());
        $this->assertFalse(Product::where('title', 'Ten photos')->exists());

        // Editing can't push a product past 9 either, and the form stops at 9.
        $nine = Product::where('title', 'Nine photos')->sole();
        $this->actingAs($this->seller1)->put(route('seller.books.update', $nine), $this->editForm($nine, ['images' => $photos(1)]))->assertSessionHasErrors('images');
        $this->actingAs($this->seller1)->get(route('seller.books.create'))->assertSee('const MAX     = 9;', false);
    }

    /** SEL-PROD-007 (A16) */
    public function test_sel_prod_007_image_file_type_and_size(): void
    {
        $this->publish(['title' => 'Five MB photo', 'images' => [$this->jpg('size_5.0MB.jpg', 5120)]])->assertSessionHasNoErrors();

        $this->publish(['images' => [$this->jpg('size_5.1MB.jpg', 5222)]])->assertSessionHasErrors(['images.0' => 'Each photo must be 5 MB or smaller.']);
        $this->publish(['images' => [UploadedFile::fake()->create('price_list.docx', 320, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]])
            ->assertSessionHasErrors(['images.0' => 'Each photo must be a JPG or PNG image.']);
        $this->publish(['images' => [UploadedFile::fake()->create('renamed_exe.jpg', 2100, 'application/x-msdownload')]])->assertSessionHasErrors('images.0');
        $this->assertFalse(Product::where('title', 'Buri Placemat Set')->exists());
        $this->assertSame(1, Product::where('title', 'Five MB photo')->sole()->images()->count());
    }

    /** SEL-PROD-008 (A20) */
    public function test_sel_prod_008_weight_and_dimensions_validation(): void
    {
        $this->publish(['weight_kg' => '0'])->assertSessionHasErrors(['weight_kg' => 'The weight must be at least 0.01 kg.']);
        $this->publish(['weight_kg' => '-1'])->assertSessionHasErrors(['weight_kg' => 'The weight must be at least 0.01 kg.']);
        $this->publish(['length_cm' => '0', 'width_cm' => '0', 'height_cm' => '0'])
            ->assertSessionHasErrors(['length_cm' => 'Length must be more than 0 cm.', 'width_cm' => 'Width must be more than 0 cm.', 'height_cm' => 'Height must be more than 0 cm.']);
        $this->publish(['length_cm' => 'abc'])->assertSessionHasErrors(['length_cm' => 'Length must be a number.']);

        $this->publish(['weight_kg' => '0.01'])->assertSessionHasNoErrors();
        $p = $this->approve(Product::where('title', 'Buri Placemat Set')->sole());
        $this->assertEquals(0.01, $p->weight_kg);
        // 0.01 kg is charged as 1 kg at checkout.
        $line = $this->cart($this->buyer1, $p);
        $this->openCheckout($this->buyer1, [$line])->assertOk();
        $quote = $this->actingAs($this->buyer1)->postJson(route('checkout.preview'), ['address_id' => $this->addr1->id])->json();
        $this->assertEquals(1, $quote['stores'][$this->seller1->id]['chargeable']);
    }

    /** SEL-PROD-009 */
    public function test_sel_prod_009_save_as_draft(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.books.store'), ['title' => 'Draft Woven Fan', 'action' => 'draft', 'language' => 'English', 'format' => 'Standard'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Draft saved.'));
        $draft = Product::where('title', 'Draft Woven Fan')->sole();
        $this->assertSame('draft', $draft->status);
        $draft->update(['approval_status' => 'approved']);   // even approved, a draft stays hidden

        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Woven Fan']))->assertDontSee('Draft Woven Fan');
        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertDontSee('Draft Woven Fan');
        $this->actingAs($this->buyer1)->get(route('products.show', $draft->slug))->assertNotFound();
    }

    /** SEL-PROD-010 */
    public function test_sel_prod_010_publish_a_draft_with_missing_data(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.books.store'), ['title' => 'Draft Woven Fan', 'action' => 'draft', 'language' => 'English', 'format' => 'Standard']);
        $draft = Product::where('title', 'Draft Woven Fan')->sole();

        $this->actingAs($this->seller1)->from(route('seller.books.edit', $draft))
            ->put(route('seller.books.update', $draft), ['title' => 'Draft Woven Fan', 'action' => 'publish', 'status' => 'active', 'language' => 'English', 'format' => 'Standard'])
            ->assertSessionHasErrors(['description', 'category_id', 'price', 'stock', 'weight_kg', 'images']);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    /** SEL-PROD-011 */
    public function test_sel_prod_011_deactivate_and_reactivate_a_product(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->actingAs($this->seller1)->patch(route('seller.books.archive', $this->p02))->assertSessionHas('success');

        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Rattan']))->assertDontSee('alt="Rattan Fruit Basket"', false);
        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertDontSee('alt="Rattan Fruit Basket"', false);
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertNotFound()->assertSee('unavailable');
        $this->assertSame('No longer available', $line->fresh()->unavailableReason());
        $this->openCheckout($this->buyer1, [$line])->assertRedirect(route('cart.index'));

        $this->actingAs($this->seller1)->patch(route('seller.books.unarchive', $this->p02))->assertSessionHas('success');
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Rattan']))->assertSee('alt="Rattan Fruit Basket"', false);
        $this->assertNull($line->fresh()->unavailableReason());
        $this->assertEquals(450, $this->p02->fresh()->price);
        $this->assertSame(20, $this->p02->fresh()->stock);
    }

    /** SEL-PROD-012 */
    public function test_sel_prod_012_duplicate_a_product(): void
    {
        ProductImage::create(['product_id' => $this->p02->id, 'path' => 'products/gallery/basket.jpg', 'sort_order' => 0]);
        $this->actingAs($this->seller1)->post(route('seller.books.duplicate', $this->p02))->assertRedirect()->assertSessionHas('success');

        $copy = Product::where('title', 'Copy of Rattan Fruit Basket')->sole();
        $this->assertNotSame($this->p02->id, $copy->id);
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($this->p02->fresh()->product_code, $copy->product_code);
        $this->assertEquals(450, $copy->price);
        $this->assertSame(1, $copy->images()->count());
        $this->assertSame([$this->cat4->id], $copy->storeCategories()->pluck('store_categories.id')->all());
        $this->assertSame('Rattan Fruit Basket', $this->p02->fresh()->title);
        // Buyers still see only one Rattan Fruit Basket.
        $html = $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Rattan']))->getContent();
        $this->assertSame(1, substr_count($html, 'alt="Rattan Fruit Basket"'));
    }

    /** SEL-PROD-013 */
    public function test_sel_prod_013_edit_price_of_a_product_in_a_buyers_cart(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p02), $this->editForm($this->p02, ['price' => '420.00']))->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertSee('₱420.00')->assertDontSee('₱450.00');
        $this->assertEquals(420, CartItem::sole()->fresh()->subtotal());
        $this->openCheckout($this->buyer1, [$line])->assertOk()->assertSee('₱420.00');
    }

    /** SEL-PROD-014 (A19) */
    public function test_sel_prod_014_delete_a_product_with_unfinished_orders(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $order->update(['status' => Order::PROCESSING]);   // To Ship

        $this->actingAs($this->seller1)->delete(route('seller.books.destroy', $this->p02))
            ->assertSessionHas('error', '"Rattan Fruit Basket" has orders that are not finished yet, so it cannot be deleted. Deactivate it instead.');
        $this->assertNotNull($this->p02->fresh());
        $this->assertTrue($this->p02->fresh()->isPublished());
    }

    /** SEL-PROD-015 (A19) */
    public function test_sel_prod_015_delete_a_product_with_only_finished_orders(): void
    {
        $this->p04->update(['stock' => 3]);
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p04)])->first(), Order::COMPLETED);

        $this->actingAs($this->seller1)->delete(route('seller.books.destroy', $this->p04))->assertSessionHas('success', 'Product deleted.');
        $this->assertSoftDeleted($this->p04);
        $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertDontSee('Banig Placemat Set of 4');
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Banig']))->assertDontSee('alt="Banig Placemat Set of 4"', false);

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()->assertSee('Banig Placemat Set of 4')->assertSee('350.00');
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertOk()->assertSee('Banig Placemat Set of 4')->assertSee('350.00');
    }

    /** SEL-PROD-016 (A19) */
    public function test_sel_prod_016_bulk_deactivate_and_bulk_delete_with_a_mixed_selection(): void
    {
        $this->publish();
        $buri = Product::where('title', 'Buri Placemat Set')->sole();
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);   // P-02 has an unfinished order
        $ids = [$this->p02->id, $this->p04->id, $buri->id];

        $this->actingAs($this->seller1)->post(route('seller.books.bulk'), ['action' => 'deactivate', 'ids' => $ids])->assertSessionHas('success', '3 products deactivated.');
        $this->assertSame(3, Product::whereIn('id', $ids)->where('is_archived', true)->count());
        Product::whereIn('id', $ids)->update(['is_archived' => false]);

        $this->actingAs($this->seller1)->post(route('seller.books.bulk'), ['action' => 'delete', 'ids' => $ids])
            ->assertSessionHas('warning', '2 products deleted. Skipped "Rattan Fruit Basket": it has orders that are not finished yet.');
        $this->assertNotNull($this->p02->fresh());
        $this->assertSoftDeleted($this->p04);
        $this->assertSoftDeleted($buri);

        // Another seller's products can't be touched.
        $this->actingAs($this->seller1)->post(route('seller.books.bulk'), ['action' => 'delete', 'ids' => [$this->p05->id]])->assertSessionHas('error');
        $this->assertNotNull($this->p05->fresh());
    }

    /** SEL-PROD-017 (A37) */
    public function test_sel_prod_017_double_click_publish(): void
    {
        $form = ['title' => 'Double Click Test Tote', 'form_token' => 'tok-' . uniqid()];
        $this->publish($form)->assertSessionHasNoErrors();
        $this->publish($form)->assertSessionHas('success', 'This product was already saved.');
        $this->assertSame(1, Product::where('title', 'Double Click Test Tote')->count());
    }

    /** SEL-PROD-018 (A22) */
    public function test_sel_prod_018_edit_another_sellers_product(): void
    {
        $this->actingAs($this->seller1)->get(route('seller.books.edit', $this->p05))->assertForbidden()->assertDontSee('USB-C Fast Charger 20W');
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p05), $this->editForm($this->p05, ['title' => 'Hijacked Charger', 'price' => '1.00']))
            ->assertForbidden();
        $this->assertSame('USB-C Fast Charger 20W', $this->p05->fresh()->title);
        $this->assertEquals(399, $this->p05->fresh()->price);
    }

    // ── S4 Product Variants ─────────────────────────────────────

    /** SEL-VAR-001 (A7) */
    public function test_sel_var_001_create_a_product_with_2_variation_types(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm())->assertSessionHasNoErrors();
        $p = $this->approve(Product::where('title', 'Buri Coin Purse')->sole());

        $this->assertSame(['Red / S', 'Red / M', 'Red / L', 'Blue / S', 'Blue / M', 'Blue / L'], $p->variations()->pluck('name')->all());
        $this->assertEquals(149, $this->variation($p, 'Blue / L')->price);
        $this->assertSame('BCP-BLUE-L', $this->variation($p, 'Blue / L')->sku);
        $this->assertSame(5, $this->variation($p, 'Red / M')->stock);
        $this->assertSame('Color, Size', $p->variation_labels);
        $this->actingAs($this->buyer1)->get(route('products.show', $p->slug))->assertOk()->assertSee('₱99.00 – ₱149.00');
    }

    /** SEL-VAR-002 (A7) */
    public function test_sel_var_002_add_a_third_variation_type(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm())->assertSessionHasNoErrors();
        $purse = Product::where('title', 'Buri Coin Purse')->sole();

        $types = [['name' => 'Color', 'options' => ['Red', 'Blue']], ['name' => 'Size', 'options' => ['S', 'M', 'L']], ['name' => 'Material', 'options' => ['Abaca', 'Buri']]];
        $message = 'A product can have at most 2 variation types (for example Color and Size).';
        $this->actingAs($this->seller1)->put(route('seller.books.update', $purse), $this->editForm($purse, ['variation_types' => $types]))
            ->assertSessionHasErrors(['variation_types' => $message]);
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([0 => ['name' => 'Red / S / Abaca']], ['title' => 'Three types', 'variation_types' => null]))
            ->assertSessionHasErrors(['variations' => $message]);
        $this->assertSame(6, $purse->variations()->count());
    }

    /** SEL-VAR-003 (A7) */
    public function test_sel_var_003_options_per_type_at_the_limit(): void
    {
        $colors = fn ($n) => array_map(fn ($i) => "Color {$i}", range(1, $n));
        $rows = fn ($n) => array_map(fn ($c) => ['name' => $c, 'price' => 199, 'stock' => 1], $colors($n));

        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->productForm(['title' => 'Twenty colors',
            'variation_types' => [['name' => 'Color', 'options' => $colors(20)]], 'variations' => $rows(20)]))->assertSessionHasNoErrors();
        $this->assertSame(20, Product::where('title', 'Twenty colors')->sole()->variations()->count());

        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->productForm(['title' => 'Twenty-one colors',
            'variation_types' => [['name' => 'Color', 'options' => $colors(21)]], 'variations' => $rows(21)]))
            ->assertSessionHasErrors(['variation_types.0.options' => 'A variation type can have at most 20 options.']);
        $this->assertFalse(Product::where('title', 'Twenty-one colors')->exists());
    }

    /** SEL-VAR-004 */
    public function test_sel_var_004_duplicate_or_empty_option_names(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([], [
            'variation_types' => [['name' => 'Color', 'options' => ['Red', 'red', '']]],
        ]))->assertSessionHasErrors([
            'variation_types.0.options.1' => 'Each option must be different (Red and red count as the same).',
            'variation_types.0.options.2' => 'Option names cannot be empty.',
        ]);

        // The same in the generated rows.
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([1 => ['name' => 'red / s']], ['variation_types' => null]))
            ->assertSessionHasErrors(['variations.1.name' => 'Each variant must be different (Red and red count as the same).']);
        $this->assertFalse(Product::where('title', 'Buri Coin Purse')->exists());
    }

    /** SEL-VAR-005 (A17) */
    public function test_sel_var_005_missing_or_invalid_price_or_stock_on_one_variant(): void
    {
        // Row 5 is Blue / L; only that row is flagged.
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([5 => ['price' => '']]))
            ->assertSessionHasErrors(['variations.5.price' => 'Enter a price for every variant.'])->assertSessionDoesntHaveErrors(['variations.4.price']);
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([5 => ['price' => '0.00']]))
            ->assertSessionHasErrors(['variations.5.price' => self::PRICE_MSG]);
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([5 => ['stock' => '-1']]))
            ->assertSessionHasErrors(['variations.5.stock' => self::STOCK_MSG]);
        $this->assertFalse(Product::where('title', 'Buri Coin Purse')->exists());
        // The bad row is highlighted on the form.
        $this->actingAs($this->seller1)->get(route('seller.books.create'))->assertSee('rowMissing(v)', false);
    }

    /** SEL-VAR-006 */
    public function test_sel_var_006_duplicate_sku(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([3 => ['sku' => 'BCP-RED-S']]))
            ->assertSessionHasErrors(['variations.3.sku' => 'Each variant needs a different SKU.']);

        $this->p01->variations()->where('name', 'Natural / S')->update(['sku' => 'MCH-TOTE-NAT-S']);
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([0 => ['sku' => 'MCH-TOTE-NAT-S']]))
            ->assertSessionHasErrors(['variations' => 'SKU MCH-TOTE-NAT-S is already used by another product in your store.']);
        $this->assertFalse(Product::where('title', 'Buri Coin Purse')->exists());
    }

    /** SEL-VAR-007 */
    public function test_sel_var_007_price_range_on_the_product_page_and_search(): void
    {
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Abaca Woven Tote Bag']))->assertSee('₱199 – ₱299');
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertOk()->assertSee('₱199.00 – ₱299.00')
            // Choosing Natural-M shows that variant's single price (from the variant list the page script uses).
            ->assertSee('"name":"Natural \/ M"', false)->assertSee('"price":249', false);
    }

    /** SEL-VAR-008 */
    public function test_sel_var_008_variant_image_switching(): void
    {
        $form = $this->editForm($this->p01);
        foreach ($form['variations'] as $i => $row) {
            $form['variations'][$i]['image'] = str_starts_with($row['name'], 'Natural')
                ? $this->jpg('product_front.jpg', 200) : UploadedFile::fake()->image('product_side.png')->size(200);
        }
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $form)->assertSessionHasNoErrors();
        $this->assertSame(6, $this->p01->variations()->whereNotNull('image_path')->count());

        $natural = $this->variation($this->p01, 'Natural / S')->image_path;
        $black   = $this->variation($this->p01, 'Black / S')->image_path;
        $this->assertNotSame($natural, $black);
        // The page script switches the main image to the chosen variant's image.
        $html = $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->getContent();
        $this->assertStringContainsString(str_replace('/', '\/', $natural), $html);
        $this->assertStringContainsString(str_replace('/', '\/', $black), $html);
    }

    /** SEL-VAR-009 */
    public function test_sel_var_009_variant_with_0_stock(): void
    {
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertOk()
            ->assertSee('"name":"Black \/ M"', false)->assertSee('"stock":0', false);
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p01->id), ['variation_id' => $this->variation($this->p01, 'Black / M')->id, 'quantity' => 1])
            ->assertSessionHas('error', '"Black / M" is out of stock.');
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p01->id), ['variation_id' => $this->variation($this->p01, 'Natural / M')->id, 'quantity' => 1])
            ->assertSessionHas('success');
    }

    /** SEL-VAR-010 */
    public function test_sel_var_010_change_options_after_orders_exist(): void
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p01, 1, 'Natural / M')])->first(), Order::COMPLETED);

        $form = $this->editForm($this->p01);
        foreach ($form['variations'] as $i => $row) {
            $form['variations'][$i]['name'] = str_replace('Natural', 'Beige', $row['name']);
            if ($form['variations'][$i]['name'] === 'Beige / M') {
                $form['variations'][$i]['price'] = 259;
            }
        }
        $form['variations'][] = ['name' => 'Brown / M', 'price' => 259, 'stock' => 4];
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $form)->assertSessionHasNoErrors();

        $item = OrderItem::where('order_id', $order->id)->sole();
        $this->assertSame('Natural / M', $item->variation_name);
        $this->assertEquals(249, $item->price);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('Natural / M')->assertSee('249.00');
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Natural / M')->assertSee('249.00');
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertSee('Beige')->assertSee('Brown')->assertSee('"price":259', false);
    }

    /** SEL-VAR-011 */
    public function test_sel_var_011_remove_a_variant_that_is_in_a_buyers_cart(): void
    {
        $gone  = $this->cart($this->buyer1, $this->p01, 1, 'Black / L');
        $other = $this->cart($this->buyer1, $this->p02);

        $form = $this->editForm($this->p01);
        $form['variations'] = array_values(array_filter($form['variations'], fn ($r) => $r['name'] !== 'Black / L'));
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $form)->assertSessionHasNoErrors();

        $this->assertSame('This option is no longer available', $gone->fresh()->unavailableReason());
        $this->assertNull($other->fresh()->unavailableReason());
        $this->openCheckout($this->buyer1, [$gone, $other])->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'This option is no longer available'));
        $this->openCheckout($this->buyer1, [$other])->assertOk();
    }

    /** SEL-VAR-012 (A19) */
    public function test_sel_var_012_remove_a_variant_with_unfinished_orders(): void
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p01, 1, 'Natural / S')])->first(), Order::PROCESSING);

        $form = $this->editForm($this->p01);
        $form['variations'] = array_values(array_filter($form['variations'], fn ($r) => $r['name'] !== 'Natural / S'));
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $form)
            ->assertSessionHasErrors(['variations' => 'Variant "Natural / S" has orders that are not finished yet, so it can\'t be removed. Set its stock to 0 instead.']);

        $this->assertNotNull($this->variation($this->p01, 'Natural / S'));
        // The order can still be packed and shipped.
        $this->actingAs($this->seller1)->post(route('seller.orders.pack', $order->id))->assertSessionHas('success');
    }
}
