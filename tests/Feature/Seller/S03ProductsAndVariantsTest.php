<?php

namespace Tests\Feature\Seller;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Seller workbook S3 (Product Management) and S4 (Product Variants). */
class S03ProductsAndVariantsTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

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

    /** The variation form for Color: Red, Blue × Size: S, M, L (SEL-VAR-001 test data). */
    private function variantForm(array $rowOver = [], array $over = []): array
    {
        $rows = [];
        foreach (['Red', 'Blue'] as $c) {
            foreach (['S' => 199, 'M' => 249, 'L' => 299] as $size => $price) {
                $rows[] = ['name' => "{$c} / {$size}", 'price' => $price, 'stock' => 5, 'sku' => 'MCH-TEE-' . strtoupper($c) . "-{$size}"];
            }
        }
        foreach ($rowOver as $i => $row) {
            $rows[$i] = array_merge($rows[$i], $row);
        }
        return $this->productForm(array_merge([
            'title' => 'Abaca Tee',
            'variation_types' => [['name' => 'Color', 'options' => ['Red', 'Blue']], ['name' => 'Size', 'options' => ['S', 'M', 'L']]],
            'variations' => $rows,
        ], $over));
    }

    // ── S3 Product Management ───────────────────────────────────

    /** SEL-PROD-001 (A16, A17) */
    public function test_sel_prod_001_add_and_publish_a_product(): void
    {
        $this->publish(['store_category_ids' => [$this->cat5->id]])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'submitted for admin approval'));
        $p = Product::where('title', 'Buri Placemat Set')->sole();
        $this->assertSame('active', $p->status);
        $this->assertSame('New', $p->condition);
        $this->assertSame(2, $p->images()->count());
        $this->assertEquals(0.6, $p->weight_kg);

        $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertSee('Buri Placemat Set')->assertSee('Active');
        $this->approve($p);
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Buri Placemat']))->assertSee('Buri Placemat Set');
        $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'scat' => $this->cat5->id]))->assertSee('Buri Placemat Set');
    }

    /** SEL-PROD-002 */
    public function test_sel_prod_002_required_fields_left_blank(): void
    {
        $this->publish(['title' => ''])->assertSessionHasErrors(['title' => 'Please enter a product name.']);
        $this->publish(['images' => []])->assertSessionHasErrors(['images' => 'Please add at least one photo.']);
        $this->publish(['category_id' => ''])->assertSessionHasErrors(['category_id' => 'Please choose a platform category.']);
        $this->publish(['price' => ''])->assertSessionHasErrors(['price' => 'Please enter a price.']);
        $this->publish(['stock' => ''])->assertSessionHasErrors(['stock' => 'Please enter the available stock.']);
        $this->publish(['weight_kg' => ''])->assertSessionHasErrors(['weight_kg' => 'Please enter the weight.']);
        $this->assertFalse(Product::where('title', 'Buri Placemat Set')->exists());
    }

    /** SEL-PROD-003 (A17) */
    public function test_sel_prod_003_price_validation(): void
    {
        $message = 'Enter a price from ₱1.00 to ₱1,000,000.00 with up to 2 decimal places.';
        foreach (['0', '-50', '0.99', '199.999', '1000000.01', 'abc'] as $price) {
            $this->publish(['price' => $price])->assertSessionHasErrors(['price' => $message]);
        }
        foreach (['1.00', '1000000.00'] as $i => $price) {
            $this->publish(['price' => $price, 'title' => "Price ok {$i}"])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, Product::where('title', 'like', 'Price ok%')->count());
    }

    /** SEL-PROD-004 (A16) */
    public function test_sel_prod_004_image_count_limits(): void
    {
        $photos = fn ($n) => array_map(fn ($i) => $this->jpg("p{$i}.jpg", 100), range(1, $n));

        $this->publish(['images' => []])->assertSessionHasErrors('images');
        $this->publish(['title' => 'One photo', 'images' => $photos(1)])->assertSessionHasNoErrors();
        $this->publish(['title' => 'Nine photos', 'images' => $photos(9)])->assertSessionHasNoErrors();
        $this->publish(['title' => 'Ten photos', 'images' => $photos(10)])->assertSessionHasErrors('images');
        $this->assertSame(9, Product::where('title', 'Nine photos')->sole()->images()->count());
        $this->assertFalse(Product::where('title', 'Ten photos')->exists());

        // The form never adds a 10th photo either.
        $this->actingAs($this->seller1)->get(route('seller.books.create'))->assertSee('const MAX     = 9;', false);
    }

    /** SEL-PROD-005 (A20) */
    public function test_sel_prod_005_weight_and_dimensions(): void
    {
        $this->publish(['weight_kg' => '0'])->assertSessionHasErrors(['weight_kg' => 'The weight must be at least 0.01 kg.']);
        $this->publish(['weight_kg' => '-1'])->assertSessionHasErrors(['weight_kg' => 'The weight must be at least 0.01 kg.']);
        $this->publish(['weight_kg' => 'abc'])->assertSessionHasErrors(['weight_kg' => 'The weight must be a number of at least 0.01 kg.']);
        $this->publish(['length_cm' => '0', 'width_cm' => '0', 'height_cm' => '0'])
            ->assertSessionHasErrors(['length_cm' => 'Length must be more than 0 cm.', 'width_cm' => 'Width must be more than 0 cm.', 'height_cm' => 'Height must be more than 0 cm.']);
        $this->publish(['length_cm' => 'abc'])->assertSessionHasErrors(['length_cm' => 'Length must be a number.']);

        $this->publish(['weight_kg' => '0.01'])->assertSessionHasNoErrors();
        $p = $this->approve(Product::where('title', 'Buri Placemat Set')->sole());
        $this->assertEquals(0.01, $p->weight_kg);
        // The weight is what the shipping fee uses: 0.01 kg → 1 kg chargeable.
        $line = $this->cart($this->buyer1, $p);
        $this->openCheckout($this->buyer1, [$line])->assertOk();
        $quote = $this->actingAs($this->buyer1)->postJson(route('checkout.preview'), ['address_id' => $this->addr1->id])->json();
        $this->assertEquals(1, $quote['stores'][$this->seller1->id]['chargeable']);
    }

    /** SEL-PROD-006 */
    public function test_sel_prod_006_save_as_draft(): void
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

    /** SEL-PROD-007 */
    public function test_sel_prod_007_deactivate_and_reactivate(): void
    {
        $this->actingAs($this->seller1)->patch(route('seller.books.archive', $this->p02))->assertSessionHas('success');
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Rattan']))->assertDontSee('alt="Rattan Fruit Basket"', false);
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertNotFound()->assertSee('unavailable');

        $this->actingAs($this->seller1)->patch(route('seller.books.unarchive', $this->p02))->assertSessionHas('success');
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Rattan']))->assertSee('alt="Rattan Fruit Basket"', false);
        $this->assertEquals(450, $this->p02->fresh()->price);
        $this->assertSame(20, $this->p02->fresh()->stock);
    }

    /** SEL-PROD-008 */
    public function test_sel_prod_008_duplicate_a_product(): void
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
    }

    /** SEL-PROD-009 */
    public function test_sel_prod_009_price_change_reaches_the_cart(): void
    {
        $this->cart($this->buyer1, $this->p02);
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p02), $this->editForm($this->p02, ['price' => '420.00']))->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertSee('₱420.00')->assertDontSee('₱450.00');
        $this->assertEquals(420, CartItem::sole()->fresh()->subtotal());
    }

    /** SEL-PROD-010 (A19) */
    public function test_sel_prod_010_product_with_active_orders_cannot_be_deleted(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $order->update(['status' => Order::PROCESSING]);   // To Ship

        $this->actingAs($this->seller1)->delete(route('seller.books.destroy', $this->p02))
            ->assertSessionHas('error', '"Rattan Fruit Basket" has orders that are not finished yet, so it cannot be deleted. Deactivate it instead.');
        $this->assertNotNull($this->p02->fresh());
    }

    /** SEL-PROD-011 (A19) */
    public function test_sel_prod_011_product_with_only_finished_orders_can_be_deleted(): void
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first(), Order::COMPLETED);

        $this->actingAs($this->seller1)->delete(route('seller.books.destroy', $this->p02))->assertSessionHas('success', 'Product deleted.');
        $this->assertSoftDeleted($this->p02);
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Rattan']))->assertDontSee('alt="Rattan Fruit Basket"', false);

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()->assertSee('Rattan Fruit Basket')->assertSee('450.00');
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertOk()->assertSee('Rattan Fruit Basket');
    }

    /** SEL-PROD-012 (A19) */
    public function test_sel_prod_012_bulk_deactivate_and_bulk_delete(): void
    {
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);   // P-02 has an active order
        $ids = [$this->p02->id, $this->p03->id, $this->p04->id];

        $this->actingAs($this->seller1)->post(route('seller.books.bulk'), ['action' => 'deactivate', 'ids' => $ids])->assertSessionHas('success', '3 products deactivated.');
        $this->assertSame(3, Product::whereIn('id', $ids)->where('is_archived', true)->count());

        $this->actingAs($this->seller1)->post(route('seller.books.bulk'), ['action' => 'delete', 'ids' => $ids])
            ->assertSessionHas('warning', '2 products deleted. Skipped "Rattan Fruit Basket": it has orders that are not finished yet.');
        $this->assertNotNull($this->p02->fresh());
        $this->assertSoftDeleted($this->p03);
        $this->assertSoftDeleted($this->p04);

        // Another seller's products can't be touched.
        $this->actingAs($this->seller1)->post(route('seller.books.bulk'), ['action' => 'delete', 'ids' => [$this->p05->id]])->assertSessionHas('error');
        $this->assertNotNull($this->p05->fresh());
    }

    /** SEL-PROD-013 (A25: product name 5–120 characters) */
    public function test_sel_prod_013_long_name_with_emoji_and_symbols(): void
    {
        $name = mb_substr('Abaca Tote 👜 "Limited" & <New> ' . str_repeat('x', 200), 0, 120);
        $this->publish(['title' => $name])->assertSessionHasNoErrors();
        $p = $this->approve(Product::where('title', $name)->sole());

        $html = $this->actingAs($this->buyer1)->get(route('products.show', $p->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Abaca Tote 👜 &quot;Limited&quot; &amp; &lt;New&gt;', $html);
        $this->assertStringNotContainsString('<New>', $html);

        $this->publish(['title' => $name . 'y'])->assertSessionHasErrors(['title' => 'The product name must be 5 to 120 characters.']);
        $this->publish(['title' => 'Mat'])->assertSessionHasErrors(['title' => 'The product name must be 5 to 120 characters.']);
    }

    // ── S4 Product Variants ─────────────────────────────────────

    /** SEL-VAR-001 (A7) */
    public function test_sel_var_001_two_variation_types(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm())->assertSessionHasNoErrors();
        $p = Product::where('title', 'Abaca Tee')->sole();

        $this->assertSame(6, $p->variations()->count());
        $this->assertSame(['Red / S', 'Red / M', 'Red / L', 'Blue / S', 'Blue / M', 'Blue / L'], $p->variations()->pluck('name')->all());
        $this->assertEquals(299, $this->variation($p, 'Blue / L')->price);
        $this->assertSame('MCH-TEE-BLUE-L', $this->variation($p, 'Blue / L')->sku);
        $this->assertSame('Color, Size', $p->variation_labels);
        $this->assertSame(30, $p->fresh()->stock);   // 6 × 5
    }

    /** SEL-VAR-002 (A7) */
    public function test_sel_var_002_third_variation_type(): void
    {
        $types = [['name' => 'Color', 'options' => ['Red']], ['name' => 'Size', 'options' => ['S']], ['name' => 'Material', 'options' => ['Abaca', 'Buri']]];
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([], ['variation_types' => $types]))
            ->assertSessionHasErrors(['variation_types' => 'A product can have at most 2 variation types (for example Color and Size).']);

        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([0 => ['name' => 'Red / S / Abaca']], ['variation_types' => null]))
            ->assertSessionHasErrors(['variations' => 'A product can have at most 2 variation types (for example Color and Size).']);
        $this->assertFalse(Product::where('title', 'Abaca Tee')->exists());
    }

    /** SEL-VAR-003 (A7) */
    public function test_sel_var_003_options_per_type_limit(): void
    {
        $colors = fn ($n) => array_map(fn ($i) => "Color {$i}", range(1, $n));
        $rows = fn ($n) => array_map(fn ($c) => ['name' => $c, 'price' => 199, 'stock' => 1], $colors($n));

        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->productForm(['title' => 'Twenty colors',
            'variation_types' => [['name' => 'Color', 'options' => $colors(20)]], 'variations' => $rows(20)]))->assertSessionHasNoErrors();
        $this->assertSame(20, Product::where('title', 'Twenty colors')->sole()->variations()->count());

        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->productForm(['title' => 'Twenty-one colors',
            'variation_types' => [['name' => 'Color', 'options' => $colors(21)]], 'variations' => $rows(21)]))
            ->assertSessionHasErrors(['variation_types.0.options' => 'A variation type can have at most 20 options.']);
    }

    /** SEL-VAR-004 */
    public function test_sel_var_004_duplicate_or_empty_options(): void
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
        $this->assertFalse(Product::where('title', 'Abaca Tee')->exists());
    }

    /** SEL-VAR-005 */
    public function test_sel_var_005_missing_price_or_stock_for_a_variant(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([5 => ['price' => '']]))
            ->assertSessionHasErrors(['variations.5.price' => 'Enter a price for every variant.']);
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([5 => ['stock' => '']]))
            ->assertSessionHasErrors(['variations.5.stock' => 'Enter the stock for every variant.']);
        $this->assertFalse(Product::where('title', 'Abaca Tee')->exists());
        // The empty row is highlighted on the form.
        $this->actingAs($this->seller1)->get(route('seller.books.create'))->assertSee('rowMissing(v)', false);
    }

    /** SEL-VAR-006 */
    public function test_sel_var_006_price_range_on_product_page_and_card(): void
    {
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertOk()->assertSee('₱199.00 – ₱299.00');
        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Abaca Tote']))->assertSee('₱199 – ₱299');
        // Choosing a variant shows that variant's single price (from the variant list the page script uses).
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertSee('"name":"Natural \/ M"', false)->assertSee('"price":249', false);
    }

    /** SEL-VAR-007 */
    public function test_sel_var_007_variant_image_and_sku(): void
    {
        $form = $this->editForm($this->p01);
        foreach ($form['variations'] as $i => $row) {
            $form['variations'][$i]['sku'] = 'MCH-TOTE-' . strtoupper(str_replace(' / ', '-', $row['name']));
            $form['variations'][$i]['image'] = $this->jpg(str_starts_with($row['name'], 'Natural') ? 'natural.jpg' : 'black.jpg', 200);
        }
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $form)->assertSessionHasNoErrors();
        $this->assertSame(6, $this->p01->variations()->whereNotNull('image_path')->count());

        $html = $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->getContent();
        $natural = $this->variation($this->p01, 'Natural / S')->image_path;
        $black   = $this->variation($this->p01, 'Black / S')->image_path;
        $this->assertNotSame($natural, $black);
        $this->assertStringContainsString(str_replace('/', '\/', $natural), $html);
        $this->assertStringContainsString(str_replace('/', '\/', $black), $html);

        // A SKU already used in the store is rejected.
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([0 => ['sku' => 'MCH-TOTE-NATURAL-S']]))
            ->assertSessionHasErrors(['variations' => 'SKU MCH-TOTE-NATURAL-S is already used by another product in your store.']);
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->variantForm([1 => ['sku' => 'MCH-TEE-RED-S']]))
            ->assertSessionHasErrors(['variations.1.sku' => 'Each variant needs a different SKU.']);
    }

    /** SEL-VAR-008 */
    public function test_sel_var_008_variant_with_zero_stock(): void
    {
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertOk()
            ->assertSee('"name":"Black \/ M"', false)->assertSee('"stock":0', false);
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p01->id), ['variation_id' => $this->variation($this->p01, 'Black / M')->id, 'quantity' => 1])
            ->assertSessionHas('error', '"Black / M" is out of stock.');
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p01->id), ['variation_id' => $this->variation($this->p01, 'Black / L')->id, 'quantity' => 1])
            ->assertSessionHas('success');
    }

    /** SEL-VAR-009 */
    public function test_sel_var_009_change_options_after_orders_exist(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p01, 1, 'Natural / M')])->first();

        $form = $this->editForm($this->p01);
        foreach ($form['variations'] as $i => $row) {
            $form['variations'][$i]['name'] = str_replace('Natural', 'Beige', $row['name']);
        }
        $form['variations'][] = ['name' => 'Brown / M', 'price' => 259, 'stock' => 4];
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $form)->assertSessionHasNoErrors();

        $item = OrderItem::where('order_id', $order->id)->sole();
        $this->assertSame('Natural / M', $item->variation_name);
        $this->assertEquals(249, $item->price);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('Natural / M');
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Natural / M')->assertSee('249.00');
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertSee('Beige')->assertSee('Brown');
    }

    /** SEL-VAR-010 */
    public function test_sel_var_010_remove_a_variant_that_is_in_a_cart(): void
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
}
