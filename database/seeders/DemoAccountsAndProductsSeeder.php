<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\LogisticsHub;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\Rider;
use App\Models\SellerApplication;
use App\Models\SortingCenterApplication;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A small working marketplace for local testing, in Laguna (the delivery area):
 * 1 admin, 6 sellers (6 products each, most with variations), 4 buyers,
 * 6 logistics companies (each with a hub and barangay coverage: Calamba, Santa Rosa, Biñan, Los Baños, Luisiana, Santa Cruz),
 * 12 riders, two per company: a motorcycle or tricycle (pickups and deliveries to buyers) and a 4-wheel truck (hub to hub).
 *
 *   php artisan db:seed --class=DemoAccountsAndProductsSeeder
 *
 * Deletes nothing and is safe to run again: accounts are matched by email, products by product code.
 * Logins are <role><n>@alvy.test (admin@alvy.test for the admin) with the passwords below.
 * 4-wheel riders: courier<n>@alvy.test / Courier@2026 (SortingCenterCouriersSeeder).
 */
class DemoAccountsAndProductsSeeder extends Seeder
{
    public const PASSWORDS = [
        'admin' => 'Admin@2026', 'seller' => 'Seller@2026', 'buyer' => 'Buyer@2026', 'logistics' => 'Logistics@2026', 'rider' => 'Rider@2026',
    ];

    /** [business name, contact person, town, hub code, lat, lng] */
    private const LOGISTICS = [
        ['Calamba Express Logistics', 'Ramon Dizon', 'City of Calamba', 'CAL', 14.2117, 121.1653],
        ['Santa Rosa Swift Cargo', 'Liza Fernandez', 'City of Santa Rosa', 'STR', 14.3122, 121.1114],
        ['Biñan Bilis Hatid', 'Carlos Mendoza', 'City of Biñan', 'BIN', 14.3424, 121.0806],
        ['Los Baños Campus Couriers', 'Miguel Navarro', 'Los Baños', 'LSB', 14.1699, 121.2441],
        ['Luisiana Highland Logistics', 'Teresa Alcantara', 'Luisiana', 'LUI', 14.1853, 121.5108],
        ['Santa Cruz Lakeshore Logistics', 'Antonio Ilagan', 'Santa Cruz', 'SCZ', 14.2814, 121.4161],
    ];

    /** [name, vehicle, plate] — one rider per logistics company, same order. */
    private const RIDERS = [
        ['Juan Carlo Reyes', 'Motorcycle', 'NAB1234'],
        ['Mark Anthony Santos', 'Motorcycle', 'NCD5678'],
        ['Ronald Garcia', 'Tricycle', 'NGH3456'],
        ['Kevin Mendoza', 'Motorcycle', 'NIJ7890'],
        ['Paolo Dela Rosa', 'Motorcycle', 'NKL2345'],
        ['Jericho Manalo', 'Motorcycle', 'NMN6789'],
    ];

    /** [name, town] */
    private const BUYERS = [
        ['Maria Angelica Santos', 'City of Calamba'],
        ['Jose Rafael Reyes', 'Los Baños'],
        ['Kristine Joy Valdez', 'Luisiana'],
        ['Mark Joseph Aguilar', 'Santa Cruz'],
    ];

    /** Photo key => file under public/images. */
    private const PHOTOS = [
        'dress' => 'categories/womens-apparel.jpg', 'sweater' => 'photo5.png', 'polo' => 'categories/mens-apparel.jpg',
        'boots' => 'photo6.png', 'coats' => 'photo4.png', 'outfit' => 'photo8.png',
        'headphones' => 'categories/electronics-and-gadgets.jpg', 'watch' => 'categories/jewelry-and-watches.jpg',
        'controller' => 'categories/gaming-and-consoles.jpg',
        'lipstick' => 'lipstick.png', 'eyeliner' => 'eyeliner.png', 'blush' => 'blush powder.png', 'browgel' => 'eyebrow gel.png',
        'brush' => 'makeup brush.png', 'skincare' => 'categories/health-and-beauty.jpg',
        'sneakers' => 'categories/shoes-and-footwear.jpg', 'dumbbells' => 'categories/sports-and-outdoors.jpg',
        'helmet' => 'categories/automotive-and-motorcycle.jpg', 'bag' => 'categories/bags-and-luggage.jpg',
        'home' => 'categories/home-and-garden.jpg', 'food' => 'categories/food-and-gourmet.jpg',
        'furniture' => 'categories/furniture-and-office.jpg', 'appliance' => 'categories/home-appliances.jpg',
        'kids' => 'categories/kids-and-baby.jpg',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('DemoAccountsAndProductsSeeder is not for production: its passwords are public.');
        }

        $this->call(CatalogCategoriesSeeder::class);

        $laguna = json_decode(file_get_contents(database_path('seeders/data/laguna.json')), true);
        $towns = collect($laguna['towns'])->keyBy('name');
        $prov = $laguna['province'];

        DB::transaction(function () use ($towns, $prov) {
            $admin = $this->user('admin', 'admin@alvy.test', 'ALVY Admin', self::PASSWORDS['admin'], ['phone' => $this->phone(1)]);
            $this->logisticsAndRiders($admin, $towns, $prov);
            $this->sellers($towns, $prov);
            $this->buyers($towns, $prov);
        });

        $this->call(SortingCenterCouriersSeeder::class);   // a truck courier for each logistics company

        $this->command?->info('Demo accounts ready: 1 admin, ' . count($this->shops()) . ' sellers, ' . count(self::BUYERS) . ' buyers, ' . count(self::LOGISTICS) . ' logistics, ' . count(self::RIDERS) . ' delivery riders, ' . count(self::LOGISTICS) . ' 4-wheel riders.');
        $this->command?->info(Product::published()->count() . ' products visible in the shop, '
            . ProductVariation::count() . ' variations.');
    }

    private function logisticsAndRiders(User $admin, $towns, array $prov): void
    {
        foreach (self::LOGISTICS as $i => [$business, $person, $townName, $hubCode, $lat, $lng]) {
            $n = $i + 1;
            $town = $towns[$townName];
            $brgy = $town['barangays'][0];
            [$first, $last] = $this->splitName($person);
            $phone = $this->phone(100 + $n);
            $address = (100 + $n) . ' National Highway, ' . $brgy['name'] . ', ' . $townName . ', Laguna';

            $company = $this->user('sorting_center', "logistics{$n}@alvy.test", $person, self::PASSWORDS['logistics'], [
                'first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'contact_no' => $phone,
                'province' => $prov['name'], 'municipality' => $townName, 'barangay' => $brgy['name'],
                'assigned_municipality' => $townName, 'assigned_municipality_code' => $town['code'],
                'assigned_province' => $prov['name'], 'assigned_province_code' => $prov['code'],
            ]);

            $this->upsert(SortingCenterApplication::class, ['user_id' => $company->id], [
                'business_name' => $business, 'status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now(),
                'first_name' => $first, 'last_name' => $last, 'sex' => $n % 2 ? 'Male' : 'Female', 'birthday' => '1988-05-1' . $n,
                'age' => 38, 'contact_no' => $phone,
                'region' => $prov['region'], 'region_code' => $prov['region_code'], 'province' => $prov['name'], 'province_code' => $prov['code'],
                'municipality' => $townName, 'municipality_code' => $town['code'], 'barangay' => $brgy['name'], 'barangay_code' => $brgy['code'],
                'street' => 'National Highway', 'house_number' => (string) (100 + $n), 'zip_code' => '4000', 'address' => $address,
                'government_id_path' => 'ids/sample-id.jpg', 'business_permit_path' => 'permits/sample-permit.pdf',
            ]);

            $firstArea = null;
            foreach ($town['barangays'] as $j => $b) {
                $area = $this->upsert(DeliveryArea::class, ['code' => $hubCode . '-' . str_pad((string) ($j + 1), 2, '0', STR_PAD_LEFT)], [
                    'sorting_center_id' => $company->id, 'name' => $b['name'], 'municipality' => $townName,
                    'region_code' => $prov['region_code'], 'province_code' => $prov['code'],
                    'municipality_code' => $town['code'], 'barangay_code' => $b['code'],
                ]);
                $firstArea ??= $area;
            }

            $this->upsert(LogisticsHub::class, ['code' => 'HUB-' . $hubCode], [
                'sorting_center_id' => $company->id, 'name' => $business . ' Hub', 'address' => $address, 'latitude' => $lat, 'longitude' => $lng,
            ]);

            [$riderName, $vehicle, $plate] = self::RIDERS[$i];
            [$rFirst, $rLast] = $this->splitName($riderName);
            $riderPhone = $this->phone(200 + $n);
            $riderUser = $this->user('courier', "rider{$n}@alvy.test", $riderName, self::PASSWORDS['rider'], [
                'first_name' => $rFirst, 'last_name' => $rLast, 'phone' => $riderPhone,
            ]);
            $this->upsert(Rider::class, ['user_id' => $riderUser->id], [
                'sorting_center_id' => $company->id, 'full_name' => $riderName, 'phone' => $riderPhone,
                'vehicle_type' => $vehicle, 'plate_number' => $plate, 'license_number' => sprintf('N%02d-26-%06d', $n, 100000 + $n),
                'license_expiry' => now()->addYears(3)->toDateString(), 'area_id' => $firstArea->id,
                'region_code' => $prov['region_code'], 'province_code' => $prov['code'],
                'municipality_code' => $town['code'], 'barangay_code' => $brgy['code'],
                'application_status' => 'approved', 'approved_at' => now(), 'approved_by' => $company->id,
                'is_active' => true, 'can_transport_transfers' => true,
            ]);
        }
    }

    private function sellers($towns, array $prov): void
    {
        $categories = Category::pluck('id', 'name');

        foreach ($this->shops() as $i => [$shop, $owner, $townName, $products]) {
            $n = $i + 1;
            $town = $towns[$townName];
            $brgy = $town['barangays'][1];
            [$first, $last] = $this->splitName($owner);
            $phone = $this->phone(300 + $n);
            $street = (10 + $n) . ' Rizal Street';

            $seller = $this->user('seller', "seller{$n}@alvy.test", $owner, self::PASSWORDS['seller'], [
                'first_name' => $first, 'last_name' => $last, 'phone' => $phone,
            ]);
            $this->upsert(SellerApplication::class, ['user_id' => $seller->id], [
                'full_name' => $owner, 'first_name' => $first, 'last_name' => $last,
                'shop_name' => $shop, 'business_name' => $shop, 'seller_type' => 'Individual', 'phone' => $phone,
                'region' => $prov['region'], 'province' => $prov['name'], 'province_code' => $prov['code'],
                'municipality' => $townName, 'municipality_code' => $town['code'], 'barangay' => $brgy['name'],
                'street' => $street, 'zip_code' => '4000',
                'address' => "{$street}, {$brgy['name']}, {$townName}, Laguna, 4000",
                'description' => "{$shop} — ships fast from {$townName}, Laguna.",
                'government_id_path' => 'ids/sample-id.jpg', 'status' => 'approved',
            ]);
            $address = $this->address($seller, 'Shop', $owner, $phone, $street, $brgy, $townName, $town, $prov);

            foreach ($products as [$code, $title, $category, $brand, $price, $discount, $kg, $photo, $desc, $labels, $options]) {
                $image = $this->photo($photo);
                $stock = is_array($options) ? array_sum(array_column($options, 2)) : $options;

                $product = $this->upsert(Product::class, ['product_code' => $code], [
                    'seller_id' => $seller->id, 'category_id' => $categories[$category], 'title' => $title, 'brand' => $brand,
                    'price' => $price, 'discount_percent' => $discount, 'stock' => $stock, 'sku' => $code, 'weight_kg' => $kg,
                    'description' => $desc, 'image' => $image, 'address_id' => $address->id, 'condition' => 'New',
                    'availability' => 'in_stock', 'status' => 'active', 'approval_status' => 'approved', 'is_archived' => false,
                    'variation_labels' => $labels,
                ]);
                ProductImage::updateOrCreate(['product_id' => $product->id, 'path' => $image], ['sort_order' => 0]);

                // Product::priceRange() skips options without a price, so when any option has its
                // own price they all need one for the page to show the true range.
                $options = is_array($options) ? $options : [];
                $anyOwnPrice = array_filter(array_column($options, 1)) !== [];
                foreach ($options as $k => [$name, $ownPrice, $optionStock]) {
                    $this->upsert(ProductVariation::class, ['product_id' => $product->id, 'name' => $name], [
                        'price' => $ownPrice ?? ($anyOwnPrice ? $price : null),
                        'stock' => $optionStock, 'sku' => $code . '-' . ($k + 1), 'sort_order' => $k,
                    ]);
                }
            }
        }
    }

    private function buyers($towns, array $prov): void
    {
        foreach (self::BUYERS as $i => [$name, $townName]) {
            $n = $i + 1;
            $town = $towns[$townName];
            $brgy = $town['barangays'][2];
            [$first, $last] = $this->splitName($name);
            $phone = $this->phone(400 + $n);

            $buyer = $this->user('buyer', "buyer{$n}@alvy.test", $name, self::PASSWORDS['buyer'], [
                'first_name' => $first, 'last_name' => $last, 'phone' => $phone,
            ]);
            $this->address($buyer, 'Home', $name, $phone, (20 + $n) . ' Mabini Street', $brgy, $townName, $town, $prov);
        }
    }

    /**
     * Each option: [name, own price or null to use the product's price, stock].
     * Two-type options are named "Color / Size" and listed with labels "Color, Size".
     */
    private function shops(): array
    {
        return [
            ['Bella Moda Boutique', 'Isabel Cruz', 'City of Calamba', [
                ['BM-01', 'Floral Wrap Midi Dress', "Women's Apparel", 'Bella', 799, 10, 0.35, 'dress', 'Lightweight wrap dress with an adjustable waist tie. Machine washable.',
                    'Color, Size', $this->grid(['Rose', 'Navy'], ['S', 'M', 'L'])],
                ['BM-02', 'Oversized Knit Sweater', "Women's Apparel", 'Bella', 1199, 0, 0.5, 'sweater', 'Cozy chunky knit with dropped shoulders.',
                    'Color', [['Oatmeal', null, 18], ['Charcoal', null, 12], ['Dusty Pink', null, 0]]],
                ['BM-03', 'Classic Cotton Polo Shirt', "Men's Apparel", 'Bella Men', 499, 0, 0.3, 'polo', 'Breathable cotton piqué polo for work and weekends.',
                    'Color, Size', $this->grid(['Navy', 'White', 'Maroon'], ['M', 'L', 'XL'], ['XL' => 549])],
                ['BM-04', 'Leather Ankle Boots', 'Shoes & Footwear', 'Bella', 2299, 0, 1.1, 'boots', 'Ankle boots with a block heel and side zipper.',
                    'Size', [['US 6', null, 6], ['US 7', null, 9], ['US 8', null, 7], ['US 9', null, 3]]],
                ['BM-05', 'Neutral Weekend Outfit Set', "Women's Apparel", 'Bella', 1599, 15, 0.6, 'outfit', 'Relaxed top and wide-leg trousers in soft neutral tones.',
                    'Size', [['S', null, 10], ['M', null, 14], ['L', null, 8]]],
                ['BM-06', 'Wool-Blend Winter Coat', "Women's Apparel", 'Bella', 2999, 0, 1.4, 'coats', 'Warm wool-blend coat for travel and cold weather. One size.', null, 10],
            ]],
            ['Techno Laguna', 'Rodrigo Lim', 'City of Santa Rosa', [
                ['TL-01', 'Wireless Over-Ear Headphones', 'Electronics & Gadgets', 'SoundMax', 1499, 10, 0.4, 'headphones', 'Bluetooth 5.3 headphones with a 40-hour battery and soft ear cushions.',
                    'Color', [['Black', null, 20], ['White', null, 15], ['Blue', 1599, 6]]],
                ['TL-02', 'Smart Watch Fitness Tracker', 'Electronics & Gadgets', 'FitTime', 1999, 0, 0.12, 'watch', 'Heart rate, sleep and step tracking with a 7-day battery.',
                    'Color, Strap', $this->grid(['Silver', 'Black'], ['Silicone', 'Steel'], ['Steel' => 2399])],
                ['TL-03', 'Wireless Game Controller', 'Gaming & Consoles', 'PlayPro', 1299, 0, 0.3, 'controller', 'Ergonomic rechargeable controller with dual vibration. Works with PC and Android.',
                    'Color', [['Black', null, 14], ['White', null, 11], ['Red', null, 0]]],
                ['TL-04', 'Gaming Headset with Mic', 'Gaming & Consoles', 'SoundMax', 1199, 0, 0.4, 'headphones', 'Surround sound headset with a noise-cancelling mic.',
                    'Edition', [['Standard', null, 16], ['RGB', 1399, 10], ['RGB Wireless', 1899, 5]]],
                ['TL-05', 'Bluetooth Neckband Earphones', 'Electronics & Gadgets', 'SoundMax', 649, 0, 0.1, 'headphones', 'Magnetic earbuds with a 20-hour battery and quick charge.',
                    'Color', [['Black', null, 30], ['Gray', null, 22]]],
                ['TL-06', 'Pro Gaming Controller (Elite)', 'Gaming & Consoles', 'PlayPro', 2499, 5, 0.35, 'controller', 'Swappable thumbsticks, back paddles and a 40-hour battery.', null, 12],
            ]],
            ['Ganda Beauty Hub', 'Maricel Santos', 'City of Biñan', [
                ['GB-01', 'Velvet Matte Lipstick', 'Health & Beauty', 'Ganda', 299, 0, 0.05, 'lipstick', 'Long-wearing matte lipstick that resists transfer.',
                    'Shade', [['Rosewood', null, 40], ['Brick Red', null, 30], ['Nude Pink', null, 0], ['Berry', 329, 25]]],
                ['GB-02', 'Waterproof Liquid Eyeliner', 'Health & Beauty', 'Ganda', 249, 0, 0.03, 'eyeliner', 'Ultra-fine tip with a smudge-proof, waterproof formula.',
                    'Color', [['Jet Black', null, 50], ['Brown', null, 35]]],
                ['GB-03', 'Silky Blush Powder', 'Health & Beauty', 'Ganda', 379, 10, 0.08, 'blush', 'Buildable blush with a natural satin finish.',
                    'Shade', [['Peach', null, 28], ['Coral', null, 22], ['Mauve', null, 16]]],
                ['GB-04', 'Clear Eyebrow Gel', 'Health & Beauty', 'Ganda', 199, 0, 0.03, 'browgel', 'Sets brows all day without flaking.', null, 60],
                ['GB-05', 'Makeup Brush Set', 'Health & Beauty', 'Ganda Tools', 449, 0, 0.3, 'brush', 'Soft synthetic brushes for face and eyes, with a travel pouch.',
                    'Set', [['8 pcs', null, 20], ['12 pcs', 599, 18], ['16 pcs', 749, 9]]],
                ['GB-06', 'Daily Skincare Kit', 'Health & Beauty', 'Ganda Skin', 1099, 0, 0.6, 'skincare', 'Cleanser, toner, serum and moisturizer for a simple daily routine.',
                    'Skin Type', [['Oily', null, 12], ['Dry', null, 14], ['Sensitive', 1199, 8]]],
            ]],
            ['Active Life Sports', 'Ernesto Ramos', 'Los Baños', [
                ['AL-01', 'Lightweight Running Sneakers', 'Shoes & Footwear', 'Stride', 1599, 15, 0.8, 'sneakers', 'Breathable knit upper with cushioned soles.',
                    'Color, Size', $this->grid(['White', 'Black'], ['US 8', 'US 9', 'US 10'])],
                ['AL-02', 'Adjustable Dumbbell Pair', 'Sports & Outdoors', 'IronFit', 1499, 0, 5, 'dumbbells', 'Pair of adjustable dumbbells with non-slip handle grips.',
                    'Weight', [['5 kg', null, 12], ['10 kg', 2299, 9], ['20 kg', 3499, 4]]],
                ['AL-03', 'Full-Face Motorcycle Helmet', 'Automotive & Motorcycle', 'RoadGuard', 1899, 0, 1.5, 'helmet', 'Full-face helmet with an anti-fog visor and ventilation.',
                    'Color, Size', $this->grid(['Matte Black', 'Red'], ['M', 'L', 'XL'])],
                ['AL-04', 'Weekender Travel Bag', 'Bags & Luggage', 'Stride', 1799, 10, 0.9, 'bag', 'Roomy weekender with a shoe compartment and trolley sleeve.',
                    'Color', [['Tan', null, 10], ['Black', null, 13]]],
                ['AL-05', 'Resistance Bands Set', 'Sports & Outdoors', 'IronFit', 549, 0, 0.4, 'dumbbells', 'Five latex bands with handles, a door anchor and a carry bag.', null, 45],
                ['AL-06', 'Motorcycle Riding Gloves', 'Automotive & Motorcycle', 'RoadGuard', 449, 0, 0.2, 'helmet', 'Touchscreen-friendly gloves with knuckle protection.',
                    'Size', [['M', null, 18], ['L', null, 20], ['XL', null, 7]]],
            ]],
            ['Luisiana Pandan Crafts', 'Lourdes Javier', 'Luisiana', [
                ['LH-01', 'Handwoven Pandan Tote Bag', 'Bags & Luggage', 'Luisiana Weaves', 449, 0, 0.4, 'bag', 'Sturdy tote woven by hand from Luisiana pandan leaves, with a cotton lining.',
                    'Size', [['Small', null, 15], ['Medium', 499, 12], ['Large', 599, 8]]],
                ['LH-02', 'Pandan Placemat Set', 'Home & Garden', 'Luisiana Weaves', 349, 0, 0.5, 'home', 'Four woven placemats in natural pandan, easy to wipe clean.',
                    'Color', [['Natural', null, 20], ['Two-Tone', null, 14]]],
                ['LH-03', 'Woven Storage Basket', 'Home & Garden', 'Luisiana Weaves', 399, 10, 0.6, 'home', 'Lidded pandan basket for laundry, toys or pantry storage.',
                    'Size', [['Small', null, 18], ['Large', 549, 9]]],
                ['LH-04', 'Pandan Banig Sleeping Mat', 'Furniture & Office', 'Luisiana Weaves', 899, 0, 1.2, 'furniture', 'Traditional woven banig, cool to sleep on in warm weather.',
                    'Size', [['Single', null, 10], ['Double', 1199, 6], ['Queen', 1499, 0]]],
                ['LH-05', 'Woven Pandan Hat', "Men's Apparel", 'Luisiana Weaves', 249, 0, 0.2, 'home', 'Wide-brim hat woven from pandan for sun and farm work.', null, 30],
                ['LH-06', 'Laguna Buko Pie Box', 'Food & Gourmet', 'Luisiana Pasalubong', 380, 0, 1, 'food', 'Freshly baked buko pie with a flaky crust. Best eaten within 3 days.', null, 25],
            ]],
            ['Sta. Cruz Home & Kesong Puti', 'Ramil Gutierrez', 'Santa Cruz', [
                ['SK-01', 'Fresh Kesong Puti', 'Food & Gourmet', 'Sta. Cruz Dairy', 120, 0, 0.25, 'food', 'Soft white carabao-milk cheese wrapped in banana leaf. Keep chilled.',
                    'Pack', [['1 piece', null, 40], ['3 pieces', 330, 20], ['6 pieces', 630, 10]]],
                ['SK-02', 'Electric Rice Cooker 1.8L', 'Home Appliances', 'KusinaPro', 1499, 10, 2.5, 'appliance', 'Cooks up to 10 cups with a keep-warm mode and non-stick pot.',
                    'Color', [['White', null, 12], ['Red', null, 8]]],
                ['SK-03', 'Stand Fan 16-inch', 'Home Appliances', 'KusinaPro', 1299, 0, 4, 'appliance', 'Three speeds, oscillation and an adjustable height.',
                    'Color', [['White', null, 10], ['Black', null, 6]]],
                ['SK-04', 'Stainless Cookware Set', 'Home & Garden', 'KusinaPro', 1899, 5, 3.2, 'home', 'Pots and pans with glass lids, for gas and induction stoves.',
                    'Set', [['5 pcs', null, 9], ['8 pcs', 2599, 5]]],
                ['SK-05', 'Baby Cotton Onesie Pack', 'Kids & Baby', 'Munting Anghel', 549, 0, 0.3, 'kids', 'Soft cotton onesies with snap buttons, pack of three.',
                    'Size', [['0-3 months', null, 15], ['3-6 months', null, 12], ['6-12 months', null, 9]]],
                ['SK-06', 'Kids Wooden Puzzle Set', 'Kids & Baby', 'Munting Anghel', 399, 0, 0.6, 'kids', 'Three chunky wooden puzzles for ages 2 and up.', null, 20],
            ]],
        ];
    }

    /** Every "A / B" combination, with varied stock; the last one is sold out so that state can be seen. */
    private function grid(array $first, array $second, array $prices = []): array
    {
        $rows = [];
        foreach ($first as $a) {
            foreach ($second as $b) {
                $rows[] = ["{$a} / {$b}", $prices[$b] ?? null, 4 + (count($rows) * 7) % 19];
            }
        }
        $rows[array_key_last($rows)][2] = 0;

        return $rows;
    }

    private function user(string $role, string $email, string $name, string $password, array $extra = []): User
    {
        return $this->upsert(User::class, ['email' => $email], $extra + [
            'name' => $name, 'password' => Hash::make($password), 'role' => $role,
            'approval_status' => 'approved', 'is_active' => true, 'email_verified_at' => now(),
        ]);
    }

    private function address(User $user, string $label, string $name, string $phone, string $line, array $brgy, string $townName, array $town, array $prov): UserAddress
    {
        return $this->upsert(UserAddress::class, ['user_id' => $user->id, 'label' => $label], [
            'full_name' => $name, 'phone' => $phone, 'address_line' => $line, 'barangay' => $brgy['name'], 'city' => $townName,
            'province' => $prov['name'], 'region' => $prov['region'], 'zip' => '4000', 'country' => 'Philippines',
            'province_code' => $prov['code'], 'municipality_code' => $town['code'], 'barangay_code' => $brgy['code'], 'is_default' => true,
        ]);
    }

    /** Create or update, including columns outside the model's fillable list. */
    private function upsert(string $model, array $key, array $values): Model
    {
        $record = $model::firstOrNew($key);
        $record->forceFill($values)->save();

        return $record;
    }

    /** Copy a photo from public/images into the public upload folder (once) and return its path. */
    private function photo(string $key): string
    {
        $source = public_path('images/' . self::PHOTOS[$key]);
        $path = 'products/demo/' . $key . '.' . pathinfo($source, PATHINFO_EXTENSION);

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, file_get_contents($source));
        }

        return $path;
    }

    private function splitName(string $name): array
    {
        return [Str::beforeLast($name, ' '), Str::afterLast($name, ' ')];
    }

    private function phone(int $n): string
    {
        return '0917' . str_pad((string) (1000000 + $n), 7, '0', STR_PAD_LEFT);
    }
}
