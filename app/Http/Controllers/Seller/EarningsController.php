<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayoutAccount;
use App\Models\Product;
use App\Models\SellerPayout;
use App\Models\SellerWalletEntry;
use App\Services\SellerWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Earnings & Payouts: the seller wallet ledger (PAYMENTS_SPEC §5, §7, App\Services\SellerWallet). Each delivered order
 * is a line: On hold during the return window (with its expected release date), Available once completed, Reversed
 * when refunded. The seller withdraws any amount of the available balance (no minimum, no fee) to a saved GCash,
 * Maya or bank account; ALVY sends it.
 */
class EarningsController extends Controller
{
    public function __construct(private SellerWallet $wallet) {}

    public function index()
    {
        $sellerId = auth()->id();
        $balances = $this->balances($sellerId);   // also brings the ledger up to date

        $productIds = Product::withTrashed()->where('seller_id', $sellerId)->pluck('id');
        $orders = Order::whereHas('items', fn ($q) => $q->whereIn('product_id', $productIds))
            ->whereNotIn('status', Order::NOT_SALES)
            ->with(['items' => fn ($q) => $q->whereIn('product_id', $productIds)->whereNull('refunded_at')])
            ->latest()->paginate(20);

        $ledger = SellerWalletEntry::where('seller_id', $sellerId)->with('order')->latest('earned_at')->latest('id')->paginate(20, ['*'], 'ledger');
        $payouts = SellerPayout::where('seller_id', $sellerId)->latest()->take(20)->get();
        $accounts = PayoutAccount::where('user_id', $sellerId)->latest('updated_at')->get();
        $token = (string) Str::uuid();

        return view('seller.earnings.index', compact('balances', 'orders', 'ledger', 'payouts', 'accounts', 'token'));
    }

    /** amount, request_token, and payout_account_id or a new account (method, bank_name, account_number, account_name). */
    public function requestPayout(Request $request)
    {
        $data = $request->validate([
            'amount'        => ['required', 'numeric', 'decimal:0,2', 'min:' . SellerPayout::MINIMUM],
            'request_token' => 'required|uuid',
        ], [
            'amount.min'     => 'Enter an amount above ₱0.',
            'amount.decimal' => 'Enter an amount with up to 2 decimal places.',
        ]);
        $sellerId = auth()->id();

        // The same form sent twice (double click): only the first creates a request.
        if (SellerPayout::where('request_token', $data['request_token'])->exists()) {
            return redirect()->route('seller.earnings')->with('success', 'Your withdrawal was already sent.');
        }
        $account = null;   // an older app sends only the amount: ALVY asks the seller where to send it
        if ($request->filled('payout_account_id')) {
            $account = PayoutAccount::where('user_id', $sellerId)->findOrFail((int) $request->payout_account_id);
        } elseif ($request->hasAny(['method', 'account_number'])) {
            $acc = $request->validate(PayoutAccount::rules(), ['account_name.required' => 'Enter the name on the account.',
                'bank_name.required_if' => 'Enter the bank name.', 'method.required' => 'Choose where to send it: GCash, Maya or a bank.']);
            $account = PayoutAccount::updateOrCreate(['user_id' => $sellerId, 'method' => $acc['method'], 'account_number' => $acc['account_number']],
                ['account_name' => trim($acc['account_name']), 'bank_name' => $acc['method'] === 'bank' ? trim((string) $acc['bank_name']) : null]);
        }

        try {
            $payout = $this->wallet->requestPayout($sellerId, (float) $data['amount'], $account, $data['request_token']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }
        $account?->touch();

        return redirect()->route('seller.earnings')->with('success', 'Withdrawal of ₱' . number_format((float) $payout->amount, 2)
            . ' is processing. ALVY sends it to your ' . ($payout->destination() ?? 'account') . '.');
    }

    /** See SellerWallet::balances(). */
    public function balances(int $sellerId): array
    {
        return $this->wallet->balances($sellerId);
    }
}
