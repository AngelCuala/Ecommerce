<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Services\VoucherService;

/** Claim vouchers into My Vouchers, and see which are still usable. */
class VoucherWalletController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $used = app(VoucherService::class)->usesByUser($user);

        $claims = VoucherClaim::where('user_id', $user->id)->with('voucher.seller.sellerApplication')->latest()->get()
            ->filter(fn ($c) => $c->voucher)
            ->each(function ($c) use ($used) {
                $v = $c->voucher;
                $c->state = match (true) {
                    ($used[$v->id] ?? 0) >= $v->per_user_limit                    => 'Used',
                    $v->ends_at && $v->ends_at->isPast()                          => 'Expired',
                    ! $v->is_active                                               => 'Unavailable',
                    $v->usage_limit !== null && $v->used_count >= $v->usage_limit => 'Fully claimed',
                    $v->starts_at && $v->starts_at->isFuture()                    => 'Starts ' . $v->starts_at->format('M d'),
                    default                                                       => 'Available',
                };
            });

        // ALVY vouchers anyone can claim, that this buyer hasn't claimed yet.
        $claimable = Voucher::running()->where('is_claimable', true)
            ->whereIn('scope', [Voucher::SCOPE_PLATFORM, Voucher::SCOPE_SHIPPING])
            ->whereNotIn('id', $claims->pluck('voucher_id'))->orderBy('min_spend')->get();

        return view('vouchers.wallet', compact('claims', 'claimable'));
    }

    /**
     * Voucher Center: every voucher buyers can claim right now, Shopee-style, filtered by
     * type (free shipping, discounts, store vouchers). Guests can browse; claiming needs login.
     */
    public function center()
    {
        $tab = in_array(request('tab'), ['shipping', 'discount', 'store'], true) ? request('tab') : 'all';

        $all = Voucher::running()->where('is_claimable', true)
            ->with('seller.sellerApplication')
            ->orderByRaw("CASE scope WHEN 'shipping' THEN 0 WHEN 'platform' THEN 1 ELSE 2 END")
            ->orderBy('min_spend')->get();

        $counts = [
            'all'      => $all->count(),
            'shipping' => $all->where('scope', Voucher::SCOPE_SHIPPING)->count(),
            'discount' => $all->where('scope', Voucher::SCOPE_PLATFORM)->count(),
            'store'    => $all->where('scope', Voucher::SCOPE_SELLER)->count(),
        ];
        $vouchers = match ($tab) {
            'shipping' => $all->where('scope', Voucher::SCOPE_SHIPPING),
            'discount' => $all->where('scope', Voucher::SCOPE_PLATFORM),
            'store'    => $all->where('scope', Voucher::SCOPE_SELLER),
            default    => $all,
        };

        $user = auth()->user();
        $claimedIds = $user ? VoucherClaim::where('user_id', $user->id)->pluck('voucher_id')->all() : [];
        $used = $user ? app(VoucherService::class)->usesByUser($user) : collect();

        return view('vouchers.center', compact('tab', 'vouchers', 'counts', 'claimedIds', 'used'));
    }

    public function claim(Voucher $voucher)
    {
        if (! $voucher->is_claimable || $voucher->stateLabel() !== 'Ongoing') {
            return back()->with('error', 'This voucher can\'t be claimed right now.');
        }

        $claim = VoucherClaim::firstOrCreate(['user_id' => auth()->id(), 'voucher_id' => $voucher->id]);

        return back()->with('success', $claim->wasRecentlyCreated
            ? $voucher->code . ' was added to My Vouchers.'
            : 'You already claimed ' . $voucher->code . '.');
    }
}
