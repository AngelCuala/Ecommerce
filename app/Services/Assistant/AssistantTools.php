<?php

namespace App\Services\Assistant;

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Models\CartItem;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\PlatformPolicy;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ShippingService;
use App\Support\ProductColors;
use App\Support\ProductSearch;
use App\Support\ServiceArea;
use App\Support\ShipmentProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The only things ALVY Assistant can do: an allowlist of read-only lookups, and three actions it can
 * PREPARE (add to cart, cancel an order, contact support). A prepared action does nothing until the buyer
 * presses Confirm in the chat; then the site's own cart / cancel code runs it (ShopAssistant::confirm).
 *
 * Every parameter is checked here. The buyer is always the signed-in user passed in by the controller;
 * no buyer, seller or order id the model sends is trusted, and private records are scoped to that buyer.
 */
class AssistantTools
{
    public const POLICY_TOPICS = ['return_refund_policy', 'terms_of_service', 'privacy_policy', 'prohibited_items'];

    /** The policies the assistant may look up (not the hidden ones, e.g. returns while they are off). */
    public static function policyTopics(): array
    {
        return array_values(array_filter(self::POLICY_TOPICS, fn ($k) => ! \App\Models\PlatformPolicy::hidden($k)));
    }
    public const SUPPORT_TOPICS = ['order problem', 'delivery problem', 'payment or refund', 'wrong or damaged item', 'seller not responding', 'account or login', 'other'];

    /** Product cards collected during a turn (shown under the reply). */
    public array $cards = [];

    /** Actions prepared during a turn (shown as Confirm buttons). */
    public array $actions = [];

    public function __construct(private ShippingService $shipping) {}

    /** Tool definitions for this visitor: guests get the public lookups only. */
    public function definitions(?User $user): array
    {
        $obj = fn (array $props, array $required = []) => ['type' => 'object', 'properties' => $props ?: new \stdClass(), 'required' => $required, 'additionalProperties' => false];
        $productRef = ['type' => 'string', 'description' => 'The product\'s ref (like "P3") from an earlier tool result'];

        $tools = [
            ['name' => 'search_products',
             'description' => 'Search products buyers can order now. Matches name, brand, category and subcategory; tolerates misspellings. Color words in the query (English or Tagalog, e.g. "red bag", "pulang damit", just "black") match the colors seen in product pictures. Returns each product\'s ref, current price, regular price when discounted, stock, rating, colors, seller and link.',
             'inputSchema' => $obj([
                 'query'        => ['type' => 'string', 'description' => 'What to look for, e.g. "bag", "buko pie", "red", "black shoes". Use "" to browse by category or price only.'],
                 'max_price'    => ['type' => 'number', 'description' => 'Highest price in pesos'],
                 'min_price'    => ['type' => 'number', 'description' => 'Lowest price in pesos'],
                 'category'     => ['type' => 'string', 'description' => 'Category name, e.g. "Food & Gourmet"'],
                 'on_sale_only' => ['type' => 'boolean', 'description' => 'Only discounted or flash-sale items'],
                 'sort'         => ['type' => 'string', 'enum' => ['relevance', 'price_low', 'price_high', 'top_rated', 'random'], 'description' => 'Order of results. "random" for "anything" / "surprise me" (the default when query is "").'],
             ], ['query'])],
            ['name' => 'get_product_details',
             'description' => 'Full, current details of one product: price and any discount or flash sale, stock, options (sizes/colors) with their own price and stock, specifications, description, rating, seller and link.',
             'inputSchema' => $obj(['product_id' => $productRef], ['product_id'])],
            ['name' => 'compare_products',
             'description' => 'Side-by-side current price, stock, rating, units sold, options and key specifications of 2 to 4 products, plus each seller\'s store rating, followers, where it ships from and whether it is on vacation. Use it for "which is better", including the same item from different sellers.',
             'inputSchema' => $obj(['product_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '2 to 4 product refs (like "P3")']], ['product_ids'])],
            ['name' => 'get_active_promotions',
             'description' => 'Promotions running now: flash sales, discounted products, claimable ALVY vouchers and store bundle deals.',
             'inputSchema' => $obj([])],
            ['name' => 'get_policy',
             'description' => "ALVY's published policy text on a topic.",
             'inputSchema' => $obj(['topic' => ['type' => 'string', 'enum' => self::policyTopics()]], ['topic'])],
        ];

        if (! $user) {
            return $tools;
        }

        return array_merge($tools, [
            ['name' => 'get_cart_summary', 'description' => "The signed-in buyer's cart: items, options, quantities, prices and subtotal.", 'inputSchema' => $obj([])],
            ['name' => 'list_my_orders', 'description' => "The signed-in buyer's 10 most recent orders: number, date, status, total, payment and items.", 'inputSchema' => $obj([])],
            ['name' => 'get_order_details',
             'description' => "One of the signed-in buyer's orders: items, price breakdown, payment, whether it can still be cancelled (and why not), return/refund requests and the return deadline.",
             'inputSchema' => $obj(['order_number' => ['type' => 'string', 'description' => 'e.g. "#000066" or "66"']], ['order_number'])],
            ['name' => 'get_order_tracking',
             'description' => "Shipment status of one of the signed-in buyer's orders: current location, sorting centers, transfers, failed delivery attempts and the latest tracking updates with times.",
             'inputSchema' => $obj(['order_number' => ['type' => 'string']], ['order_number'])],
            ['name' => 'estimate_shipping',
             'description' => "Shipping fee for a product to the signed-in buyer's default address, using the same calculation as checkout. Also says if the address is outside the delivery area.",
             'inputSchema' => $obj(['product_id' => $productRef, 'quantity' => ['type' => 'integer', 'description' => 'Default 1']], ['product_id'])],
            ['name' => 'get_my_addresses', 'description' => "The signed-in buyer's saved addresses (label, area, default, whether ALVY delivers there).", 'inputSchema' => $obj([])],
            ['name' => 'prepare_add_to_cart',
             'description' => 'Prepare adding a product to the cart. Nothing is added until the buyer presses Confirm in the chat. Only use when the buyer clearly asked to add it, after the option (if any) and quantity are known.',
             'inputSchema' => $obj(['product_id' => $productRef, 'variation' => ['type' => 'string', 'description' => 'Option name exactly as listed, when the product has options'], 'quantity' => ['type' => 'integer']], ['product_id'])],
            ['name' => 'prepare_cancel_order',
             'description' => 'Prepare cancelling one of the buyer\'s orders. Checks eligibility first; nothing is cancelled until the buyer presses Confirm. Only use when the buyer clearly asked to cancel and gave a reason.',
             'inputSchema' => $obj(['order_number' => ['type' => 'string'], 'reason' => ['type' => 'string', 'enum' => OrderController::CANCEL_REASONS]], ['order_number', 'reason'])],
            ['name' => 'prepare_support_request',
             'description' => 'Prepare a message to the ALVY support team about a problem. Sent only when the buyer presses Confirm. Use when the buyer wants a human, or the issue needs ALVY staff.',
             'inputSchema' => $obj([
                 'topic'        => ['type' => 'string', 'enum' => self::SUPPORT_TOPICS],
                 'message'      => ['type' => 'string', 'description' => "The buyer's problem in a few sentences, in their words"],
                 'order_number' => ['type' => 'string', 'description' => 'Related order, if any'],
             ], ['topic', 'message'])],
        ]);
    }

    /** Run one tool. Unknown tools, guests asking for private data and bad input all return an error result. */
    public function run(string $name, array $input, ?User $user): array
    {
        $private = ['get_cart_summary', 'list_my_orders', 'get_order_details', 'get_order_tracking', 'estimate_shipping', 'get_my_addresses',
                    'prepare_add_to_cart', 'prepare_cancel_order', 'prepare_support_request'];
        if (in_array($name, $private, true) && ! $user) {
            return ['error' => 'The buyer is not signed in. Ask them to sign in at /login first.'];
        }

        try {
            return match ($name) {
                'search_products'         => $this->searchProducts($input),
                'get_product_details'     => $this->productDetails($this->productId($input['product_id'] ?? null)),
                'compare_products'        => $this->compare((array) ($input['product_ids'] ?? [])),
                'get_active_promotions'   => $this->promotions(),
                'get_policy'              => $this->policy((string) ($input['topic'] ?? '')),
                'get_cart_summary'        => $this->cart($user),
                'list_my_orders'          => $this->myOrders($user),
                'get_order_details'       => $this->orderDetails($user, (string) ($input['order_number'] ?? '')),
                'get_order_tracking'      => $this->orderTracking($user, (string) ($input['order_number'] ?? '')),
                'estimate_shipping'       => $this->estimateShipping($user, $this->productId($input['product_id'] ?? null), (int) ($input['quantity'] ?? 1)),
                'get_my_addresses'        => $this->addresses($user),
                'prepare_add_to_cart'     => $this->prepareAddToCart($user, $this->productId($input['product_id'] ?? null), $input['variation'] ?? null, (int) ($input['quantity'] ?? 1)),
                'prepare_cancel_order'    => $this->prepareCancel($user, (string) ($input['order_number'] ?? ''), (string) ($input['reason'] ?? '')),
                'prepare_support_request' => $this->prepareSupport($user, (string) ($input['topic'] ?? ''), (string) ($input['message'] ?? ''), $input['order_number'] ?? null),
                default                   => ['error' => 'That tool does not exist. Only the listed tools can be used.'],
            };
        } catch (\Throwable $e) {
            report($e);
            return ['error' => 'This lookup failed on the server. Tell the buyer it could not be checked right now.'];
        }
    }

    // ── Product refs ───────────────────────────────────────────
    // The AI provider never sees database ids: products are "P1", "P2", … for this chat, kept in the session.

    public const REFS_KEY = 'assistant_product_refs';

    /** This chat's ref for a product ("P3"), given one the first time it is shown. */
    public function ref(int $id): string
    {
        $refs = session(self::REFS_KEY, []);
        if (($ref = array_search($id, $refs, true)) !== false) {
            return $ref;
        }
        $ref = 'P' . (session('assistant_product_ref_next', 0) + 1);
        session(['assistant_product_ref_next' => (int) substr($ref, 1)]);
        $refs[$ref] = $id;
        session([self::REFS_KEY => array_slice($refs, -300, null, true)]);
        return $ref;
    }

    /** The product a ref stands for in this chat (0 = unknown ref). */
    private function productId(mixed $ref): int
    {
        return (int) (session(self::REFS_KEY, [])[strtoupper(trim((string) $ref))] ?? 0);
    }

    // ── Products ───────────────────────────────────────────────

    private function visible()
    {
        return Product::published()->with(['seller.sellerApplication', 'category', 'variations'])->withAvg('reviews', 'rating')->withCount('reviews');
    }

    /** Words in a search that don't name a product ("may sapatos ba kayo" → "sapatos"). */
    private const SEARCH_FILLER = ['may', 'meron', 'ba', 'po', 'kayo', 'kau', 'ka', 'mga', 'na', 'ng', 'sa', 'yung', 'ung', 'ang', 'si',
        'ako', 'ko', 'nyo', 'niyo', 'lang', 'kahit', 'anong', 'ano', 'any', 'anything', 'random', 'item', 'items', 'product', 'products',
        'some', 'the', 'and', 'for', 'cheap', 'mura', 'murang', 'below', 'under', 'pesos', 'peso', 'php', 'have', 'you', 'do', 'is', 'there'];

    /** Tagalog / Taglish words → the English words product names use. */
    private const SEARCH_SYNONYMS = ['sapatos' => 'shoe', 'tsinelas' => 'slipper', 'sandalyas' => 'sandal', 'bota' => 'boots',
        'damit' => 'shirt', 'kamiseta' => 'shirt', 'pantalon' => 'pants', 'shorts' => 'short', 'palda' => 'skirt', 'bestida' => 'dress',
        'medyas' => 'socks', 'sumbrero' => 'hat', 'pitaka' => 'wallet', 'bagahe' => 'luggage', 'relo' => 'watch',
        'alahas' => 'jewelry', 'kwintas' => 'necklace', 'singsing' => 'ring', 'hikaw' => 'earring', 'pulseras' => 'bracelet',
        'pagkain' => 'food', 'inumin' => 'drink', 'kape' => 'coffee', 'tsokolate' => 'chocolate', 'laruan' => 'toy',
        'libro' => 'book', 'aklat' => 'book', 'kuwaderno' => 'notebook', 'lapis' => 'pencil', 'pabango' => 'perfume', 'sabon' => 'soap',
        'unan' => 'pillow', 'kumot' => 'blanket', 'upuan' => 'chair', 'mesa' => 'table', 'lamesa' => 'table', 'kusina' => 'kitchen',
        'kaldero' => 'pot', 'kawali' => 'pan', 'payong' => 'umbrella', 'salamin' => 'glasses', 'aso' => 'dog', 'pusa' => 'cat'];

    private function searchProducts(array $in): array
    {
        $query = Str::limit(trim((string) ($in['query'] ?? '')), 80, '');
        $words = collect(preg_split('/[^\pL\pN&-]+/u', mb_strtolower($query)))
            ->filter(fn ($w) => mb_strlen($w) >= 2 && ! in_array($w, self::SEARCH_FILLER, true));
        // Color words ("red", "pula") filter by the colors read from the pictures; the rest name the product.
        $colors = $words->map(fn ($w) => ProductColors::fromWord($w))->filter()->unique()->values();
        $words = $words->reject(fn ($w) => ProductColors::fromWord($w))
            ->map(fn ($w) => self::SEARCH_SYNONYMS[$w] ?? $w)->unique()->take(6)->values();
        $min = isset($in['min_price']) ? max(0, (float) $in['min_price']) : null;
        $max = isset($in['max_price']) ? max(0, (float) $in['max_price']) : null;
        $category = isset($in['category']) ? Str::limit((string) $in['category'], 60, '') : null;

        $base = fn () => $this->visible()
            ->when($category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('name', 'like', '%' . $category . '%')))
            ->when($colors->isNotEmpty(), fn ($q) => $q->where(fn ($any) => $colors->each(
                fn ($c) => $any->orWhere(fn ($one) => ProductSearch::whereColor($one, $c)))));

        // The price filter runs on the effective (sale) price after loading, so load enough to cover the whole catalogue.
        $matches = $words->isEmpty()
            ? $base()->limit(1000)->get()
            : $base()->where(fn ($q) => $words->each(fn ($w) => $q->orWhere('title', 'like', "%{$w}%")->orWhere('brand', 'like', "%{$w}%")
                ->orWhere('subcategory', 'like', "%{$w}%")->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$w}%"))))->limit(300)->get();

        $didYouMean = null;
        if ($matches->isEmpty() && $words->isNotEmpty()) {
            // Misspelled? Compare each word with the words in visible product titles.
            $pool = $base()->limit(400)->get();
            $matches = $pool->filter(fn (Product $p) => $words->contains(fn ($w) => collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($p->title . ' ' . $p->brand)))
                ->filter(fn ($t) => mb_strlen($t) >= 3)->contains(fn ($t) => levenshtein($w, $t) <= (mb_strlen($w) >= 4 ? max(1, intdiv(mb_strlen($w), 4)) : 0))))->values();   // short words: "toy" is not a misspelled "top"
            $didYouMean = $matches->isNotEmpty() ? 'No exact match; these are close spellings.' : null;
        }

        $matches = $matches
            ->filter(fn (Product $p) => ($min === null || $p->effective_price >= $min) && ($max === null || $p->effective_price <= $max))
            ->filter(fn (Product $p) => empty($in['on_sale_only']) || $p->effective_price < (float) $p->price);

        $relevance = fn (Product $p) => $words->sum(fn ($w) => str_contains(mb_strtolower($p->title), $w) ? 2 : 1);
        $matches = match ($in['sort'] ?? ($words->isEmpty() ? 'random' : 'relevance')) {
            'random'     => $matches->filter(fn (Product $p) => $p->totalStock() > 0)->shuffle(),
            'price_low'  => $matches->sortBy(fn ($p) => $p->effective_price),
            'price_high' => $matches->sortByDesc(fn ($p) => $p->effective_price),
            'top_rated'  => $matches->sortByDesc(fn ($p) => [(float) $p->rating_avg, $p->rating_count]),
            default      => $matches->sortByDesc(fn ($p) => [$relevance($p), $p->stock > 0 ? 1 : 0]),
        };

        $found = $matches->take(8)->values();
        $this->addCards($found->take(4));

        return array_filter([
            'products' => $found->map(fn (Product $p) => $this->summary($p))->all(),
            'note'     => $found->isEmpty() ? 'No products match. Suggest other words, a different budget, or browsing /categories.' : $didYouMean,
            'color_note' => $colors->isNotEmpty() ? 'Colors come from each product\'s main picture; mention the color options in get_product_details when there are any.' : null,
        ]);
    }

    private function summary(Product $p): array
    {
        $price = $p->effective_price;
        [$low, $high] = $p->priceRange();
        return array_filter([
            'ref'           => $this->ref($p->id),
            'name'          => $p->title,
            'price'         => $low !== $high ? $this->peso($low) . ' – ' . $this->peso($high) : $this->peso($price),
            'regular_price' => $price < (float) $p->price ? $this->peso((float) $p->price) : null,
            'stock'         => $p->totalStock() > 0 ? $p->totalStock() . ' in stock' : 'sold out',
            'rating'        => $p->rating_count ? $p->rating_avg . '/5 from ' . $p->rating_count . ' reviews' : 'no reviews yet',
            'seller'        => $p->seller?->storeName(),
            'category'      => $p->category?->name,
            'colors'        => $p->colors ? implode(', ', $p->colors) : null,
            'link'          => '/products/' . $p->slug,
        ], fn ($v) => $v !== null);
    }

    private function productDetails(int $id): array
    {
        $p = $this->visible()->find($id);
        if (! $p) {
            return ['error' => 'No product with that ref is available to buyers (it may be sold out, removed or unpublished). Search again for its ref.'];
        }
        $this->addCards(collect([$p]));
        $flash = $p->runningFlashSale();

        return $this->summary($p) + array_filter([
            'flash_sale'   => $flash ? ['price' => $this->peso($flash->flash_price), 'ends' => $flash->ends_at->format('M j, g:i A'), 'units_left' => $flash->remaining()] : null,
            'options'      => $p->variations->map(fn ($v) => ['name' => $v->name, 'price' => $this->peso($v->price > 0 ? $p->priceFor((float) $v->price) : $p->effective_price), 'stock' => (int) $v->stock])->all() ?: null,
            'option_type'  => $p->variation_labels,
            'condition'    => $p->condition,
            'brand'        => $p->brand,
            'specifications' => collect((array) $p->specs)->map(fn ($v, $k) => is_scalar($v) ? Str::limit((string) $v, 80) : null)->filter()->take(12)->all() ?: null,
            'weight_kg'    => $p->weight_kg ? (float) $p->weight_kg : null,
            'store_on_vacation' => $p->sellerOnVacation() ? 'This store is on vacation and not taking orders right now.' : null,
            'seller_description_untrusted' => $p->description ? Str::limit(strip_tags($p->description), 700) : null,
        ], fn ($v) => $v !== null);
    }

    private function compare(array $refs): array
    {
        $refs = collect($refs)->map(fn ($r) => strtoupper(trim((string) $r)))->filter()->unique()->take(4);
        if ($refs->count() < 2) {
            return ['error' => 'Give 2 to 4 product refs (from a search) to compare.'];
        }
        $ids = $refs->mapWithKeys(fn ($r) => [$r => $this->productId($r)]);
        $products = $this->visible()->whereIn('id', $ids->filter())->get();
        $this->addCards($products);
        $missing = $ids->reject(fn ($id) => $products->contains('id', $id))->keys()->values()->all();

        // Units sold (delivered or completed orders) and each seller's store-wide rating and followers.
        $sold = \App\Models\OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $products->pluck('id'))->whereIn('orders.status', [Order::DELIVERED, Order::COMPLETED])
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as n')->groupBy('order_items.product_id')->pluck('n', 'product_id');
        $sellerIds = $products->pluck('seller_id')->unique();
        $storeRatings = \App\Models\Review::join('products', 'products.id', '=', 'reviews.product_id')->whereIn('products.seller_id', $sellerIds)
            ->selectRaw('products.seller_id, AVG(reviews.rating) as avg, COUNT(*) as n')->groupBy('products.seller_id')->get()->keyBy('seller_id');
        $followers = \App\Models\StoreFollow::whereIn('seller_id', $sellerIds)->selectRaw('seller_id, COUNT(*) as n')->groupBy('seller_id')->pluck('n', 'seller_id');

        return array_filter([
            'products' => $products->map(function (Product $p) use ($sold, $storeRatings, $followers) {
                $app = $p->seller?->sellerApplication;
                $store = $storeRatings[$p->seller_id] ?? null;
                return $this->summary($p) + array_filter([
                    'units_sold'     => (int) ($sold[$p->id] ?? 0),
                    'options'        => $p->variations->pluck('name')->implode(', ') ?: null,
                    'specifications' => collect((array) $p->specs)->filter(fn ($v) => is_scalar($v))->map(fn ($v) => Str::limit((string) $v, 60))->take(6)->all() ?: null,
                    'condition'      => $p->condition,
                    'store'          => array_filter([
                        'rating'     => $store ? round((float) $store->avg, 1) . '/5 from ' . $store->n . ' reviews (all its products)' : 'no store reviews yet',
                        'followers'  => (int) ($followers[$p->seller_id] ?? 0),
                        'ships_from' => $app ? collect([$app->municipality, $app->province])->filter()->implode(', ') ?: null : null,
                        'on_vacation' => $p->sellerOnVacation() ? 'yes: not taking orders right now' : null,
                    ], fn ($v) => $v !== null),
                ], fn ($v) => $v !== null && $v !== []);
            })->values()->all(),
            'not_available' => $missing ? $missing : null,
            'how_to_judge'  => 'Weigh price (with any sale), product rating and number of reviews, units sold, store rating, stock, and where it ships from (nearer usually means cheaper, faster shipping). Few or no reviews means there is not enough to judge quality: say so.',
        ]);
    }

    private function promotions(): array
    {
        $flash = FlashSale::running()->with('product')->orderBy('ends_at')->take(6)->get()
            ->filter(fn ($f) => $f->product && $f->product->isPublished());
        $onSale = $this->visible()->get()->filter(fn (Product $p) => $p->effective_price < (float) $p->price)
            ->sortByDesc(fn ($p) => 1 - $p->effective_price / max(1, (float) $p->price))->take(6)->values();
        $this->addCards($onSale->take(4));

        return [
            'flash_sales' => $flash->map(fn ($f) => ['ref' => $this->ref($f->product_id), 'name' => $f->product->title, 'flash_price' => $this->peso($f->flash_price),
                'regular_price' => $this->peso((float) $f->product->price), 'ends' => $f->ends_at->format('M j, g:i A'), 'units_left' => $f->remaining(), 'link' => '/products/' . $f->product->slug])->values()->all(),
            'discounted_products' => $onSale->map(fn ($p) => $this->summary($p))->all(),
            'alvy_vouchers' => Voucher::running()->whereIn('scope', [Voucher::SCOPE_PLATFORM, Voucher::SCOPE_SHIPPING])->where('is_claimable', true)->take(6)->get()
                ->map(fn ($v) => array_filter(['code' => $v->code, 'name' => $v->name, 'type' => $v->scope === Voucher::SCOPE_SHIPPING ? 'shipping discount' : 'order discount',
                    'discount' => $v->discount_type === 'percent' ? rtrim(rtrim(number_format($v->discount_value, 2), '0'), '.') . '% off' : $this->peso($v->discount_value) . ' off',
                    'min_spend' => $v->min_spend ? $this->peso($v->min_spend) : null, 'ends' => $v->ends_at?->format('M j, Y'), 'claim_at' => '/my-vouchers']))->all(),
            'store_deals' => Promotion::running()->take(6)->get()->map(fn ($p) => ['name' => $p->name, 'deal' => $p->label()])->all(),
        ];
    }

    private function policy(string $topic): array
    {
        if (! in_array($topic, self::policyTopics(), true)) {
            return ['error' => 'Unknown policy topic.'];
        }
        $row = PlatformPolicy::where('key', $topic)->first();
        $html = $row?->content ?: PlatformPolicy::defaultContent($topic);
        if (! $html) {
            return ['error' => 'This policy has not been published yet.'];
        }
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>'], "\n", $html)))));

        return ['title' => $row?->title ?? Str::headline($topic), 'text' => Str::limit($text, 3500), 'link' => '/policies/' . $topic];
    }

    // ── The signed-in buyer's own records ──────────────────────

    private function cart(User $user): array
    {
        $lines = CartItem::where('user_id', $user->id)->with(['product', 'variation'])->get()->filter(fn ($l) => $l->product);

        return [
            'items' => $lines->map(fn ($l) => array_filter([
                'ref' => $this->ref($l->product_id), 'name' => $l->product->title, 'option' => $l->variation?->name, 'quantity' => $l->quantity,
                'unit_price' => $this->peso($l->variation && $l->variation->price > 0 ? $l->product->priceFor((float) $l->variation->price) : $l->product->effective_price),
                'available' => $l->product->isPublished() && $l->product->inStock() ? null : 'no longer available',
            ]))->values()->all(),
            'subtotal' => $this->peso($lines->sum(fn ($l) => $l->quantity * ($l->variation && $l->variation->price > 0 ? $l->product->priceFor((float) $l->variation->price) : $l->product->effective_price))),
            'note' => 'Shipping and vouchers are added at checkout.',
            'link' => '/cart',
        ];
    }

    public function myOrders(User $user): array
    {
        $orders = Order::where('user_id', $user->id)->with('items.product')->latest('id')->take(10)->get();

        return ['orders' => $orders->map(fn (Order $o) => [
            'number'  => $o->number(),
            'placed'  => $o->created_at->format('M j, Y'),
            'status'  => $o->buyerStatusLabel(),
            'total'   => $this->peso($o->total_price),
            'payment' => $o->paymentMethodLabel() . ' · ' . $o->payment_status,
            'items'   => $o->items->map(fn ($i) => ($i->product->title ?? 'Item') . ' ×' . $i->quantity)->implode(', '),
            'link'    => '/profile/orders/' . $o->id,
        ])->all(), 'note' => $orders->isEmpty() ? 'This buyer has no orders yet.' : null];
    }

    /** The buyer's own order from a typed number, or null (never another buyer's). */
    private function ownOrder(User $user, string $number): ?Order
    {
        $id = (int) ltrim(trim($number), '#0 ');
        return $id ? Order::where('user_id', $user->id)->find($id) : null;
    }

    private function orderDetails(User $user, string $number): array
    {
        $o = $this->ownOrder($user, $number);
        if (! $o) {
            return ['error' => 'No order with that number on this account.'];
        }
        $o->load(['items.product', 'returnRequests']);
        $deadline = $o->returnDeadline();

        return array_filter([
            'number'   => $o->number(),
            'placed'   => $o->created_at->format('M j, Y g:i A'),
            'status'   => $o->buyerStatusLabel(),
            'seller'   => $o->seller?->storeName(),
            'items'    => $o->items->map(fn ($i) => array_filter(['name' => $i->product->title ?? 'Item', 'option' => $i->variation_name, 'quantity' => $i->quantity,
                'price' => $this->peso($i->price), 'refunded' => $i->refunded_at ? $this->peso($i->refund_amount) : null]))->all(),
            'merchandise' => $this->peso($o->subtotal),
            'shipping_fee' => $this->peso($o->shipping_fee),
            'discounts' => ($d = (float) $o->promo_discount + (float) $o->seller_discount + (float) $o->platform_discount + (float) $o->shipping_discount) > 0 ? '−' . $this->peso($d) : null,
            'total'    => $this->peso($o->total_price),
            'payment'  => $o->paymentMethodLabel() . ' · ' . $o->payment_status,
            'can_cancel' => $o->isCancellableByBuyer() ? 'yes, from the order page or by asking here' : 'no: ' . ($o->isReadyForPickup() ? 'the seller already booked the pickup; message the seller' : 'the order is past the cancellable stage (' . $o->buyerStatusLabel() . ')'),
            'returns'  => $o->returnRequests->map(fn (ReturnRequest $r) => ['reason' => $r->reason, 'status' => $r->statusLabel(), 'refund' => $r->refund_amount ? $this->peso($r->refund_amount) : null])->all() ?: null,
            'return_window' => ! Order::returnsEnabled() ? 'returns are not available on ALVY yet; message the seller from the order page'
                : ($o->returnWindowOpen() ? 'open until ' . $deadline?->format('M j, Y g:i A') . ' (request it from the order page with photos)'
                : ($deadline ? 'closed on ' . $deadline->format('M j, Y') : 'opens once the order is delivered')),
            'cancellation_reason' => $o->cancellation_reason,
            'link'     => '/profile/orders/' . $o->id,
        ], fn ($v) => $v !== null);
    }

    private function orderTracking(User $user, string $number): array
    {
        $o = $this->ownOrder($user, $number);
        if (! $o) {
            return ['error' => 'No order with that number on this account.'];
        }
        $t = ShipmentProgress::for($o);
        $events = $t['events'];

        return array_filter([
            'number'          => $o->number(),
            'order_status'    => $o->buyerStatusLabel(),
            'shipment_status' => $t['status'],
            'last_confirmed_location' => $t['location'],
            'delivered_at'    => $t['delivered_at']?->format('M j, Y g:i A'),
            'sorting_centers_visited' => collect($t['hubs'])->pluck('name')->filter()->unique()->values()->all() ?: null,
            'transfers'       => $t['transfers']->map(fn ($tr) => \App\Support\SortingCenters::displayName($tr->fromSortingCenter) . ' → ' . \App\Support\SortingCenters::displayName($tr->toSortingCenter) . ': ' . $tr->statusLabel())->all() ?: null,
            'failed_delivery_attempts' => $t['attempts']->map(fn ($a) => $a->failure_reason . ' (' . $a->updated_at->format('M j') . ')')->all() ?: null,
            'under_review'    => $t['under_review'] ? 'ALVY logistics is reviewing this shipment.' : null,
            'latest_updates'  => $events->reverse()->take(5)->map(fn ($e) => $e->label() . ' — ' . $e->occurred_at->format('M j, g:i A'))->values()->all(),
            'no_updates_yet'  => $events->isEmpty() ? 'No tracking event has been recorded yet.' : null,
            'expected_next_steps' => $t['expected'] ?: null,
            'estimated_delivery' => 'ALVY does not record delivery date estimates; do not give one.',
            'link'            => '/profile/orders/' . $o->id,
        ], fn ($v) => $v !== null);
    }

    private function estimateShipping(User $user, int $productId, int $qty): array
    {
        $p = $this->visible()->find($productId);
        if (! $p) {
            return ['error' => 'That product is not available.'];
        }
        $address = $user->addresses()->orderByDesc('is_default')->first();
        if (! $address) {
            return ['error' => 'The buyer has no saved address. They can add one at /profile/addresses.'];
        }
        if ($why = $this->shipping->unserviceableReason($address->municipality_code, $address->province_code)) {
            return ['address' => $address->label . ' (' . $address->city . ')', 'deliverable' => false, 'reason' => $why];
        }
        $qty = max(1, min(99, $qty));
        $line = (object) ['quantity' => $qty, 'product' => $p];
        $quote = $this->shipping->quote(collect([$p->seller_id => ['items' => collect([$line])]]),
            $this->shipping->zoneOf($address->municipality_code ?: $address->province_code), $address->municipality_code)[$p->seller_id];

        return ['product' => $p->title, 'quantity' => $qty, 'address' => Str::limit((string) $address->label, 30) . ' (' . $address->city . ')',
            'shipping_fee' => $this->peso($quote['fee']), 'route' => $quote['route'], 'chargeable_weight_kg' => $quote['chargeable'],
            'note' => 'Same calculation as checkout; vouchers can lower it. Items from one store ship together.'];
    }

    private function addresses(User $user): array
    {
        return ['addresses' => $user->addresses()->orderByDesc('is_default')->get()->map(fn ($a) => [
            // City and province only: no street, house number, barangay, or the recipient's name and phone.
            'label' => Str::limit((string) $a->label, 30), 'area' => collect([$a->city, $a->province])->filter()->implode(', '), 'default' => (bool) $a->is_default,
            'deliverable' => $this->shipping->unserviceableReason($a->municipality_code, $a->province_code) === null,
        ])->all(), 'manage_at' => '/profile/addresses'];
    }

    // ── Actions: prepared here, run only after the buyer confirms ──

    private function prepareAddToCart(User $user, int $productId, ?string $variation, int $qty): array
    {
        $p = $this->visible()->find($productId);
        if (! $p) {
            return ['error' => 'That product is not available to buy.'];
        }
        if ($p->sellerOnVacation()) {
            return ['error' => 'This store is on vacation and not taking orders.'];
        }
        $option = null;
        if ($p->variations->isNotEmpty()) {
            $option = $p->variations->first(fn ($v) => $variation !== null && mb_strtolower(trim($v->name)) === mb_strtolower(trim($variation)));
            if (! $option) {
                return ['error' => 'Ask the buyer which option they want first.', 'options' => $p->variations->map(fn ($v) => $v->name . ($v->stock > 0 ? '' : ' (sold out)'))->all()];
            }
        }
        $stock = $option ? (int) $option->stock : $p->totalStock();
        $qty = max(1, $qty);
        if ($stock < $qty) {
            return ['error' => $stock > 0 ? "Only {$stock} left." : 'Sold out.'];
        }

        $label = 'Add ' . $qty . ' × "' . $p->title . '"' . ($option ? ' (' . $option->name . ')' : '') . ' to your cart';
        return $this->action('add_to_cart', $label, ['product_id' => $p->id, 'variation_id' => $option?->id, 'quantity' => $qty]);
    }

    private function prepareCancel(User $user, string $number, string $reason): array
    {
        $o = $this->ownOrder($user, $number);
        if (! $o) {
            return ['error' => 'No order with that number on this account.'];
        }
        if (! $o->isCancellableByBuyer()) {
            return ['error' => 'Not eligible: ' . ($o->isReadyForPickup() ? 'the seller has booked the pickup, so it can no longer be cancelled. Suggest messaging the seller.' : 'the order is ' . $o->buyerStatusLabel() . ' and can no longer be cancelled.')];
        }
        if (! in_array($reason, OrderController::CANCEL_REASONS, true)) {
            return ['error' => 'Ask the buyer for a reason from this list.', 'reasons' => OrderController::CANCEL_REASONS];
        }

        return $this->action('cancel_order', 'Cancel order ' . $o->number() . ' (' . $reason . ')', ['order_id' => $o->id, 'reason' => $reason])
            + ['consequence' => 'The order is cancelled for good. ' . ($o->isPaidOnline() ? 'The online payment is refunded.' : ($o->isGcashReceipt() ? 'For a GCash payment, the seller returns the money.' : 'Nothing was paid yet (COD).'))];
    }

    private function prepareSupport(User $user, string $topic, string $message, ?string $orderNumber): array
    {
        $message = trim(Str::limit(strip_tags($message), 1000, ''));
        if (! in_array($topic, self::SUPPORT_TOPICS, true) || mb_strlen($message) < 10) {
            return ['error' => 'Ask the buyer to describe the problem in a sentence or two first.'];
        }
        $order = $orderNumber ? $this->ownOrder($user, $orderNumber) : null;
        if ($orderNumber && ! $order) {
            return ['error' => 'No order with that number on this account.'];
        }

        return $this->action('support_request', 'Send this to ALVY support: "' . Str::limit($message, 140) . '"', ['topic' => $topic, 'message' => $message, 'order_id' => $order?->id]);
    }

    /** A prepared action the buyer must confirm in the chat; kept in the session with a one-time token. */
    private function action(string $type, string $label, array $params): array
    {
        $token = Str::random(32);
        $pending = collect(session(ShopAssistant::ACTIONS_KEY, []))->filter(fn ($a) => $a['expires'] > now()->timestamp)->take(-4)->all();
        $pending[$token] = ['type' => $type, 'label' => $label, 'params' => $params, 'expires' => now()->addMinutes(15)->timestamp];
        session([ShopAssistant::ACTIONS_KEY => $pending]);
        $this->actions[] = ['token' => $token, 'label' => $label];

        return ['prepared' => true, 'needs_buyer_confirmation' => 'Nothing has happened yet. Tell the buyer to press Confirm under your reply if this is right.', 'summary' => $label];
    }

    private function addCards(Collection $products): void
    {
        foreach ($products as $p) {
            $this->cards[$p->id] = ['name' => $p->title, 'price' => $this->peso($p->effective_price),
                'was' => $p->effective_price < (float) $p->price ? $this->peso((float) $p->price) : null,
                'image' => $p->primary_image, 'link' => '/products/' . $p->slug, 'stock' => $p->totalStock() > 0 ? null : 'Sold out'];
        }
    }

    private function peso(float|string|null $v): string
    {
        return '₱' . number_format((float) $v, 2);
    }

    /** Store facts for the system prompt (from the real settings). */
    public static function storeFacts(): string
    {
        $gcash = CheckoutController::gcashAccount();
        $area = ServiceArea::enabled() ? ServiceArea::label() : 'the Philippines';

        return "- Delivery area: ALVY delivers only within {$area}. Addresses outside it can't be used at checkout.\n"
            . '- Payment methods: Cash on delivery (pay the rider when the parcel arrives)'
            . ($gcash ? '; GCash (send the total to ALVY\'s GCash number shown at checkout and upload the receipt screenshot; the seller checks it before shipping, so the payment shows "For verification" until then)' : '') . ".\n"
            . "- Shipping fee: from the parcel's weight and the distance between the seller and the buyer's address, shown at checkout. Vouchers can lower it.\n"
            . '- Order steps: Pending (seller hasn\'t accepted) → Processing (accepted, being packed) → Shipped (picked up; it passes through ALVY sorting centers, possibly a transfer between hubs) → Out for delivery → Delivered → Completed (the buyer taps "Order Received", or after ' . Order::RETURN_DAYS . " days).\n"
            . "- Cancelling: until the seller books the pickup. After that, message the seller.\n"
            . (Order::returnsEnabled()
                ? '- Returns/refunds: from the order page within ' . Order::RETURN_DAYS . " days of delivery, with 1–5 photos. The seller approves or rejects; if rejected the buyer can escalate to ALVY. COD refunds go to the buyer's refund account; online payments go back to the payment method.\n"
                : "- Returns/refunds: not available on ALVY yet. If something is wrong, the buyer messages the seller from the order page.\n")
            . "- Pages: My Purchases /profile/orders · Cart /cart · Addresses /profile/addresses · Accounts & Security /profile/security (password, email, phone) · Profile /profile/personal-info · Vouchers /my-vouchers · Forgot password /forgot-password.";
    }
}
