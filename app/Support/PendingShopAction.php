<?php

namespace App\Support;

use App\Http\Controllers\CartController;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Add to cart, Buy now or the wishlist heart pressed while signed out: the shop remembers it, sends the
 * buyer to log in (or sign up), and does it right after they are signed in, then takes them back to the
 * product (or to checkout for Buy now).
 */
class PendingShopAction
{
    private const KEY = 'pending_shop_action';
    private const RETURN = 'pending_shop_return';
    private const DONE = 'pending_shop_done';

    /** The routes this covers. */
    public const ROUTES = ['cart.store', 'wishlist.toggle'];

    /** Remembers the action of a signed-out request; returns where to go after logging in. */
    public static function remember(Request $request): string
    {
        $route = $request->route();
        $isCart = $route?->getName() === 'cart.store';
        $productId = (int) ($route?->parameter($isCart ? 'bookId' : 'productId'));
        $buyNow = $isCart && $request->input('action') === 'buy_now';

        session()->put(self::KEY, [
            'type'         => $isCart ? 'cart' : 'wishlist',
            'product_id'   => $productId,
            'quantity'     => max(1, (int) $request->input('quantity', 1)),
            'variation_id' => $request->input('variation_id') ? (int) $request->input('variation_id') : null,
            'buy_now'      => $buyNow,
        ]);

        $product = Product::find($productId);
        session()->put(self::RETURN, $buyNow ? route('checkout.index', ['buy_now' => 1])
            : ($product ? route('products.show', $product->slug ?? $product->id) : url()->previous()));

        return $product?->title ?? 'this item';
    }

    /**
     * Where a buyer goes after logging in when something was pending: back to the product (or to checkout for
     * Buy now), with the welcome and what happened to the item. null when nothing was pending.
     */
    public static function redirect(string $welcome): ?\Illuminate\Http\RedirectResponse
    {
        $back = session()->pull(self::RETURN);
        if (! $back) {
            return null;
        }
        $done = session()->pull(self::DONE, []);
        if (! empty($done['error'])) {
            return redirect($back)->with('success', $welcome)->with('error', $done['error']);
        }

        return redirect($back)->with('success', trim($welcome . ' ' . ($done['ok'] ?? '')));
    }

    /** Right after signing in: do what was pressed (buyers only), once. */
    public static function run(User $user): void
    {
        $pending = session()->pull(self::KEY);
        if (! $pending || ! $user->isBuyer() || ! $user->isActive()) {
            return;
        }

        if ($pending['type'] === 'wishlist') {
            if ($product = Product::find($pending['product_id'])) {
                WishlistItem::firstOrCreate(['user_id' => $user->id, 'product_id' => $product->id]);
                session()->put(self::DONE, ['ok' => '"' . $product->title . '" was added to your wishlist.']);
            }
            return;
        }

        // The cart's own rules (published, stock, options, purchase limits) and its messages.
        $request = Request::create('/cart/' . $pending['product_id'], 'POST', array_filter([
            'quantity'     => $pending['quantity'],
            'variation_id' => $pending['variation_id'],
            'action'       => $pending['buy_now'] ? 'buy_now' : null,
        ]));
        $request->setLaravelSession(session()->driver());
        $request->setUserResolver(fn () => $user);
        try {
            app()->call([app(CartController::class), 'store'], ['request' => $request, 'bookId' => $pending['product_id']]);
            // The cart's own message (added, or why not: sold out, pick an option, over the limit…).
            $error = session()->get('error');
            session()->put(self::DONE, $error ? ['error' => $error] : ['ok' => (string) session()->get('success', '')]);
            session()->forget(['success', 'error']);
        } catch (ValidationException $e) {
            session()->put(self::DONE, ['error' => collect($e->errors())->flatten()->first() ?? 'That item could not be added to your cart.']);
        }
    }
}
