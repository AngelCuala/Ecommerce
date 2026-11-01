<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies vouchers at checkout. Per checkout the buyer can stack:
 *  - one store voucher per store, on that store's items (the seller pays for it),
 *  - one ALVY voucher, on what is left after store vouchers (ALVY pays for it),
 *  - one shipping voucher, on the shipping fees (ALVY pays for it).
 * Choosing another voucher of the same kind replaces the first. Every amount is worked out
 * here on the server; the checkout page only shows it.
 *
 * Before any voucher, each store's running promotions (bundle deals and buy-X-get-Y) come off
 * that store's items; the seller pays for them. A store voucher limited to some products only
 * counts (and discounts) those products.
 */
class VoucherService
{
    /**
     * Checkout lines grouped by seller: [sellerId => ['name' => ..., 'subtotal' => ..., 'items' => Collection]].
     * Products listed by ALVY itself (no seller) are grouped under 0 and can't take store vouchers.
     */
    public function groupBySeller(Collection $items): Collection
    {
        $sellerIds  = $items->map(fn ($i) => (int) ($i->product->seller_id ?? 0))->filter()->unique()->values();
        $promotions = $sellerIds->isEmpty() ? collect() : Promotion::running()->whereIn('seller_id', $sellerIds)->orderBy('id')->get()->groupBy('seller_id');

        return $items->groupBy(fn ($i) => (int) ($i->product->seller_id ?? 0))
            ->map(function ($group, $sellerId) use ($promotions) {
                $gross = round($group->sum(fn ($i) => $i->subtotal()), 2);
                [$promoLines, $promoLabels] = $this->promotionDiscounts($group, $promotions->get($sellerId, collect()));
                $promo = round(array_sum($promoLines), 2);

                return [
                    'name'         => $sellerId ? ($group->first()->product->seller?->storeName() ?? 'Seller') : 'ALVY',
                    'gross'        => $gross,
                    'promo'        => $promo,
                    'promo_lines'  => $promoLines,
                    'promo_labels' => $promoLabels,
                    // What the store's items cost after its promotions; vouchers work on this.
                    'subtotal'     => round($gross - $promo, 2),
                    'items'        => $group,
                ];
            });
    }

    /**
     * Discounts from a store's running promotions: [[lineId => amount], [labels]].
     * Bundle: at least min_qty units of the listed products (any mix of options) → % off them.
     * Buy X get Y: every min_qty units of the listed product → free_qty of the free product at ₱0.
     * A line gets at most one promotion.
     */
    public function promotionDiscounts(Collection $lines, Collection $promotions): array
    {
        $off = [];
        $labels = [];
        foreach ($promotions as $promo) {
            $ids = array_map('intval', (array) $promo->product_ids);
            $buyLines = $lines->filter(fn ($l) => in_array((int) $l->product_id, $ids, true));

            if ($promo->type === Promotion::BUNDLE) {
                $eligible = $buyLines->filter(fn ($l) => ! isset($off[$l->id]));
                if ($eligible->sum('quantity') >= $promo->min_qty && $promo->discount_percent > 0) {
                    foreach ($eligible as $l) {
                        $off[$l->id] = round($l->subtotal() * $promo->discount_percent / 100, 2);
                    }
                    $labels[] = $promo->name . ' (' . $promo->label() . ')';
                }
                continue;
            }

            // Buy X get Y: when the free item is the same product, a set needs X + Y units.
            $same = in_array((int) $promo->free_product_id, $ids, true);
            $sets = intdiv((int) $buyLines->sum('quantity'), max(1, $promo->min_qty + ($same ? $promo->free_qty : 0)));
            $freeUnits = $sets * max(1, $promo->free_qty);
            $given = 0.0;
            foreach ($lines->filter(fn ($l) => (int) $l->product_id === (int) $promo->free_product_id && ! isset($off[$l->id]))->sortBy(fn ($l) => $l->unit_price) as $l) {
                if ($freeUnits <= 0) {
                    break;
                }
                $n = min($freeUnits, (int) $l->quantity);
                $off[$l->id] = round(min($l->subtotal(), $n * $l->unit_price), 2);
                $given += $off[$l->id];
                $freeUnits -= $n;
            }
            if ($given > 0) {
                $labels[] = $promo->name . ' (' . $promo->label() . ')';
            }
        }

        return [$off, $labels];
    }

    /** What a store voucher counts and discounts: the store's items after promotions, or only its chosen products. */
    public function voucherBase(Voucher $voucher, array $group): float
    {
        if (empty($voucher->product_ids)) {
            return $group['subtotal'];
        }
        return round($group['items']->filter(fn ($l) => $voucher->appliesToProduct((int) $l->product_id))
            ->sum(fn ($l) => $l->subtotal() - ($group['promo_lines'][$l->id] ?? 0)), 2);
    }

    /**
     * Vouchers the buyer can pick on this checkout, and the cheapest combination.
     * Code-only vouchers aren't listed; they work when typed.
     */
    public function options(Collection $items, User $user, float $shippingTotal = 0): array
    {
        $groups   = $this->groupBySeller($items);
        $subtotal = round($groups->sum('subtotal'), 2);
        $used     = $this->usesByUser($user);

        $describe = fn (Voucher $v, float $base, float $discountBase = null) => [
            'code'      => $v->code,
            'name'      => $v->name,
            'label'     => $v->discountLabel(),
            'min_spend' => $v->min_spend,
            'ends_at'   => $v->ends_at?->format('M d, Y g:i A'),
            'type'      => $v->discount_type,
            'value'     => $v->discount_value,
            'max'       => $v->max_discount,
            'discount'  => $v->discountFor($discountBase ?? $base),
            'reason'    => $this->unusableReason($v, $base, $used),
        ];

        $sellerVouchers = Voucher::running()->where('is_claimable', true)->where('scope', Voucher::SCOPE_SELLER)
            ->whereIn('seller_id', $groups->keys()->filter())
            ->orderBy('min_spend')->get()->groupBy('seller_id');

        $sellerOptions = [];
        foreach ($groups as $sellerId => $group) {
            $sellerOptions[$sellerId] = [
                'name'     => $group['name'],
                'subtotal' => $group['subtotal'],
                'vouchers' => collect($sellerVouchers->get($sellerId, []))->map(fn ($v) => $this->describeStoreVoucher($describe, $v, $group))->values()->all(),
            ];
        }

        $platformVouchers = Voucher::running()->where('is_claimable', true)->where('scope', Voucher::SCOPE_PLATFORM)->orderBy('min_spend')->get();
        $shippingVouchers = Voucher::running()->where('is_claimable', true)->where('scope', Voucher::SCOPE_SHIPPING)->orderBy('min_spend')->get();

        $best = $this->best($groups, $subtotal, $sellerVouchers, $platformVouchers, $used);
        $afterVouchers = round($subtotal - $best['discount'], 2);

        return [
            'platform' => $platformVouchers->map(fn ($v) => $describe($v, $subtotal))->values()->all(),
            'shipping' => $shippingVouchers->map(fn ($v) => $describe($v, $afterVouchers, $shippingTotal))->values()->all(),
            'sellers'  => $sellerOptions,
            'best'     => $best + ['shipping' => $this->bestShipping($shippingVouchers, $afterVouchers, $shippingTotal, $used)],
        ];
    }

    /** A store voucher limited to products that aren't in this order can't be used. */
    private function describeStoreVoucher(callable $describe, Voucher $v, array $group): array
    {
        $out = $describe($v, $this->voucherBase($v, $group));
        if (! empty($v->product_ids) && $this->voucherBase($v, $group) <= 0) {
            $out['reason'] = 'only for selected products that are not in this order.';
            $out['discount'] = 0.0;
        }
        return $out;
    }

    /**
     * The store + ALVY vouchers that make the order cheapest, picked automatically at checkout.
     * Every combination is tried (one store voucher per seller or none, then the best ALVY
     * voucher on what is left), because a bigger store discount can push the order under an
     * ALVY voucher's minimum spend.
     *
     * @return array{platform: ?string, sellers: array<int,string>, discount: float}
     */
    private function best(Collection $groups, float $subtotal, Collection $sellerVouchers, Collection $platformVouchers, Collection $used): array
    {
        $choices = [];
        foreach ($groups as $sellerId => $group) {
            $usable = collect($sellerVouchers->get($sellerId, []))
                ->filter(fn ($v) => $this->voucherBase($v, $group) > 0 && $this->unusableReason($v, $this->voucherBase($v, $group), $used) === null)
                ->map(fn ($v) => ['code' => $v->code, 'discount' => $v->discountFor($this->voucherBase($v, $group))])
                ->values()->all();
            if ($sellerId && $usable) {
                $choices[$sellerId] = array_merge([['code' => null, 'discount' => 0.0]], $usable);
            }
        }

        $combos = [[]];
        foreach ($choices as $sellerId => $options) {
            $next = [];
            foreach ($combos as $combo) {
                foreach ($options as $option) {
                    $next[] = $combo + [$sellerId => $option];
                }
            }
            $combos = $next;
            if (count($combos) > 5000) {
                // Very many stores with vouchers: just take each store's biggest discount.
                $combos = [collect($choices)->map(fn ($opts) => collect($opts)->sortByDesc('discount')->first())->all()];
                break;
            }
        }

        $best = ['platform' => null, 'sellers' => [], 'discount' => 0.0];
        foreach ($combos as $combo) {
            $storeOff = array_sum(array_column($combo, 'discount'));
            $base     = round($subtotal - $storeOff, 2);

            $platformCode = null;
            $platformOff  = 0.0;
            foreach ($platformVouchers as $v) {
                if ($this->unusableReason($v, $base, $used) === null && $v->discountFor($base) > $platformOff) {
                    $platformCode = $v->code;
                    $platformOff  = $v->discountFor($base);
                }
            }

            $total = round($storeOff + $platformOff, 2);
            if ($total > $best['discount']) {
                $best = [
                    'platform' => $platformCode,
                    'sellers'  => array_filter(array_map(fn ($o) => $o['code'], $combo)),
                    'discount' => $total,
                ];
            }
        }

        return $best;
    }

    private function bestShipping(Collection $vouchers, float $merchandise, float $shippingTotal, Collection $used): ?string
    {
        if ($shippingTotal <= 0) {
            return null;
        }
        return $vouchers->filter(fn ($v) => $this->unusableReason($v, $merchandise, $used) === null)
            ->sortByDesc(fn ($v) => $v->discountFor($shippingTotal))->first()?->code;
    }

    /**
     * Sort a typed code into the right slot (store / ALVY / shipping), so code-only vouchers work.
     * Returns [platformCode, sellerCodes, shippingCode].
     */
    public function placeTypedCode(?string $typed, ?string $platformCode, array $sellerCodes, ?string $shippingCode, Collection $items): array
    {
        $code = strtoupper(trim((string) $typed));
        if ($code === '') {
            return [$platformCode, $sellerCodes, $shippingCode];
        }

        $voucher = Voucher::where('code', $code)->first();
        if (! $voucher) {
            throw ValidationException::withMessages(['voucher_code' => "Voucher code {$code} doesn't exist. Please check the code."]);
        }

        if ($voucher->scope === Voucher::SCOPE_SELLER) {
            if (! $items->contains(fn ($i) => (int) $i->product->seller_id === (int) $voucher->seller_id)) {
                throw ValidationException::withMessages(['voucher_code' => "Voucher {$code} is for another store's products."]);
            }
            $sellerCodes[$voucher->seller_id] = $code;
        } elseif ($voucher->isShipping()) {
            $shippingCode = $code;
        } else {
            $platformCode = $code;
        }

        return [$platformCode, $sellerCodes, $shippingCode];
    }

    /**
     * Work out the discounts for the chosen codes.
     *
     * Strict (placing the order): a code that can't be used throws a validation error naming its
     * field ("platform_voucher", "seller_vouchers.{id}", "shipping_voucher").
     * Lenient (checkout preview): such a code is dropped and explained in 'notices' instead.
     *
     * @param array<int,string|null> $sellerCodes [sellerId => code]
     * @param array<int,float>       $shippingFees [sellerId => fee]
     */
    public function quote(Collection $items, User $user, ?string $platformCode, array $sellerCodes,
                          ?string $shippingCode = null, array $shippingFees = [], bool $strict = true): array
    {
        $groups  = $this->groupBySeller($items);
        $used    = $this->usesByUser($user);
        $notices = [];

        $fail = function (string $field, string $message) use ($strict, &$notices) {
            if ($strict) {
                throw ValidationException::withMessages([$field => $message]);
            }
            $notices[$field] = $message;
        };

        $sellerVouchers = [];
        $sellerDiscount = [];
        foreach ($sellerCodes as $sellerId => $code) {
            $code = strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }
            $field   = "seller_vouchers.{$sellerId}";
            $group   = $groups->get((int) $sellerId);
            $voucher = Voucher::where('code', $code)->first();

            if (! $group || ! $voucher || $voucher->scope !== Voucher::SCOPE_SELLER || (int) $voucher->seller_id !== (int) $sellerId) {
                $fail($field, "Voucher {$code} can't be used on this store's items.");
                continue;
            }
            $base = $this->voucherBase($voucher, $group);
            if (! empty($voucher->product_ids) && $base <= 0) {
                $fail($field, "{$code} can't be used: it is only for selected products that are not in this order.");
                continue;
            }
            if ($reason = $this->unusableReason($voucher, $base, $used, true)) {
                $fail($field, "{$code} was removed: {$reason}");
                continue;
            }
            $sellerVouchers[$sellerId] = $voucher;
            $sellerDiscount[$sellerId] = $voucher->discountFor($base);
        }

        $subtotal    = round($groups->sum('subtotal'), 2);
        $afterSeller = round($subtotal - array_sum($sellerDiscount), 2);

        $platformVoucher  = null;
        $platformDiscount = 0.0;
        $platformCode     = strtoupper(trim((string) $platformCode));
        if ($platformCode !== '') {
            $voucher = Voucher::where('code', $platformCode)->first();
            if (! $voucher) {
                $fail('platform_voucher', "Voucher code {$platformCode} doesn't exist. Please check the code.");
            } elseif (! $voucher->isPlatform()) {
                $fail('platform_voucher', "Voucher {$platformCode} is not an ALVY voucher.");
            } elseif ($reason = $this->unusableReason($voucher, $afterSeller, $used, true)) {
                $fail('platform_voucher', "{$platformCode} was removed: {$reason}");
            } else {
                $platformVoucher  = $voucher;
                $platformDiscount = $voucher->discountFor($afterSeller);
            }
        }

        $merchandise      = round($afterSeller - $platformDiscount, 2);
        $shippingTotal    = round(array_sum($shippingFees), 2);
        $shippingVoucher  = null;
        $shippingDiscount = 0.0;
        $shippingCode     = strtoupper(trim((string) $shippingCode));
        if ($shippingCode !== '') {
            $voucher = Voucher::where('code', $shippingCode)->first();
            if (! $voucher || ! $voucher->isShipping()) {
                $fail('shipping_voucher', "Voucher {$shippingCode} is not a shipping voucher.");
            } elseif ($reason = $this->unusableReason($voucher, $merchandise, $used, true)) {
                $fail('shipping_voucher', "{$shippingCode} was removed: {$reason}");
            } else {
                $shippingVoucher  = $voucher;
                $shippingDiscount = $voucher->discountFor($shippingTotal);
            }
        }

        return [
            'groups'            => $groups,
            'subtotal'          => $subtotal,                                  // after promotions
            'merchandise'       => round($groups->sum('gross'), 2),             // before promotions
            'promo_discount'    => round($groups->sum('promo'), 2),
            'seller_vouchers'   => $sellerVouchers,
            'seller_discounts'  => $sellerDiscount,
            'seller_discount'   => round(array_sum($sellerDiscount), 2),
            'platform_voucher'  => $platformVoucher,
            'platform_discount' => $platformDiscount,
            'shipping_fees'     => $shippingFees,
            'shipping_total'    => $shippingTotal,
            'shipping_voucher'  => $shippingVoucher,
            'shipping_discount' => $shippingDiscount,
            // Merchandise never goes below ₱0; shipping is added on top.
            'total'             => round(max(0, $merchandise) + max(0, $shippingTotal - $shippingDiscount), 2),
            'notices'           => $notices,
        ];
    }

    /**
     * Each line's share of the vouchers: [lineId => ['seller' => x, 'platform' => y]].
     * Shares are proportional to the line total; rounding left-overs go on the last line.
     */
    public function lineShares(array $quote): array
    {
        $shares = [];
        $allLines = collect();

        $promo = [];
        foreach ($quote['groups'] as $sellerId => $group) {
            $lines = $group['items'];
            $allLines = $allLines->concat($lines);
            foreach ($lines as $l) {
                $promo[$l->id] = $group['promo_lines'][$l->id] ?? 0.0;
            }
            // A store voucher for chosen products is shared over those products only.
            $voucher = $quote['seller_vouchers'][$sellerId] ?? null;
            $eligible = $voucher ? $lines->filter(fn ($l) => $voucher->appliesToProduct((int) $l->product_id))->values() : $lines;
            $weights = $eligible->mapWithKeys(fn ($l) => [$l->id => $l->subtotal() - $promo[$l->id]]);
            foreach ($this->split($quote['seller_discounts'][$sellerId] ?? 0, $eligible, $weights) as $id => $amount) {
                $shares[$id]['seller'] = $amount;
            }
        }

        // A8: the ALVY voucher is split across the stores in proportion to each store's merchandise
        // subtotal, rounded to the centavo, with any rounding remainder given to the largest store;
        // within a store, across its lines by price.
        $platform = round((float) $quote['platform_discount'], 2);
        if ($platform > 0) {
            $groups = collect($quote['groups']);
            $total  = max(0.01, (float) $groups->sum('gross'));
            $storeShare = $groups->map(fn ($g) => round($platform * (float) $g['gross'] / $total, 2));
            $largest = $groups->sortByDesc('gross')->keys()->first();
            $storeShare[$largest] = round($storeShare[$largest] + $platform - $storeShare->sum(), 2);
            foreach ($quote['groups'] as $sellerId => $group) {
                foreach ($this->split($storeShare[$sellerId], $group['items']) as $id => $amount) {
                    $shares[$id]['platform'] = $amount;
                }
            }
        }

        foreach ($allLines as $line) {
            $shares[$line->id] = ($shares[$line->id] ?? []) + ['seller' => 0.0, 'platform' => 0.0];
            $shares[$line->id]['promo'] = $promo[$line->id];
        }

        return $shares;
    }

    /** Each store's share of the shipping voucher, in proportion to its shipping fee. */
    public function shippingShares(array $quote): array
    {
        $fees  = collect($quote['shipping_fees']);
        $total = max(0.01, $fees->sum());
        $left  = $quote['shipping_discount'];
        $out   = [];
        $keys  = $fees->keys()->values();
        foreach ($keys as $idx => $sellerId) {
            $share = $idx === $keys->count() - 1 ? round($left, 2) : round($quote['shipping_discount'] * $fees[$sellerId] / $total, 2);
            $out[$sellerId] = $share;
            $left -= $share;
        }
        return $out;
    }

    /**
     * Record the uses (call inside the order transaction). Fails if a voucher ran out meanwhile.
     *
     * @param array<int,Order> $ordersBySeller the checkout's orders, one per seller
     */
    public function redeem(array $ordersBySeller, User $user, array $quote): void
    {
        $first = reset($ordersBySeller);

        $uses = [];
        foreach ($quote['seller_vouchers'] as $sellerId => $voucher) {
            $uses[] = [$voucher, $quote['seller_discounts'][$sellerId], "seller_vouchers.{$sellerId}", $ordersBySeller[$sellerId] ?? $first];
        }
        if ($quote['platform_voucher']) {
            $uses[] = [$quote['platform_voucher'], $quote['platform_discount'], 'platform_voucher', $first];
        }
        if ($quote['shipping_voucher']) {
            $uses[] = [$quote['shipping_voucher'], $quote['shipping_discount'], 'shipping_voucher', $first];
        }

        foreach ($uses as [$voucher, $amount, $field, $order]) {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->first();
            if ($locked->usage_limit !== null && $locked->used_count >= $locked->usage_limit) {
                throw ValidationException::withMessages([$field => "Voucher {$locked->code} has just run out. Please choose another one."]);
            }
            $myUses = VoucherRedemption::where('voucher_id', $locked->id)->where('user_id', $user->id)->count();
            if ($myUses >= $locked->per_user_limit) {
                throw ValidationException::withMessages([$field => "You have already used voucher {$locked->code}."]);
            }
            $locked->increment('used_count');

            VoucherRedemption::create([
                'voucher_id' => $locked->id, 'user_id' => $user->id,
                'order_id'   => $order->id,  'discount_amount' => $amount,
            ]);
        }
    }

    /**
     * A cancelled order gives its vouchers back (to the buyer and to the voucher's limit).
     * An ALVY or shipping voucher shared with the checkout's other orders stays used while
     * one of those orders is still active; it moves to that order instead.
     */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach (VoucherRedemption::with('voucher')->where('order_id', $order->id)->get() as $use) {
                $shared = $use->voucher && $use->voucher->scope !== Voucher::SCOPE_SELLER && $order->checkout_group;
                $stillUsedBy = $shared
                    ? Order::where('checkout_group', $order->checkout_group)->whereKeyNot($order->id)
                        ->whereNotIn('status', Order::VOID_STATUSES)->first()
                    : null;

                if ($stillUsedBy) {
                    $use->update(['order_id' => $stillUsedBy->id]);
                    continue;
                }
                // An expired voucher isn't given back (A21).
                if ($use->voucher && $use->voucher->ends_at && $use->voucher->ends_at->isPast()) {
                    continue;
                }
                Voucher::whereKey($use->voucher_id)->where('used_count', '>', 0)->decrement('used_count');
                $use->delete();
            }
        });
    }

    /** How many times this buyer has used each voucher: [voucherId => count]. */
    public function usesByUser(User $user): Collection
    {
        return VoucherRedemption::where('user_id', $user->id)
            ->selectRaw('voucher_id, COUNT(*) as uses')->groupBy('voucher_id')
            ->pluck('uses', 'voucher_id');
    }

    /** Why the voucher can't be used on this amount, or null when it can. */
    public function unusableReason(Voucher $v, float $amount, Collection $used, bool $checkRunning = false): ?string
    {
        if ($checkRunning) {
            if (! $v->is_active)                                   return 'this voucher is turned off.';
            if ($v->starts_at && $v->starts_at->isFuture())        return 'this voucher is not active yet — it starts on ' . $v->starts_at->format('M d, Y g:i A') . '.';
            if ($v->ends_at && $v->ends_at->isPast())              return 'this voucher has expired.';
            if ($v->usage_limit !== null && $v->used_count >= $v->usage_limit) return 'this voucher has been fully claimed (no uses left).';
        }
        if (($used[$v->id] ?? 0) >= $v->per_user_limit) {
            return $v->per_user_limit === 1 ? 'you have already used this voucher.' : 'you have used this voucher the maximum number of times.';
        }
        if ($amount < $v->min_spend) {
            return 'spend ₱' . number_format($v->min_spend - $amount, 2) . ' more to use it (minimum spend ₱' . number_format($v->min_spend, 2) . ').';
        }

        return null;
    }

    /** Split an amount over lines in proportion to $weights (default: line totals). */
    private function split(float $amount, Collection $lines, ?Collection $weights = null): array
    {
        if ($amount <= 0 || $lines->isEmpty()) {
            return $lines->mapWithKeys(fn ($l) => [$l->id => 0.0])->all();
        }

        $weights ??= $lines->mapWithKeys(fn ($l) => [$l->id => $l->subtotal()]);
        $total = max(0.01, $weights->sum());
        $out = [];
        $left = $amount;
        foreach ($lines->values() as $idx => $line) {
            $share = $idx === $lines->count() - 1
                ? round($left, 2)
                : round($amount * ($weights[$line->id] / $total), 2);
            $out[$line->id] = $share;
            $left -= $share;
        }

        return $out;
    }
}
