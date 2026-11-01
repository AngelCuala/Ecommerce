<?php

namespace Tests\Concerns;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SellerApplication;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Voucher;
use Illuminate\Support\Facades\Hash;

/**
 * The "Test Data" sheet of the Buyer Modules (B1–B14) black-box workbook: accounts, addresses,
 * products P-01…P-07 and vouchers MCH50…FREESHIP, so the automated cases match the manual ones.
 */
trait BuyerTestData
{
    use ChecksOut;

    protected User $buyer1, $buyer2, $seller1, $seller2;
    protected UserAddress $addr1, $addr2, $addr3, $addr4;
    protected Product $p01, $p02, $p03, $p04, $p05, $p06, $p07;
    protected Category $homeLiving, $gadgets;

    protected function seedBuyerTestData(): void
    {
        config(['marketplace.commission_rate' => 5]);   // assumption A9

        $this->buyer1  = $this->account('buyer', 'juanmiguel.delacruz@example.com', 'Juan Miguel Dela Cruz', 'Mamimili@2026');
        $this->buyer2  = $this->account('buyer', 'anamarie.reyes@example.com', 'Ana Marie Reyes', 'Suki#2026Ana');
        $this->seller1 = $this->account('seller', 'mariaclara.santos@example.com', 'Maria Clara Santos', 'Tindahan@2026');
        $this->seller2 = $this->account('seller', 'roberto.villanueva@example.com', 'Roberto Villanueva', 'Gadget#Hub26');
        $this->shop($this->seller1, 'Maria Clara Handicrafts', 'Metro Manila', 'Quezon City', self::QUEZON_CITY, null);
        $this->shop($this->seller2, 'RV Gadget Hub', 'Cebu', 'Cebu City', self::CEBU_CITY, '072200000');

        $this->addr1 = $this->addressFor($this->buyer1, self::QUEZON_CITY, ['label' => 'Home', 'address_line' => '45 Kalayaan Ave.', 'barangay' => 'Central']);
        $this->addr2 = $this->addressFor($this->buyer1, self::QUEZON_CITY, ['label' => 'Office', 'address_line' => '12F Pacific Tower, 6750 Ayala Ave.']);
        $this->addr3 = $this->addressFor($this->buyer2, self::CEBU_CITY, ['label' => 'Home', 'address_line' => '78 Osmeña Blvd.', 'phone' => '09173456789']);
        $this->addr4 = $this->addressFor($this->buyer1, self::ITBAYAT, ['label' => 'Province', 'address_line' => 'Purok 2, Brgy. Raele']);

        $this->homeLiving = Category::create(['name' => 'Home & Garden']);
        $bags             = Category::create(['name' => 'Bags & Luggage']);
        $this->gadgets    = Category::create(['name' => 'Electronics & Gadgets']);

        $this->p01 = $this->product($this->seller1, $bags, 'Abaca Woven Tote Bag', 199, 36, 0.4, ['variation_labels' => 'Color, Size']);
        foreach ([['Natural / S', 199, 10], ['Natural / M', 249, 8], ['Natural / L', 299, 5],
                  ['Black / S', 199, 10], ['Black / M', 249, 0], ['Black / L', 299, 3]] as $i => [$name, $price, $stock]) {
            ProductVariation::create(['product_id' => $this->p01->id, 'name' => $name, 'price' => $price, 'stock' => $stock, 'sort_order' => $i]);
        }
        $this->p02 = $this->product($this->seller1, $this->homeLiving, 'Rattan Fruit Basket', 450, 20, 0.8, ['subcategory' => 'Baskets']);
        $this->p03 = $this->product($this->seller1, $this->homeLiving, 'Capiz Shell Lamp', 1250, 1, 1.5, ['subcategory' => 'Lamps']);
        $this->p04 = $this->product($this->seller1, $this->homeLiving, 'Banig Placemat Set of 4', 350, 0, 0.5);
        $this->p05 = $this->product($this->seller2, $this->gadgets, 'USB-C Fast Charger 20W', 399, 50, 0.2);
        $this->p06 = $this->product($this->seller2, $this->gadgets, 'Wireless Earbuds X2', 1499, 20, 0.3);
        foreach ([['Black', 10], ['White', 10]] as $i => [$name, $stock]) {
            ProductVariation::create(['product_id' => $this->p06->id, 'name' => $name, 'price' => 1499, 'stock' => $stock, 'sort_order' => $i]);
        }
        $this->p07 = $this->product($this->seller2, $this->gadgets, 'Power Bank 10000mAh', 899, 5, 0.4, ['purchase_limit' => 2]);

        $s1 = ['scope' => 'seller', 'seller_id' => $this->seller1->id];
        $this->voucher('MCH50', 50, 300, $s1 + ['usage_limit' => 100, 'ends_at' => now()->endOfMonth()]);
        $this->voucher('MCH10', 10, 500, $s1 + ['discount_type' => 'percent', 'max_discount' => 100, 'usage_limit' => 50, 'ends_at' => now()->endOfMonth()]);
        $this->voucher('RVSECRET', 100, 1000, ['scope' => 'seller', 'seller_id' => $this->seller2->id, 'usage_limit' => 20, 'is_claimable' => false]);
        $this->voucher('MCHOLD', 30, 200, $s1 + ['ends_at' => now()->subDay()]);
        $this->voucher('MCHSOON', 30, 200, $s1 + ['starts_at' => now()->addDay()]);
        $this->voucher('MCHMAX', 20, 100, $s1 + ['usage_limit' => 1, 'used_count' => 1]);
        $this->voucher('PLAT100', 100, 1000, ['scope' => 'platform']);
        $this->voucher('FREESHIP', 50, 300, ['scope' => 'shipping']);
    }

    protected function account(string $role, string $email, string $name, string $password = 'Secret@123'): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => $role,
            'approval_status' => 'approved', 'email_verified_at' => now(), 'phone' => '09171234567']);
    }

    private function shop(User $seller, string $name, string $province, string $city, string $cityCode, ?string $provinceCode): void
    {
        SellerApplication::create(['user_id' => $seller->id, 'full_name' => $seller->name, 'shop_name' => $name,
            'phone' => '09170000000', 'address' => $city, 'province' => $province, 'municipality' => $city,
            'municipality_code' => $cityCode, 'province_code' => $provinceCode,
            'government_id_path' => 'ids/x.jpg', 'status' => 'approved']);
    }

    protected function product(User $seller, Category $cat, string $title, float $price, int $stock, float $kg, array $extra = []): Product
    {
        return Product::create($extra + ['category_id' => $cat->id, 'seller_id' => $seller->id, 'title' => $title,
            'price' => $price, 'stock' => $stock, 'weight_kg' => $kg, 'status' => 'active', 'approval_status' => 'approved']);
    }

    protected function voucher(string $code, float $value, float $min, array $extra = []): Voucher
    {
        $v = Voucher::create($extra + ['code' => $code, 'name' => $code, 'discount_type' => 'fixed', 'discount_value' => $value,
            'min_spend' => $min, 'per_user_limit' => 1, 'is_active' => true, 'is_claimable' => true]);
        if (isset($extra['used_count'])) {
            $v->forceFill(['used_count' => $extra['used_count']])->save();
        }
        return $v;
    }

    protected function variation(Product $p, string $name): ProductVariation
    {
        return $p->variations()->where('name', $name)->firstOrFail();
    }

    protected function cart(User $buyer, Product $p, int $qty = 1, ?string $variation = null): CartItem
    {
        return CartItem::create(['user_id' => $buyer->id, 'product_id' => $p->id, 'quantity' => $qty,
            'variation_id' => $variation ? $this->variation($p, $variation)->id : null]);
    }

    /** Tick these cart lines and open checkout (as the cart page does). */
    protected function openCheckout(User $buyer, array $lines, array $query = [])
    {
        return $this->actingAs($buyer)->get(route('checkout.index', $query + ['items' => collect($lines)->pluck('id')->all()]));
    }
}
