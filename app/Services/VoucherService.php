<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies vouchers to a cart at checkout.
 *  - One store voucher per seller, on that seller's items only (the seller pays for it).
 *  - One platform voucher per order, on what is left after store vouchers (ALVY pays for it).
 * Every amount is worked out here on the server; the checkout page only previews it.
 */
class VoucherService
{
    /**
     * Cart items grouped by seller: [sellerId => ['name' => ..., 'subtotal' => ..., 'items' => Collection]].
     * Products listed by ALVY itself (no seller) are grouped under 0 and can't take store vouchers.
     */
    public function groupBySeller(Collection $items): Collection
    {
        return $items->groupBy(fn ($i) => (int) ($i->product->seller_id ?? 0))
            ->map(fn ($group, $sellerId) => [
                'name'     => $sellerId ? ($group->first()->product->seller->sellerApplication->shop_name
                                ?? $group->first()->product->seller->name ?? 'Seller') : 'ALVY',
                'subtotal' => round($group->sum(fn ($i) => $i->quantity * $i->product->effective_price), 2),
                'items'    => $group,
            ]);
    }

    /** Vouchers the buyer could use on this cart, for the checkout page. */
    public function options(Collection $items, User $user): array
    {
        $groups   = $this->groupBySeller($items);
        $subtotal = round($groups->sum('subtotal'), 2);
        $used     = $this->usesByUser($user);

        $describe = fn (Voucher $v, float $base) => [
            'code'      => $v->code,
            'name'      => $v->name,
            'label'     => $v->discountLabel(),
            'min_spend' => $v->min_spend,
            'ends_at'   => $v->ends_at?->format('M d, Y g:i A'),
            'type'      => $v->discount_type,
            'value'     => $v->discount_value,
            'max'       => $v->max_discount,
            'discount'  => $v->discountFor($base),
            'reason'    => $this->unusableReason($v, $base, $used),
        ];

        $sellerOptions = [];
        $sellerVouchers = Voucher::running()->where('scope', Voucher::SCOPE_SELLER)
            ->whereIn('seller_id', $groups->keys()->filter())
            ->orderBy('min_spend')->get()->groupBy('seller_id');
        foreach ($groups as $sellerId => $group) {
            if ($sellerId && $sellerVouchers->has($sellerId)) {
                $sellerOptions[$sellerId] = [
                    'name'     => $group['name'],
                    'subtotal' => $group['subtotal'],
                    'vouchers' => $sellerVouchers[$sellerId]->map(fn ($v) => $describe($v, $group['subtotal']))->values()->all(),
                ];
            }
        }

        $platform = Voucher::running()->where('scope', Voucher::SCOPE_PLATFORM)->orderBy('min_spend')->get()
            ->map(fn ($v) => $describe($v, $subtotal))->values()->all();

        return ['platform' => $platform, 'sellers' => $sellerOptions];
    }

    /**
     * Work out the discounts for the chosen codes. Throws a validation error naming the field
     * ("platform_voucher" or "seller_vouchers.{sellerId}") when a code can't be used.
     *
     * @param array<int,string|null> $sellerCodes [sellerId => code]
     */
    public function quote(Collection $items, User $user, ?string $platformCode, array $sellerCodes): array
    {
        $groups = $this->groupBySeller($items);
        $used   = $this->usesByUser($user);

        $sellerVouchers = [];      // sellerId => Voucher
        $sellerDiscount = [];      // sellerId => amount
        foreach ($sellerCodes as $sellerId => $code) {
            $code = strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }
            $field   = "seller_vouchers.{$sellerId}";
            $group   = $groups->get((int) $sellerId);
            $voucher = Voucher::where('code', $code)->first();

            if (! $group || ! $voucher || $voucher->scope !== Voucher::SCOPE_SELLER || (int) $voucher->seller_id !== (int) $sellerId) {
                throw ValidationException::withMessages([$field => "Voucher {$code} can't be used on this store's items."]);
            }
            if ($reason = $this->unusableReason($voucher, $group['subtotal'], $used, true)) {
                throw ValidationException::withMessages([$field => "Voucher {$code}: {$reason}"]);
            }
            $sellerVouchers[$sellerId] = $voucher;
            $sellerDiscount[$sellerId] = $voucher->discountFor($group['subtotal']);
        }

        $subtotal      = round($groups->sum('subtotal'), 2);
        $afterSeller   = round($subtotal - array_sum($sellerDiscount), 2);

        $platformVoucher  = null;
        $platformDiscount = 0.0;
        $platformCode     = strtoupper(trim((string) $platformCode));
        if ($platformCode !== '') {
            $platformVoucher = Voucher::where('code', $platformCode)->first();
            if (! $platformVoucher || ! $platformVoucher->isPlatform()) {
                throw ValidationException::withMessages(['platform_voucher' => "Voucher {$platformCode} is not an ALVY voucher. Store vouchers go under their store."]);
            }
            if ($reason = $this->unusableReason($platformVoucher, $afterSeller, $used, true)) {
                throw ValidationException::withMessages(['platform_voucher' => "Voucher {$platformCode}: {$reason}"]);
            }
            $platformDiscount = $platformVoucher->discountFor($afterSeller);
        }

        return [
            'groups'            => $groups,
            'subtotal'          => $subtotal,
            'seller_vouchers'   => $sellerVouchers,
            'seller_discounts'  => $sellerDiscount,
            'seller_discount'   => round(array_sum($sellerDiscount), 2),
            'platform_voucher'  => $platformVoucher,
            'platform_discount' => $platformDiscount,
        ];
    }

    /**
     * Each cart line's share of the vouchers: [cartItemId => ['seller' => x, 'platform' => y]].
     * Shares are proportional to the line total; rounding left-overs go on the last line.
     */
    public function lineShares(array $quote): array
    {
        $shares = [];
        $allLines = collect();

        foreach ($quote['groups'] as $sellerId => $group) {
            $lines = $group['items'];
            $allLines = $allLines->concat($lines);
            foreach ($this->split($quote['seller_discounts'][$sellerId] ?? 0, $lines) as $id => $amount) {
                $shares[$id]['seller'] = $amount;
            }
        }

        // The platform voucher is shared over what each line costs after its store voucher.
        $afterSeller = $allLines->mapWithKeys(fn ($i) => [
            $i->id => $i->quantity * $i->product->effective_price - ($shares[$i->id]['seller'] ?? 0),
        ]);
        foreach ($this->split($quote['platform_discount'], $allLines, $afterSeller) as $id => $amount) {
            $shares[$id]['platform'] = $amount;
        }

        foreach ($allLines as $line) {
            $shares[$line->id] = ($shares[$line->id] ?? []) + ['seller' => 0.0, 'platform' => 0.0];
        }

        return $shares;
    }

    /** Record the uses (call inside the order's transaction). Fails if a voucher ran out meanwhile. */
    public function redeem(Order $order, User $user, array $quote): void
    {
        $uses = [];
        foreach ($quote['seller_vouchers'] as $sellerId => $voucher) {
            $uses[] = [$voucher, $quote['seller_discounts'][$sellerId], "seller_vouchers.{$sellerId}"];
        }
        if ($quote['platform_voucher']) {
            $uses[] = [$quote['platform_voucher'], $quote['platform_discount'], 'platform_voucher'];
        }

        foreach ($uses as [$voucher, $amount, $field]) {
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

    /** A cancelled order gives its vouchers back (to the buyer and to the voucher's limit). */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach (VoucherRedemption::where('order_id', $order->id)->get() as $use) {
                Voucher::whereKey($use->voucher_id)->where('used_count', '>', 0)->decrement('used_count');
                $use->delete();
            }
        });
    }

    // ─────────────────────────────────────────────────────────────

    /** How many times this buyer has used each voucher: [voucherId => count]. */
    private function usesByUser(User $user): Collection
    {
        return VoucherRedemption::where('user_id', $user->id)
            ->selectRaw('voucher_id, COUNT(*) as uses')->groupBy('voucher_id')
            ->pluck('uses', 'voucher_id');
    }

    /** Why the voucher can't be used on this amount, or null when it can. */
    private function unusableReason(Voucher $v, float $amount, Collection $used, bool $checkRunning = false): ?string
    {
        if ($checkRunning) {
            if (! $v->is_active)                                   return 'this voucher is turned off.';
            if ($v->starts_at && $v->starts_at->isFuture())        return 'this voucher starts on ' . $v->starts_at->format('M d, Y g:i A') . '.';
            if ($v->ends_at && $v->ends_at->isPast())              return 'this voucher has ended.';
            if ($v->usage_limit !== null && $v->used_count >= $v->usage_limit) return 'this voucher has been fully used.';
        }
        if (($used[$v->id] ?? 0) >= $v->per_user_limit) {
            return $v->per_user_limit === 1 ? 'you have already used this voucher.' : 'you have used this voucher the maximum number of times.';
        }
        if ($amount < $v->min_spend) {
            return 'needs a minimum spend of ₱' . number_format($v->min_spend, 2) . ' (add ₱' . number_format($v->min_spend - $amount, 2) . ' more).';
        }

        return null;
    }

    /** Split an amount over cart lines in proportion to $weights (default: line totals). */
    private function split(float $amount, Collection $lines, ?Collection $weights = null): array
    {
        if ($amount <= 0 || $lines->isEmpty()) {
            return $lines->mapWithKeys(fn ($l) => [$l->id => 0.0])->all();
        }

        $weights ??= $lines->mapWithKeys(fn ($l) => [$l->id => $l->quantity * $l->product->effective_price]);
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
