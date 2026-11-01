<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\LogisticsHub;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerApplication;
use App\Models\SortingCenterApplication;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Clears every account and everything that belongs to accounts (orders, products, parcels, messages…),
 * keeping only the categories and the migrations, then creates fresh test accounts in Laguna (the
 * delivery area): 1 admin, 10 logistics companies (each with a hub, barangay coverage and a rider),
 * 15 sellers (one category each, 20 products each) and 20 buyers with a default address.
 *
 *   php artisan db:seed --class=FreshTestAccountsSeeder --force
 *
 * Back the database up first. Each product uses its own image in storage/app/public/products/alvy/<sku>.jpg
 * (a real photo or a designed ALVY image); without it, photos from database/seeders/data/product-images.json.
 */
class FreshTestAccountsSeeder extends Seeder
{
    /** Tables that are kept; every other table is emptied. */
    private const KEEP = ['migrations', 'categories'];

    public const PASSWORDS = [
        'admin' => 'Admin@2026', 'logistics' => 'Logistics@2026', 'rider' => 'Rider@2026', 'seller' => 'Seller@2026', 'buyer' => 'Buyer@2026',
    ];

    /** The logistics companies: [business name, contact person, town, hub code, lat, lng]. */
    private const LOGISTICS = [
        ['Calamba Express Logistics', 'Ramon Dizon', 'City of Calamba', 'CAL', 14.2117, 121.1653],
        ['Santa Rosa Swift Cargo', 'Liza Fernandez', 'City of Santa Rosa', 'STR', 14.3122, 121.1114],
        ['Biñan Bilis Hatid', 'Carlos Mendoza', 'City of Biñan', 'BIN', 14.3424, 121.0806],
        ['San Pedro Parcel Movers', 'Grace Villanueva', 'City of San Pedro', 'SPD', 14.3595, 121.0473],
        ['Cabuyao Courier Co.', 'Jerome Castillo', 'City of Cabuyao', 'CAB', 14.2785, 121.1252],
        ['San Pablo Lakeside Logistics', 'Andrea Robles', 'City of San Pablo', 'SPB', 14.0683, 121.3256],
        ['Los Baños Campus Couriers', 'Miguel Navarro', 'Los Baños', 'LSB', 14.1699, 121.2441],
        ['Santa Cruz Provincial Freight', 'Teresa Aquino', 'Santa Cruz', 'STC', 14.2814, 121.4161],
        ['Bay Lakeshore Deliveries', 'Paolo Ramirez', 'Bay', 'BAY', 14.1833, 121.2833],
        ['Pagsanjan Falls Express', 'Celeste Domingo', 'Pagsanjan', 'PAG', 14.2732, 121.4553],
    ];

    private const RIDERS = [
        ['Juan Carlo Reyes', 'Motorcycle', 'NAB1234'], ['Mark Anthony Santos', 'Motorcycle', 'NCD5678'], ['Jessie Bautista', 'Motorcycle', 'NEF9012'],
        ['Ronald Garcia', 'Van', 'NGH3456'], ['Kevin Mendoza', 'Motorcycle', 'NIJ7890'], ['Arnel Villanueva', 'Motorcycle', 'NKL2345'],
        ['Dennis Cruz', 'Motorcycle', 'NMN6789'], ['Rodel Flores', 'Van', 'NOP0123'], ['Joshua Ramos', 'Motorcycle', 'NQR4567'],
        ['Christian Torres', 'Motorcycle', 'NST8901'],
    ];

    private const BUYERS = [
        'Juan Miguel Dela Cruz', 'Maria Angelica Santos', 'Jose Rafael Reyes', 'Kristine Joy Bautista', 'Paolo Gabriel Garcia',
        'Andrea Nicole Mendoza', 'Carlo Miguel Villanueva', 'Bea Camille Cruz', 'Marco Antonio Flores', 'Patricia Anne Ramos',
        'Gabriel Luis Torres', 'Sofia Isabel Navarro', 'Rafael Jose Castillo', 'Camille Rose Aquino', 'Nathaniel James Robles',
        'Jasmine Claire Domingo', 'Adrian Paul Ramirez', 'Hannah Grace Fernandez', 'Christian Dale Morales', 'Alyssa Mae Gonzales',
    ];

    /**
     * The sellers: [category slug, shop name, owner, products as "Title|price|kg"].
     * Each shop sells in its own category.
     */
    private function shops(): array
    {
        return [
            ['health-beauty', 'Ganda Beauty Hub', 'Maricel Santos', [
                'Hydrating Aloe Vera Gel 300ml|149|0.35', 'Vitamin C Brightening Serum 30ml|399|0.1', 'Matte Liquid Lipstick (Rose Nude)|199|0.05',
                'Waterproof Eyeliner Pen|159|0.03', 'Blush Powder Compact (Peach)|229|0.08', 'Brow Gel Clear Fix|179|0.03',
                'Gentle Foaming Facial Wash 150ml|189|0.2', 'SPF 50 Sunscreen Lotion 100ml|329|0.15', 'Sheet Mask Set (10 pcs)|249|0.2',
                'Keratin Repair Shampoo 400ml|279|0.5', 'Argan Oil Hair Serum 50ml|219|0.1', 'Whitening Body Lotion 500ml|299|0.6',
                'Rosewater Facial Toner 200ml|169|0.25', 'Lip & Cheek Tint (Cherry)|129|0.04', 'Makeup Brush Set (12 pcs)|449|0.3',
                'Nail Polish Trio (Pastel)|159|0.12', 'Charcoal Peel-off Mask 100g|139|0.12', 'Coconut Body Scrub 250g|199|0.3',
                'Setting Spray Long-Wear 100ml|259|0.15', 'Cushion Foundation SPF 30 (Natural)|499|0.1',
            ]],
            ['electronics-gadgets', 'Techno Laguna', 'Rodrigo Lim', [
                'Wireless Earbuds Bluetooth 5.3|899|0.15', '20000mAh Power Bank Fast Charge|1199|0.45', 'USB-C Fast Charger 25W|499|0.1',
                'Braided USB-C Cable 1.5m|199|0.06', 'Smartwatch Fitness Tracker|1599|0.12', 'Bluetooth Portable Speaker|1299|0.6',
                'Ring Light 10" with Tripod|749|0.9', 'Wireless Mouse Silent Click|399|0.1', 'Mechanical Keyboard RGB|1899|0.95',
                'Phone Holder Car Mount|249|0.15', '1080p Web Camera with Mic|999|0.2', 'Laptop Cooling Pad|699|0.8',
                'Tempered Glass Screen Protector|149|0.05', 'Mini Wi-Fi Router 300Mbps|1099|0.35', '64GB USB Flash Drive|349|0.03',
                'Noise-Cancelling Headphones|2499|0.4', 'Smart LED Bulb Wi-Fi|299|0.1', 'Selfie Stick Tripod Bluetooth|399|0.25',
                'Digital Kitchen Scale|449|0.5', 'Portable Mini Fan Rechargeable|329|0.3',
            ]],
            ['womens-apparel', 'Bella Moda Boutique', 'Isabel Cruz', [
                'Floral Wrap Midi Dress|799|0.35', 'Ribbed Knit Crop Top|299|0.15', 'High-Waist Wide Leg Pants|649|0.45',
                'Linen Button-Down Blouse|549|0.25', 'Denim Mom Jeans|899|0.6', 'Pleated Midi Skirt|599|0.3',
                'Oversized Cotton Tee|349|0.2', 'Smocked Off-Shoulder Top|399|0.18', 'Lounge Set Shorts & Top|699|0.4',
                'Cardigan Soft Knit|749|0.4', 'Satin Slip Dress|849|0.3', 'Ruffle Sleeve Blouse|459|0.2',
                'Cargo Jogger Pants|599|0.45', 'Sleeveless Maxi Dress|899|0.4', 'Sports Bra & Leggings Set|799|0.35',
                'Denim Button Skirt|549|0.4', 'Square Neck Bodysuit|399|0.15', 'Puff Sleeve Mini Dress|699|0.3',
                'Blazer Single Button|1199|0.7', 'Pajama Set Cotton|599|0.4',
            ]],
            ['sports-outdoors', 'Laguna Sports Center', 'Ernesto Ramos', [
                'Yoga Mat 6mm Non-slip|599|1.2', 'Adjustable Dumbbell Pair 5kg|1499|5', 'Jump Rope Speed Cable|199|0.2',
                'Resistance Bands Set (5)|349|0.4', 'Basketball Size 7 Rubber|699|0.65', 'Badminton Racket Pair|899|0.5',
                'Hydro Flask 1L Insulated|799|0.5', 'Camping Tent 2-Person|2499|2.5', 'Hiking Backpack 40L|1599|1.1',
                'Cycling Gloves Padded|299|0.1', 'Football Training Cones (20)|249|0.6', 'Swimming Goggles Anti-fog|249|0.1',
                'Ab Roller Wheel|399|0.8', 'Sports Headband Pack (3)|149|0.06', 'Volleyball Soft Touch|649|0.3',
                'Foam Roller 45cm|449|0.5', 'Table Tennis Paddle Set|599|0.4', 'Folding Camping Chair|899|2',
                'Push-up Bars Pair|299|0.6', 'LED Headlamp Rechargeable|349|0.15',
            ]],
            ['mens-apparel', 'Barako Menswear', 'Antonio Navarro', [
                'Classic Polo Shirt (Navy)|499|0.3', 'Slim Fit Chino Pants|799|0.55', 'Oxford Long Sleeve Shirt|699|0.35',
                'Graphic Cotton Tee|349|0.2', 'Denim Jacket Washed|1299|0.9', 'Drawstring Board Shorts|449|0.25',
                'Hooded Pullover Jacket|899|0.6', 'Cargo Shorts 6-Pocket|549|0.4', 'Barong Tagalog Jusi|1999|0.4',
                'Henley Long Sleeve|459|0.3', 'Track Pants Zip Pocket|599|0.4', 'Plain White Tee 3-Pack|599|0.6',
                'Linen Short Sleeve Shirt|649|0.3', 'Straight Cut Jeans|999|0.7', 'Varsity Bomber Jacket|1499|0.9',
                'Athletic Dri-fit Shirt|399|0.18', 'Leather Belt Classic|399|0.25', 'Boxer Briefs 3-Pack|349|0.2',
                'Crew Socks 5 Pairs|249|0.25', 'Sleeveless Muscle Tee|299|0.15',
            ]],
            ['books-media', 'Pahina Bookshop', 'Leonora Garcia', [
                'Noli Me Tangere (Annotated)|299|0.5', 'El Filibusterismo (Annotated)|299|0.5', 'Philippine History Made Easy|399|0.6',
                'Atomic Habits (Paperback)|599|0.4', 'The Psychology of Money|549|0.35', 'Tagalog-English Dictionary|449|0.8',
                'Coloring Book for Adults: Mandalas|199|0.3', 'Basic Calculus Reviewer|349|0.6', 'Children\'s Bedtime Stories|299|0.45',
                'Cooking Filipino Classics|699|0.9', 'Learn Python in 30 Days|649|0.7', 'Poetry of the Islands|249|0.3',
                'Mindset (Paperback)|499|0.35', 'UPCAT Reviewer Complete|799|1.2', 'Manga Starter Pack Vol. 1–3|899|0.9',
                'Sketchbook Drawing Guide|349|0.5', 'Rich Dad Poor Dad|449|0.35', 'English Grammar in Use|699|0.8',
                'Bible Stories for Kids|399|0.6', 'Philippine Folk Tales|279|0.4',
            ]],
            ['food-gourmet', 'Lasang Laguna Delicacies', 'Remedios Aquino', [
                'Buko Pie (Whole)|350|1', 'Espasol Pack of 12|150|0.4', 'Uraro Arrowroot Cookies|180|0.3',
                'Kesong Puti (Laguna)|120|0.3', 'Pancit Habhab Noodles 500g|95|0.5', 'Lambanog Original 350ml|250|0.7',
                'Salted Duck Eggs (12)|180|0.8', 'Coco Jam 300g|130|0.4', 'Peanut Brittle Pack|99|0.25',
                'Barako Coffee Ground 250g|220|0.3', 'Dried Mangoes 200g|169|0.25', 'Chicharon Bulaklak 200g|149|0.2',
                'Polvoron Assorted Box|189|0.35', 'Tablea Chocolate 250g|159|0.3', 'Macapuno Strings 340g|175|0.45',
                'Ube Halaya Jar 350g|210|0.5', 'Calamansi Juice Concentrate 500ml|159|0.6', 'Spiced Coconut Vinegar 750ml|120|0.9',
                'Turon Frozen Pack (10)|199|0.8', 'Honey Wild Forest 500ml|349|0.7',
            ]],
            ['furniture-office', 'Kahoy Furniture', 'Fernando Robles', [
                'Ergonomic Office Chair Mesh|3499|12', 'Folding Study Table|1299|8', 'Bookshelf 5-Tier Wooden|1899|14',
                'Computer Desk with Drawer|2599|18', 'Monitor Riser Stand|499|1.5', 'Bean Bag Chair Large|1499|3',
                'Shoe Rack 4-Layer|699|3', 'Bedside Table Rattan|1199|5', 'Laptop Stand Aluminum|799|0.8',
                'Wall Shelves Floating Set|599|2', 'Plastic Drawer Cabinet 4-Level|1099|6', 'Folding Chair Wooden|799|4',
                'Coat Rack Standing|649|3', 'Desk Organizer Bamboo|399|0.9', 'TV Console Minimalist|3299|20',
                'Footrest Under Desk|449|1.2', 'Filing Cabinet 3-Drawer|2299|15', 'Kids Study Table & Chair|1599|7',
                'Corner Shelf Ladder|899|4', 'Gaming Chair Reclining|4599|16',
            ]],
            ['jewelry-watches', 'Kislap Jewelry', 'Corazon Domingo', [
                'Sterling Silver Pendant Necklace|899|0.05', 'Stainless Steel Analog Watch|1299|0.15', 'Freshwater Pearl Earrings|699|0.03',
                'Gold-Plated Hoop Earrings|399|0.02', 'Charm Bracelet Silver|599|0.04', 'Couple Rings Titanium (Pair)|799|0.04',
                'Digital Sports Watch|899|0.1', 'Minimalist Leather Strap Watch|1099|0.12', 'Birthstone Stud Earrings|349|0.02',
                'Anklet Beaded Boho|199|0.02', 'Men\'s Chain Bracelet|499|0.06', 'Name Necklace Custom|699|0.04',
                'Jewelry Box Organizer|549|0.6', 'Smart Band Heart Rate|999|0.08', 'Hair Clip Pearl Set|179|0.05',
                'Crystal Tennis Bracelet|749|0.04', 'Signet Ring Classic|449|0.03', 'Chronograph Steel Watch|2499|0.2',
                'Choker Layered Gold|299|0.03', 'Brooch Vintage Flower|249|0.03',
            ]],
            ['bags-luggage', 'Biyahe Bags', 'Wilfredo Castillo', [
                'Laptop Backpack Anti-theft|1199|0.9', 'Hardcase Luggage 24"|2999|4', 'Canvas Tote Bag|299|0.3',
                'Crossbody Sling Bag|449|0.3', 'Duffel Gym Bag|799|0.7', 'Mini Shoulder Bag (Women)|599|0.35',
                'Travel Packing Cubes (6)|499|0.5', 'Waterproof Dry Bag 20L|399|0.3', 'Leather Wallet Bifold|549|0.15',
                'Coin Purse Woven|149|0.05', 'School Backpack Kids|699|0.6', 'Cabin Luggage 20"|2499|3.2',
                'Belt Bag Unisex|349|0.2', 'Buri Woven Handbag|649|0.4', 'Toiletry Travel Kit|299|0.25',
                'Messenger Bag Canvas|899|0.7', 'Card Holder RFID|249|0.06', 'Foldable Shopping Bag|129|0.1',
                'Drawstring Gym Sack|199|0.15', 'Luggage Scale Digital|299|0.15',
            ]],
            ['shoes-footwear', 'Tsinelas & Co.', 'Rosalinda Ramirez', [
                'Running Shoes Lightweight|1599|0.8', 'Classic White Sneakers|1299|0.9', 'Slip-on Canvas Shoes|599|0.6',
                'Rubber Flip-flops|149|0.3', 'Leather Loafers Men|1899|1', 'Block Heel Sandals|899|0.6',
                'Platform Sneakers Women|1199|0.9', 'Slide Sandals Unisex|299|0.4', 'Hiking Boots Waterproof|2299|1.4',
                'Kids Light-up Sneakers|799|0.5', 'Ballet Flats Comfort|649|0.4', 'Basketball Shoes High-cut|2499|1.1',
                'Rain Boots Rubber|499|0.9', 'Espadrilles Woven|699|0.5', 'Formal Oxford Shoes|1999|1',
                'Indoor House Slippers|199|0.3', 'Wedge Sandals|849|0.6', 'Football Cleats|1699|0.8',
                'Shoe Cleaner Kit|249|0.3', 'Memory Foam Insoles|199|0.15',
            ]],
            ['home-appliances', 'Bahay Appliance Depot', 'Benjamin Morales', [
                'Air Fryer 4.5L Digital|2999|4.5', 'Electric Kettle 1.8L|799|1.2', 'Rice Cooker 1.8L|1499|2.5',
                'Stand Fan 16"|1299|4', 'Blender 1.5L Glass Jar|1699|3', 'Steam Iron Non-stick|899|1.2',
                'Hand Mixer 5-Speed|799|1', 'Electric Grill Indoor|1899|3.5', 'Vacuum Cleaner Handheld|1999|2',
                'Toaster 2-Slice|899|1.5', 'Induction Cooker|2299|3', 'Coffee Maker Drip|1499|2',
                'Water Dispenser Hot & Cold|4999|12', 'Mini Refrigerator 1.7 cu.ft.|6999|18', 'Electric Food Steamer|1199|2',
                'Air Purifier HEPA|3499|4', 'Slow Cooker 3.5L|1599|4', 'Sandwich Maker|699|1.4',
                'Juicer Extractor|2499|3.5', 'Wall Fan 16" Remote|1599|4',
            ]],
            ['gaming-consoles', 'Level Up Games', 'Joaquin Gonzales', [
                'Wireless Game Controller|1499|0.3', 'Gaming Headset 7.1 Surround|1299|0.4', 'RGB Gaming Mouse 7200DPI|799|0.15',
                'Gaming Mouse Pad XL|399|0.5', 'Console Charging Dock|899|0.4', 'Handheld Retro Console 500 Games|1599|0.3',
                'Phone Gaming Trigger Set|249|0.08', 'Controller Silicone Skin|249|0.05', 'Gaming Keyboard Membrane RGB|999|0.9',
                'Capture Card HD|2499|0.2', 'Streaming Microphone USB|1999|0.6', 'Gaming Glasses Blue Light|499|0.05',
                'Joystick Arcade Stick|2999|2', 'VR Headset for Phone|899|0.5', 'Cooling Fan for Handheld|399|0.15',
                'HDMI 2.1 Cable 2m|349|0.15', 'Game Storage Case|299|0.3', 'Steering Wheel Controller|3999|3',
                'Gaming Chair Cushion|599|0.6', 'Gift Card ₱500 (Digital)|500|0.01',
            ]],
            ['automotive-motorcycle', 'Moto Parts Laguna', 'Eduardo Torres', [
                'Full-Face Helmet ICC|1899|1.5', 'Motorcycle Phone Mount|399|0.2', 'Chain Lube Spray 400ml|249|0.45',
                'LED Headlight Bulb H4|599|0.15', 'Motorcycle Cover Waterproof|699|0.8', 'Riding Gloves Full Finger|449|0.2',
                'Car Air Freshener Gel|129|0.15', 'Microfiber Towel Set (5)|299|0.4', 'Tire Inflator Portable|1499|1',
                'Car Seat Cover Set|1999|3', 'Dashcam 1080p|1899|0.3', 'Motorcycle Rain Coat Set|599|0.7',
                'Engine Oil 10W-40 1L|349|1', 'Brake Pads Set (Scooter)|399|0.3', 'Side Mirror Pair|499|0.6',
                'Car Vacuum Cleaner 12V|899|1.2', 'Reflective Safety Vest|149|0.15', 'Jumper Cables 2.5m|699|1.3',
                'Helmet Visor Clear|349|0.2', 'Steering Wheel Cover Leather|399|0.4',
            ]],
            ['stationery-school-supplies', 'Lapis School Supplies', 'Victoria Flores', [
                'Spiral Notebook 5-Pack|199|0.8', 'Gel Pens 12 Colors|149|0.15', 'Scientific Calculator|699|0.2',
                'Colored Pencils 24 Set|179|0.3', 'Highlighter Pastel Set (6)|129|0.1', 'Sticky Notes Bundle|99|0.15',
                'Art Sketch Pad A4|159|0.4', 'Correction Tape (3)|89|0.06', 'Ruler & Geometry Set|79|0.1',
                'Pencil Case Canvas|149|0.1', 'Bond Paper A4 Ream|279|2.5', 'Watercolor Paint 24 Colors|249|0.3',
                'Stapler with Staples|129|0.25', 'Planner 2027 Undated|299|0.4', 'Crayons 16 Colors|69|0.12',
                'Index Cards 100 pcs|59|0.15', 'Glue Stick Pack (4)|79|0.1', 'Whiteboard Markers (4)|119|0.1',
                'Expanding File Folder|199|0.35', 'Calligraphy Brush Pens (10)|249|0.12',
            ]],
        ];
    }

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('FreshTestAccountsSeeder is not for production.');
        }
        $laguna = json_decode(file_get_contents(database_path('seeders/data/laguna.json')), true);
        $images = json_decode(@file_get_contents(database_path('seeders/data/product-images.json')) ?: '[]', true);
        $towns = collect($laguna['towns'])->keyBy('name');
        $prov = $laguna['province'];

        $this->wipe();

        $pw = collect(self::PASSWORDS)->map(fn ($p) => Hash::make($p));
        $user = function (string $role, string $email, string $name, string $password, array $extra = []) {
            return User::forceCreate($extra + [
                'name' => $name, 'email' => $email, 'password' => $password, 'role' => $role,
                'approval_status' => 'approved', 'is_active' => true, 'email_verified_at' => now(),
            ]);
        };
        $phone = fn (int $n) => '0917' . str_pad((string) (1000000 + $n), 7, '0', STR_PAD_LEFT);

        // ── Admin ──
        $admin = $user('admin', 'admin@alvy.trade', 'ALVY Admin', $pw['admin'], ['phone' => $phone(1)]);

        // ── Logistics companies, each with a hub, its town's barangays and one rider ──
        $companies = [];
        foreach (self::LOGISTICS as $i => [$business, $person, $townName, $hubCode, $lat, $lng]) {
            $n = $i + 1;
            $town = $towns[$townName];
            $brgy = $town['barangays'][0];
            [$first, $last] = [Str::beforeLast($person, ' '), Str::afterLast($person, ' ')];
            $sc = $user('sorting_center', sprintf('logistics%02d@alvy.trade', $n), $person, $pw['logistics'], [
                'first_name' => $first, 'last_name' => $last, 'phone' => $phone(100 + $n), 'contact_no' => $phone(100 + $n),
                'province' => $prov['name'], 'municipality' => $townName, 'barangay' => $brgy['name'],
                'assigned_municipality' => $townName, 'assigned_municipality_code' => $town['code'],
                'assigned_province' => $prov['name'], 'assigned_province_code' => $prov['code'],
            ]);
            SortingCenterApplication::forceCreate([
                'user_id' => $sc->id, 'business_name' => $business, 'status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now(),
                'first_name' => $first, 'last_name' => $last, 'sex' => $n % 2 ? 'Male' : 'Female', 'birthday' => '1988-05-1' . ($n % 9),
                'age' => 38, 'contact_no' => $phone(100 + $n),
                'region' => $laguna['province']['region'], 'region_code' => $prov['region_code'], 'province' => $prov['name'], 'province_code' => $prov['code'],
                'municipality' => $townName, 'municipality_code' => $town['code'], 'barangay' => $brgy['name'], 'barangay_code' => $brgy['code'],
                'street' => 'National Highway', 'house_number' => (string) (100 + $n), 'zip_code' => '4000',
                'address' => (100 + $n) . ' National Highway, ' . $brgy['name'] . ', ' . $townName . ', Laguna',
                'government_id_path' => 'ids/sample-id.jpg', 'business_permit_path' => 'permits/sample-permit.pdf',
            ]);
            $areas = [];
            foreach ($town['barangays'] as $j => $b) {
                $areas[] = DeliveryArea::create([
                    'sorting_center_id' => $sc->id, 'name' => $b['name'], 'code' => $hubCode . '-' . str_pad((string) ($j + 1), 2, '0', STR_PAD_LEFT),
                    'municipality' => $townName, 'region_code' => $prov['region_code'], 'province_code' => $prov['code'],
                    'municipality_code' => $town['code'], 'barangay_code' => $b['code'],
                ]);
            }
            LogisticsHub::create(['sorting_center_id' => $sc->id, 'code' => 'HUB-' . $hubCode, 'name' => $business . ' Hub',
                'address' => (100 + $n) . ' National Highway, ' . $brgy['name'] . ', ' . $townName . ', Laguna', 'latitude' => $lat, 'longitude' => $lng]);

            [$riderName, $vehicle, $plate] = self::RIDERS[$i];
            $ru = $user('courier', sprintf('rider%02d@alvy.trade', $n), $riderName, $pw['rider'], ['phone' => $phone(200 + $n)]);
            Rider::forceCreate([
                'user_id' => $ru->id, 'sorting_center_id' => $sc->id, 'full_name' => $riderName, 'phone' => $phone(200 + $n),
                'vehicle_type' => $vehicle, 'plate_number' => $plate, 'license_number' => sprintf('N%02d-26-%06d', $n, 100000 + $n),
                'license_expiry' => now()->addYears(3)->toDateString(), 'area_id' => $areas[0]->id,
                'region_code' => $prov['region_code'], 'province_code' => $prov['code'], 'municipality_code' => $town['code'], 'barangay_code' => $town['barangays'][0]['code'],
                'application_status' => 'approved', 'approved_at' => now(), 'approved_by' => $sc->id, 'is_active' => true,
                'can_transport_transfers' => true,
            ]);
            $companies[] = [$sc, $townName];
        }

        // ── Sellers: one category each, 20 products each, in the companies' towns ──
        $categories = Category::all()->keyBy('slug');
        foreach ($this->shops() as $i => [$slug, $shop, $owner, $products]) {
            $n = $i + 1;
            $townName = $companies[$i % count($companies)][1];
            $town = $towns[$townName];
            $brgy = $town['barangays'][1] ?? $town['barangays'][0];
            [$first, $last] = [Str::beforeLast($owner, ' '), Str::afterLast($owner, ' ')];
            $seller = $user('seller', sprintf('seller%02d@alvy.trade', $n), $owner, $pw['seller'], [
                'first_name' => $first, 'last_name' => $last, 'phone' => $phone(300 + $n),
            ]);
            $street = (10 + $n) . ' Rizal Street';
            SellerApplication::forceCreate([
                'user_id' => $seller->id, 'full_name' => $owner, 'first_name' => $first, 'last_name' => $last,
                'shop_name' => $shop, 'business_name' => $shop, 'seller_type' => 'Individual', 'phone' => $phone(300 + $n),
                'region' => $laguna['province']['region'], 'province' => $prov['name'], 'province_code' => $prov['code'],
                'municipality' => $townName, 'municipality_code' => $town['code'], 'barangay' => $brgy['name'],
                'street' => $street, 'zip_code' => '4000',
                'address' => $street . ', ' . $brgy['name'] . ', ' . $townName . ', Laguna, 4000',
                'description' => $shop . ' — quality ' . strtolower($categories[$slug]->name ?? 'items') . ' from Laguna, shipped fast.',
                'government_id_path' => 'ids/sample-id.jpg', 'status' => 'approved',
            ]);
            $cat = $categories[$slug] ?? null;
            $pics = array_values($images[(string) $cat?->id] ?? []);
            foreach ($products as $k => $line) {
                [$title, $price, $kg] = explode('|', $line);
                Product::forceCreate([
                    'seller_id' => $seller->id, 'category_id' => $cat?->id, 'title' => $title,
                    'description' => $title . ' from ' . $shop . '. Brand new and carefully packed. Ships from ' . $townName . ', Laguna.',
                    'price' => (float) $price, 'stock' => [25, 40, 15, 60, 8, 30, 50, 12, 35, 20][$k % 10],
                    // Each product's own photo (storage/app/public/products/alvy/<sku>.jpg) when it is there.
                    'weight_kg' => (float) $kg, 'image' => Storage::disk('public')->exists($own = sprintf('products/alvy/S%02d-%03d.jpg', $n, $k + 1))
                        ? $own : ($pics ? $pics[$k % count($pics)] : null),
                    'condition' => 'New', 'availability' => 'in_stock', 'status' => 'active', 'approval_status' => 'approved',
                    'is_archived' => false, 'sku' => sprintf('S%02d-%03d', $n, $k + 1),
                    // A few products on sale, like a real shop.
                    'discount_percent' => $k % 6 === 0 ? 10 : 0,
                ]);
            }
        }

        // ── Buyers with a default address in a served town ──
        foreach (self::BUYERS as $i => $name) {
            $n = $i + 1;
            $townName = $companies[$i % count($companies)][1];
            $town = $towns[$townName];
            $brgy = $town['barangays'][($i % max(1, count($town['barangays']) - 2)) + 2] ?? $town['barangays'][0];
            [$first, $last] = [Str::beforeLast($name, ' '), Str::afterLast($name, ' ')];
            $b = $user('buyer', sprintf('buyer%02d@alvy.trade', $n), $name, $pw['buyer'], [
                'first_name' => $first, 'last_name' => $last, 'phone' => $phone(400 + $n),
            ]);
            UserAddress::create([
                'user_id' => $b->id, 'label' => 'Home', 'full_name' => $name, 'phone' => $phone(400 + $n),
                'address_line' => (20 + $n) . ' Mabini Street', 'barangay' => $brgy['name'], 'city' => $townName,
                'province' => $prov['name'], 'region' => $laguna['province']['region'], 'zip' => '4000', 'country' => 'Philippines',
                'province_code' => $prov['code'], 'municipality_code' => $town['code'], 'barangay_code' => $brgy['code'], 'is_default' => true,
            ]);
        }

        // ── A few ALVY vouchers so the Voucher Center isn't empty ──
        foreach ([
            ['FREESHIP50', 'Free shipping up to ₱50', 'shipping', 'fixed', 50, null, 199],
            ['ALVY10', '10% off, capped at ₱150', 'platform', 'percent', 10, 150, 500],
            ['WELCOME100', '₱100 off your first big order', 'platform', 'fixed', 100, null, 999],
        ] as [$code, $label, $scope, $type, $value, $max, $min]) {
            Voucher::forceCreate([
                'code' => $code, 'name' => $label, 'scope' => $scope, 'discount_type' => $type, 'discount_value' => $value,
                'max_discount' => $max, 'min_spend' => $min, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(3),
                'usage_limit' => 500, 'per_user_limit' => 1, 'is_active' => true, 'is_claimable' => true, 'created_by' => $admin->id,
            ]);
        }
    }

    /** Empty every table except the kept ones. */
    private function wipe(): void
    {
        $db = DB::getDatabaseName();
        $tables = collect(DB::select('select table_name as t from information_schema.tables where table_schema = ? and table_type = ?', [$db, 'BASE TABLE']))
            ->pluck('t')->reject(fn ($t) => in_array($t, self::KEEP, true));
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $t) {
            DB::table($t)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
