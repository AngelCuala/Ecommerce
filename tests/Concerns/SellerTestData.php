<?php

namespace Tests\Concerns;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\StoreCategory;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The "Test Data" sheet of the Seller Modules (S1–S12) black-box workbook. It shares the
 * accounts, products and vouchers of the buyer workbook and adds Seller 3 (Pending Approval)
 * and Seller 1's store categories CAT-1…CAT-5.
 */
trait SellerTestData
{
    use BuyerTestData;

    protected User $seller3;
    protected StoreCategory $cat1, $cat2, $cat3, $cat4, $cat5;

    protected function seedSellerTestData(): void
    {
        Storage::fake('public');
        $this->seedBuyerTestData();

        $this->seller3 = $this->account('seller', 'seller.test01@example.com', 'Lola Test', 'Tindahan@2026');
        $this->seller3->update(['approval_status' => 'pending']);
        SellerApplication::create(['user_id' => $this->seller3->id, 'full_name' => 'Lola Test', 'shop_name' => 'Tindahan ni Lola',
            'phone' => '09170000003', 'address' => 'Quezon City', 'government_id_path' => 'ids/x.jpg', 'status' => 'pending']);

        // Seller 1's pickup address (Commonwealth, Quezon City).
        $this->seller1->sellerApplication->update(['region' => 'NCR', 'barangay' => 'Commonwealth', 'street' => '12 Tandang Sora Ave.',
            'zip_code' => '1121', 'address' => '12 Tandang Sora Ave., Commonwealth, Quezon City, Metro Manila, 1121']);

        $s1 = $this->seller1->id;
        $this->cat1 = StoreCategory::create(['seller_id' => $s1, 'name' => 'Bags', 'sort_order' => 1]);
        $this->cat2 = StoreCategory::create(['seller_id' => $s1, 'name' => 'Home Decor', 'sort_order' => 2]);
        $this->cat3 = StoreCategory::create(['seller_id' => $s1, 'name' => 'Lamps', 'parent_id' => $this->cat2->id, 'sort_order' => 1]);
        $this->cat4 = StoreCategory::create(['seller_id' => $s1, 'name' => 'Baskets', 'parent_id' => $this->cat2->id, 'sort_order' => 2]);
        $this->cat5 = StoreCategory::create(['seller_id' => $s1, 'name' => 'Kitchen & Dining', 'sort_order' => 3]);
        $this->p01->storeCategories()->attach($this->cat1);
        $this->p02->storeCategories()->attach($this->cat4);
        $this->p03->storeCategories()->attach($this->cat3);
    }

    protected function jpg(string $name = 'product_front.jpg', int $kb = 1200): UploadedFile
    {
        return UploadedFile::fake()->image($name, 40, 40)->size($kb);
    }

    /** A valid "Add Product" form (SEL-PROD-001 test data), published. */
    protected function productForm(array $over = []): array
    {
        return array_merge([
            'title' => 'Buri Placemat Set', 'description' => 'Set of 6, handwoven buri',
            'category_id' => $this->homeLiving->id, 'brand' => 'No Brand', 'condition' => 'New',
            'price' => '299.00', 'stock' => '30', 'weight_kg' => '0.6', 'length_cm' => '35', 'width_cm' => '25', 'height_cm' => '3',
            'images' => [$this->jpg(), $this->jpg('product_side.png', 2400)],
            'status' => 'active', 'action' => 'publish',
            // The SQLite test schema still has these NOT NULL (MySQL relaxed them in a later migration).
            'language' => 'English', 'format' => 'Standard',
        ], $over);
    }

    /** Edit form for an existing product, keeping its data unless overridden. */
    protected function editForm(Product $p, array $over = []): array
    {
        $p->load('variations');
        return array_merge([
            'title' => $p->title, 'description' => $p->description ?: 'A sturdy handmade item, made to last for years.',
            'category_id' => $p->category_id, 'price' => $p->price, 'stock' => $p->stock,
            'weight_kg' => $p->weight_kg ?: 0.5, 'status' => 'active', 'action' => 'publish',
            'discount_percent' => $p->discount_percent > 0 ? $p->discount_percent : '',
            'images' => $p->images()->exists() ? [] : [$this->jpg()],
            'variations' => $p->variations->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'stock' => $v->stock, 'sku' => $v->sku])->all(),
            'language' => 'English', 'format' => 'Standard',
        ], $over);
    }

    /** Place a COD (or other) order for these cart lines; returns the new orders. */
    protected function placeOrder(User $buyer, array $lines, ?UserAddress $address = null, array $extra = [])
    {
        $before = Order::max('id') ?? 0;
        $this->openCheckout($buyer, $lines);
        $this->actingAs($buyer)->post(route('checkout.store'), $this->checkoutForm($address ?? ($buyer->is($this->buyer1) ? $this->addr1 : $this->addr3), $extra))
            ->assertSessionHasNoErrors();

        return Order::where('id', '>', $before)->orderBy('id')->get();
    }

    /** Move an order along the status flow (as logistics would). */
    protected function moveTo(Order $order, string $status): Order
    {
        foreach ([Order::PROCESSING, Order::SHIPPED, Order::DELIVERED, Order::COMPLETED] as $step) {
            if ($order->status === $status) {
                break;
            }
            $order->update(['status' => $step] + ($step === Order::PROCESSING ? ['accepted_at' => now()] : []));
        }
        return $order->fresh();
    }
}
