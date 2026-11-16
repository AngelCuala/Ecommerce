<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerPayout;
use App\Models\SellerWalletEntry;
use App\Services\SellerWallet;
use Illuminate\Http\Request;

/**
 * Seller withdrawals (PAYMENTS_SPEC §7): ALVY sends each one from its bank (InstaPay / PESONet, or GCash / Maya),
 * then marks it Completed with the bank reference, or Failed (the amount goes back to the seller's available balance).
 * Reconciliation (§10): the sellers' balances should equal what ALVY keeps aside for them.
 */
class SellerPayoutController extends Controller
{
    public function __construct(private SellerWallet $wallet) {}

    public function index()
    {
        $processing = SellerPayout::where('status', 'pending')->with('seller.sellerApplication')->oldest()->get();
        $done = SellerPayout::whereIn('status', ['paid', 'rejected'])->with('seller.sellerApplication', 'processor')->latest('processed_at')->latest('id')->paginate(30);
        $owed = [
            'on_hold'    => round((float) SellerWalletEntry::where('status', 'on_hold')->sum('amount'), 2),
            'available'  => round((float) SellerWalletEntry::where('status', 'available')->sum('amount')
                - (float) SellerPayout::whereIn('status', ['pending', 'paid'])->sum('amount'), 2),
            'processing' => round((float) $processing->sum('amount'), 2),
        ];

        return view('admin.seller-payouts.index', compact('processing', 'done', 'owed'));
    }

    public function paid(Request $request, SellerPayout $payout)
    {
        $data = $request->validate(['reference' => 'required|string|min:6|max:60'], ['reference.required' => 'Enter the bank reference number of the transfer.',
            'reference.min' => 'Enter the bank reference number of the transfer.']);
        try {
            $p = $this->wallet->markPaid($payout, auth()->user(), trim($data['reference']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', '₱' . number_format((float) $p->amount, 2) . ' to ' . ($p->seller?->storeName() ?? 'the seller') . ' marked completed.');
    }

    public function failed(Request $request, SellerPayout $payout)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:300'], ['reason.required' => 'Tell the seller why (5 to 300 characters).']);
        try {
            $p = $this->wallet->markFailed($payout, auth()->user(), trim($data['reason']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marked failed: ₱' . number_format((float) $p->amount, 2) . ' is back in the seller\'s available balance.');
    }
}
