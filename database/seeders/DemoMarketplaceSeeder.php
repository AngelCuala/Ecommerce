<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SellerApplication;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Demo shops, products (with photos) and store vouchers so the storefront looks busy:
 *   php artisan db:seed --class=DemoMarketplaceSeeder
 *
 * Safe to run again: shops are matched by email, products by product code (DEMO-…),
 * vouchers by code — nothing is duplicated. Photos come from public/images and are copied
 * into the public upload folder. Demo shop logins: <email> / Seller@123
 */
class DemoMarketplaceSeeder extends Seeder
{
    /** Photo key => file under public/images. */
    private const PHOTOS = [
        'headphones' => 'categories/electronics-and-gadgets.jpg',
        'controller' => 'categories/gaming-and-consoles.jpg',
        'bag'        => 'categories/bags-and-luggage.jpg',
        'chair'      => 'categories/furniture-and-office.jpg',
        'skincare'   => 'categories/health-and-beauty.jpg',
        'plants'     => 'categories/home-and-garden.jpg',
        'coffee'     => 'categories/home-appliances.jpg',
        'watch'      => 'categories/jewelry-and-watches.jpg',
        'toys'       => 'categories/kids-and-baby.jpg',
        'polo'       => 'categories/mens-apparel.jpg',
        'dogbowl'    => 'categories/pet-supplies.jpg',
        'sneakers'   => 'categories/shoes-and-footwear.jpg',
        'dumbbells'  => 'categories/sports-and-outdoors.jpg',
        'stationery' => 'categories/stationery-and-school-supplies.jpg',
        'dress'      => 'categories/womens-apparel.jpg',
        'books'      => 'categories/books-and-media.jpg',
        'spices'     => 'categories/food-and-gourmet.jpg',
        'helmet'     => 'categories/automotive-and-motorcycle.jpg',
        'sunglasses' => 'photo3.png',
        'sweater'    => 'photo5.png',
        'boots'      => 'photo6.png',
        'coats'      => 'photo4.png',
        'outfit'     => 'photo8.png',
        'lipstick'   => 'lipstick.png',
        'eyeliner'   => 'eyeliner.png',
        'blush'      => 'blush powder.png',
        'browgel'    => 'eyebrow gel.png',
        'brush'      => 'makeup brush.png',
    ];

    public function run(): void
    {
        // The shop's 18 categories (same names as the storefront menu).
        $this->call(CatalogCategoriesSeeder::class);
        $cat = Category::pluck('id', 'name');
        $c = fn (string $name) => $cat[$name] ?? $cat->first();

        // shop email => [shop name, owner, products]
        // product: [code, title, category, brand, price, sale price|null, discount %|null, stock, photo, description]
        $shops = [
            'tech@store.com' => ['Tech Store', null, [
                ['DEMO-T01', 'Wireless Earbuds Pro with Charging Case', 'Electronics & Gadgets', 'SoundMax', 2499, 1899, null, 40, 'headphones', 'Bluetooth 5.3 earbuds with noise reduction, 30-hour battery with the case and touch controls.'],
                ['DEMO-T02', 'Wireless Game Controller', 'Gaming & Consoles', 'PlayPro', 1799, null, 10, 25, 'controller', 'Ergonomic controller with dual vibration, works with PC and Android. Rechargeable battery.'],
                ['DEMO-T03', 'Smart Watch Fitness Tracker', 'Electronics & Gadgets', 'FitTime', 3299, 2799, null, 18, 'watch', 'Heart rate, sleep and step tracking, 7-day battery, water resistant.'],
                ['DEMO-T04', 'Pro Gaming Controller (Elite Edition)', 'Gaming & Consoles', 'PlayPro', 3499, 2999, null, 12, 'controller', 'Swappable thumbsticks, back paddles and a 40-hour battery.'],
                ['DEMO-T05', 'Gaming Headset with Mic', 'Gaming & Consoles', 'SoundMax', 1999, null, 15, 20, 'headphones', '7.1 surround sound, noise-cancelling mic and soft ear cushions.'],
                ['DEMO-T06', 'Bluetooth Neckband Earphones', 'Electronics & Gadgets', 'SoundMax', 899, 749, null, 55, 'headphones', 'Magnetic earbuds, 20-hour battery and quick charge.'],
            ]],
            'fashion@hub.com' => ['Fashion Hub', null, [
                ['DEMO-F01', "Classic Men's Polo Shirt (3-Pack)", "Men's Apparel", 'Hub Basics', 1299, 999, null, 60, 'polo', 'Breathable cotton piqué polos in navy, white and maroon. Sizes S–XXL.'],
                ['DEMO-F02', 'Embroidered Long Dress', "Women's Apparel", 'Hub Studio', 2199, null, 15, 22, 'dress', 'Flowy embroidered dress with soft lining — perfect for events.'],
                ['DEMO-F03', 'Oversized Knit Sweater', "Women's Apparel", 'Hub Studio', 1499, 1199, null, 35, 'sweater', 'Cozy chunky knit in oatmeal beige. One size fits most.'],
                ['DEMO-F04', 'Leather Ankle Boots', 'Shoes & Footwear', 'StepUp', 2899, null, 20, 14, 'boots', 'Genuine leather ankle boots with a block heel and side zipper.'],
                ['DEMO-F05', 'Wool-Blend Winter Coats', "Women's Apparel", 'Hub Basics', 3499, 2999, null, 10, 'coats', 'Warm wool-blend coats for travel and cold weather.'],
                ['DEMO-F06', "Men's Oxford Long-Sleeve Shirt", "Men's Apparel", 'Hub Basics', 1199, null, 10, 40, 'polo', 'Easy-iron cotton Oxford shirt for work and weekends.'],
                ['DEMO-F07', "Men's Wool Overcoat", "Men's Apparel", 'Hub Studio', 4299, 3699, null, 8, 'coats', 'Tailored single-breasted overcoat in charcoal and camel.'],
            ]],
            'home@essentials.com' => ['Home Essentials', null, [
                ['DEMO-H01', 'Indoor Plant Starter Set (5 Pots)', 'Home & Garden', 'GreenNest', 1599, null, null, 30, 'plants', 'Five easy-care indoor plants with ceramic pots. Brightens any room.'],
                ['DEMO-H02', 'Espresso & Drip Coffee Machine', 'Home Appliances', 'BrewMaster', 8999, 7499, null, 8, 'coffee', '2-in-1 espresso and drip coffee maker with milk frother and grinder.'],
                ['DEMO-H03', 'Ergonomic Leather Office Chair', 'Furniture & Office', 'SitWell', 5499, 4699, null, 12, 'chair', 'Padded leather seat, swivel base and adjustable height.'],
                ['DEMO-H04', 'Hanging Planters Set of 3', 'Home & Garden', 'GreenNest', 899, 749, null, 40, 'plants', 'Macramé hangers with matte ceramic pots for small plants.'],
                ['DEMO-H05', 'Herb Garden Kit (Basil, Mint, Thyme)', 'Home & Garden', 'GreenNest', 599, null, null, 60, 'plants', 'Grow your own kitchen herbs — seeds, soil and pots included.'],
                ['DEMO-H06', 'Mid-Century Accent Chair', 'Furniture & Office', 'SitWell', 6999, 5999, null, 6, 'chair', 'Tan faux-leather armchair with solid wood legs.'],
                ['DEMO-H07', 'Home Office Desk Chair (Mesh Back)', 'Furniture & Office', 'SitWell', 3999, null, 10, 15, 'chair', 'Breathable mesh back, lumbar support and 360° swivel.'],
                ['DEMO-H08', 'Electric Coffee Maker 12-Cup', 'Home Appliances', 'BrewMaster', 2799, 2399, null, 20, 'coffee', 'Programmable drip coffee maker with keep-warm plate.'],
            ]],
            'glow@demo.alvy.test' => ['Glow Beauty PH', 'Bea Santos', [
                ['DEMO-G01', 'Velvet Matte Lipstick', 'Health & Beauty', 'Glow', 399, 299, null, 120, 'lipstick', 'Long-wearing matte lipstick, transfer-proof for up to 12 hours.'],
                ['DEMO-G02', 'Waterproof Liquid Eyeliner', 'Health & Beauty', 'Glow', 349, null, 10, 90, 'eyeliner', 'Ultra-fine tip, smudge-proof and waterproof formula.'],
                ['DEMO-G03', 'Silky Blush Powder', 'Health & Beauty', 'Glow', 449, 379, null, 70, 'blush', 'Buildable blush with a natural satin finish.'],
                ['DEMO-G04', 'Clear Eyebrow Gel', 'Health & Beauty', 'Glow', 299, null, null, 85, 'browgel', 'Sets brows all day without flaking.'],
                ['DEMO-G05', 'Pro Makeup Brush Set (12 pcs)', 'Health & Beauty', 'Glow Tools', 899, 699, null, 40, 'brush', 'Soft synthetic brushes for face and eyes, with a travel pouch.'],
                ['DEMO-G06', 'Daily Skincare Kit', 'Health & Beauty', 'Glow Skin', 1599, 1299, null, 35, 'skincare', 'Cleanser, toner, serum and moisturizer for a simple daily routine.'],
            ]],
            'pawfect@demo.alvy.test' => ['Pawfect Pets', 'Carlo Reyes', [
                ['DEMO-P01', 'Stainless Steel Pet Bowl Set', 'Pet Supplies', 'PawPal', 599, 499, null, 50, 'dogbowl', 'Non-slip stand with two rust-proof bowls for food and water.'],
                ['DEMO-P02', 'Premium Dog Food 3kg', 'Pet Supplies', 'PawPal', 899, null, null, 45, 'dogbowl', 'Complete nutrition for adult dogs, made with real chicken.'],
                ['DEMO-P03', 'Elevated Dog Feeder Stand', 'Pet Supplies', 'PawPal', 1099, 899, null, 25, 'dogbowl', 'Raised feeder that is easier on your dog’s neck and joints.'],
                ['DEMO-P04', 'Pet Grooming Shampoo 500ml', 'Pet Supplies', 'PawPal', 349, null, 15, 70, 'dogbowl', 'Gentle oatmeal shampoo for dogs and cats with sensitive skin.'],
            ]],
            'active@demo.alvy.test' => ['Active Life Sports', 'Miguel Cruz', [
                ['DEMO-S01', 'Adjustable Dumbbell Set 20kg', 'Sports & Outdoors', 'IronFit', 3499, 2999, null, 15, 'dumbbells', 'Pair of adjustable dumbbells with a resistance band and handle grips.'],
                ['DEMO-S02', 'Running Sneakers Flyknit', 'Shoes & Footwear', 'Stride', 3999, null, 25, 20, 'sneakers', 'Lightweight knit running shoes with cushioned soles.'],
                ['DEMO-S03', 'Full-Face Motorcycle Helmet', 'Automotive & Motorcycle', 'RoadGuard', 2799, 2499, null, 18, 'helmet', 'DOT-certified helmet with anti-fog visor and ventilation.'],
                ['DEMO-S04', 'Resistance Bands Set (5 Levels)', 'Sports & Outdoors', 'IronFit', 699, 549, null, 80, 'dumbbells', 'Latex bands with handles, door anchor and carry bag.'],
                ['DEMO-S05', 'Jump Rope with Counter', 'Sports & Outdoors', 'IronFit', 399, null, null, 100, 'dumbbells', 'Adjustable speed rope with a digital jump counter.'],
                ['DEMO-S06', 'Motorcycle Riding Gloves', 'Automotive & Motorcycle', 'RoadGuard', 899, 749, null, 35, 'helmet', 'Touchscreen-friendly riding gloves with knuckle protection.'],
                ['DEMO-S07', 'Motorcycle Phone Mount', 'Automotive & Motorcycle', 'RoadGuard', 599, null, 10, 60, 'helmet', 'Vibration-damping handlebar mount for 4.7"–7" phones.'],
            ]],
            'bookworm@demo.alvy.test' => ['Bookworm Corner', 'Liza Mendoza', [
                ['DEMO-B01', 'Classic Novels Bundle (5 Books)', 'Books & Media', 'Bookworm', 1499, 1199, null, 25, 'books', 'Five timeless classics in paperback — a great gift for readers.'],
                ['DEMO-B02', 'School Supplies Starter Kit', 'Stationery & School Supplies', 'Bookworm', 699, null, 10, 60, 'stationery', 'Notebooks, pens, highlighters, sticky notes and a pencil case.'],
                ['DEMO-B03', 'Bestselling Filipino Novels Set', 'Books & Media', 'Bookworm', 899, null, null, 30, 'books', 'Three award-winning novels by Filipino authors.'],
                ['DEMO-B04', 'Gel Pen Set (24 Colors)', 'Stationery & School Supplies', 'Bookworm', 349, 279, null, 90, 'stationery', 'Smooth gel pens in 24 bright colors, great for notes and journaling.'],
                ['DEMO-B06', 'Kids Storybook Collection (10 Books)', 'Books & Media', 'Bookworm', 1299, 999, null, 35, 'books', 'Ten illustrated bedtime stories for ages 3–8.'],
                ['DEMO-B05', 'Hardbound Planner & Journal', 'Stationery & School Supplies', 'Bookworm', 499, null, 15, 50, 'stationery', 'Undated planner with monthly and weekly pages.'],
            ]],
            'littlewonders@demo.alvy.test' => ['Little Wonders Kids', 'Ana Villanueva', [
                ['DEMO-K01', 'Wooden Learning Toys Set', 'Kids & Baby', 'TinyMinds', 1299, 999, null, 30, 'toys', 'Colorful wooden blocks, shapes and cars that help kids learn.'],
                ['DEMO-K02', 'Kids Plush Sock Monkey', 'Kids & Baby', 'TinyMinds', 499, null, null, 40, 'toys', 'Soft, washable plush toy for toddlers.'],
                ['DEMO-K03', 'Building Blocks Bucket (120 pcs)', 'Kids & Baby', 'TinyMinds', 799, 649, null, 45, 'toys', 'Big, easy-grip blocks for ages 2 and up.'],
            ]],
            'brewbean@demo.alvy.test' => ['Brew & Bean Kitchen', 'Paolo Garcia', [
                ['DEMO-E01', 'Gourmet Spice Collection', 'Food & Gourmet', 'Brew & Bean', 799, 649, null, 50, 'spices', 'Eight hand-picked spices and herbs in reusable jars.'],
                ['DEMO-E02', 'Barista Coffee Grinder', 'Home Appliances', 'Brew & Bean', 2499, null, 15, 16, 'coffee', 'Burr grinder with 15 grind settings for espresso to French press.'],
                ['DEMO-E03', 'Dried Fruit & Nut Mix 500g', 'Food & Gourmet', 'Brew & Bean', 449, null, null, 80, 'spices', 'Almonds, cashews, raisins and dried mango — a healthy snack.'],
                ['DEMO-E04', 'Single-Origin Coffee Beans 250g', 'Food & Gourmet', 'Brew & Bean', 549, 479, null, 65, 'spices', 'Medium-roast Benguet Arabica beans, roasted weekly.'],
            ]],
            'luxe@demo.alvy.test' => ['Luxe Accessories', 'Nina Lopez', [
                ['DEMO-L01', 'Woven Leather Tote Bag', 'Bags & Luggage', 'Luxe', 3299, 2799, null, 15, 'bag', 'Hand-woven vegan leather tote with gold chain detail.'],
                ['DEMO-L02', 'Oval Sunglasses & Gold Earrings Set', 'Jewelry & Watches', 'Luxe', 1199, null, 20, 28, 'sunglasses', 'Retro oval sunglasses with UV400 lenses plus statement gold earrings.'],
                ['DEMO-L03', 'Stainless Steel Dress Watch', 'Jewelry & Watches', 'Luxe', 4599, 3899, null, 9, 'watch', 'Minimalist stainless steel watch, water resistant.'],
                ['DEMO-L04', 'Neutral Weekend Outfit Set', "Women's Apparel", 'Luxe', 2499, 1999, null, 20, 'outfit', 'Relaxed top and wide-leg trousers in soft neutral tones.'],
                ['DEMO-L05', 'Gold Hoop Earrings', 'Jewelry & Watches', 'Luxe', 799, 649, null, 50, 'sunglasses', '18k gold-plated hoops, hypoallergenic.'],
                ['DEMO-L06', 'Leather Crossbody Bag', 'Bags & Luggage', 'Luxe', 2199, null, 10, 22, 'bag', 'Compact crossbody with adjustable strap and zip pocket.'],
                ['DEMO-L07', 'Weekender Travel Bag', 'Bags & Luggage', 'Luxe', 3899, 3299, null, 10, 'bag', 'Roomy weekender with shoe compartment and trolley sleeve.'],
            ]],
        ];

        // The shop's first sample products, moved from the old categories into the shop's ones.
        foreach ([
            'Smartphone'            => 'Electronics & Gadgets',
            'Cotton Casual T-Shirt' => "Men's Apparel",
            'The Great Gatsby'      => 'Books & Media',
            'Running Shoes'         => 'Shoes & Footwear',
            'Coffee Maker'          => 'Home Appliances',
        ] as $title => $category) {
            Product::where('title', $title)
                ->where(fn ($q) => $q->whereNull('product_code')->orWhere('product_code', 'not like', 'DEMO-%'))
                ->update(['category_id' => $c($category)]);
        }

        $productCount = 0;
        foreach ($shops as $email => [$shopName, $owner, $products]) {
            $seller = $this->seller($email, $shopName, $owner);
            if (! $seller) {
                $this->command?->warn("Skipped {$shopName}: {$email} exists but is not a seller.");
                continue;
            }

            foreach ($products as [$code, $title, $category, $brand, $price, $sale, $pct, $stock, $photo, $desc]) {
                $image = $this->photo($photo);
                $product = Product::updateOrCreate(['product_code' => $code], [
                    'seller_id' => $seller->id, 'category_id' => $c($category), 'title' => $title, 'brand' => $brand,
                    'price' => $price, 'sale_price' => $sale, 'discount_percent' => $pct ?? 0, 'stock' => $stock,
                    'availability' => 'in_stock', 'status' => 'active', 'approval_status' => 'approved',
                    'is_archived' => false, 'description' => $desc, 'image' => $image,
                ]);
                ProductImage::updateOrCreate(['product_id' => $product->id, 'path' => $image], ['sort_order' => 0]);
                $productCount++;
            }

            // Two store vouchers per shop.
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $shopName), 0, 6));
            foreach ([
                [$prefix . '100', '₱100 off orders of ₱1,000+', 'fixed', 100, null, 1000],
                [$prefix . '10', '10% off the whole store (up to ₱300)', 'percent', 10, 300, 500],
            ] as [$vcode, $name, $type, $value, $max, $min]) {
                Voucher::updateOrCreate(['code' => $vcode], [
                    'name' => $name, 'scope' => Voucher::SCOPE_SELLER, 'seller_id' => $seller->id,
                    'discount_type' => $type, 'discount_value' => $value, 'max_discount' => $max, 'min_spend' => $min,
                    'per_user_limit' => 1, 'usage_limit' => null, 'starts_at' => null, 'ends_at' => null,
                    'is_active' => true, 'created_by' => $seller->id,
                ]);
            }
        }

        // The ALVY-wide sample vouchers too.
        $this->call(SampleVoucherSeeder::class);

        $this->command?->info("Demo marketplace ready: {$productCount} demo products in " . count($shops) . ' shops; '
            . Product::published()->count() . ' products visible in the shop.');
    }

    /** An existing seller account, or a new approved demo shop. */
    private function seller(string $email, string $shopName, ?string $owner): ?User
    {
        $user = User::where('email', $email)->first();
        if ($user && $user->role !== 'seller') {
            return null;
        }

        if (! $user) {
            [$first, $last] = explode(' ', $owner ?? 'Demo Seller', 2) + [1 => 'Seller'];
            $user = User::create([
                'name' => $owner ?? $shopName, 'first_name' => $first, 'last_name' => $last,
                'email' => $email, 'password' => Hash::make('Seller@123'), 'role' => 'seller',
                'approval_status' => 'approved', 'email_verified_at' => now(),
                'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Commonwealth',
            ]);
        }

        if (! $user->sellerApplication) {
            SellerApplication::create([
                'user_id' => $user->id, 'first_name' => $user->first_name ?? $user->name, 'last_name' => $user->last_name ?? '',
                'full_name' => $user->name, 'shop_name' => $shopName, 'business_name' => $shopName,
                'phone' => '0917' . str_pad((string) ($user->id % 10000000), 7, '0', STR_PAD_LEFT),
                'address' => 'Commonwealth, Quezon City, Metro Manila', 'province' => 'Metro Manila',
                'municipality' => 'Quezon City', 'barangay' => 'Commonwealth',
                'line_of_business' => 'General', 'seller_type' => 'Individual',
                'government_id_path' => 'demo/none', 'status' => 'approved',
            ]);
        }

        return $user;
    }

    /** Copy a photo from public/images into the public upload folder (once) and return its path. */
    private function photo(string $key): string
    {
        $source = public_path('images/' . self::PHOTOS[$key]);
        $path   = 'products/demo/' . $key . '.' . pathinfo($source, PATHINFO_EXTENSION);

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, file_get_contents($source));
        }

        return $path;
    }
}
