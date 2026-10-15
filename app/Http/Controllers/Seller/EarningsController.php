<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Earnings: Pending until the order is Completed, then Available (assumption A15).
 * Cancelled and refunded orders never count. Available money can be requested as a payout
 * (minimum SellerPayout::MINIMUM).
 */
class EarningsController extends Controller
{
    public function index()
    {
        $balances = $this->balances(auth()->id());

        $productIds = Product::withTrashed()->where('seller_id', auth()->id())->pluck('id');
        $orders = Order::whereHas('items', fn ($q) => $q->whereIn('product_id', $productIds))
            ->whereNotIn('status', Order::NOT_SALES)
            ->with(['items' => fn ($q) => $q->whereIn('product_id', $productIds)])
            ->latest()->paginate(20);

        $payouts = SellerPayout::where('seller_id', auth()->id())->latest()->take(20)->get();
        $token   = (string) Str::uuid();

        return view('seller.earnings.index', compact('balances', 'orders', 'payouts', 'token'));
    }

    public function requestPayout(Request $request)
    {
        $data = $request->validate([
            'amount'        => ['required', 'numeric', 'decimal:0,2', 'min:' . SellerPayout::MINIMUM],
            'request_token' => 'required|uuid',
        ], [
            'amount.min'     => 'The minimum payout is ₱' . number_format(SellerPayout::MINIMUM, 2) . '.',
            'amount.decimal' => 'Enter an amount with up to 2 decimal places.',
        ]);

        // The same form sent twice (double click): only the first creates a request.
        if (SellerPayout::where('request_token', $data['request_token'])->exists()) {
            return redirect()->route('seller.earnings')->with('success', 'Your payout request was already sent.');
        }

        $sellerId = auth()->id();

        return Cache::lock('seller-payout:' . $sellerId, 10)->block(8, function () use ($data, $sellerId) {
            if (SellerPayout::where('request_token', $data['request_token'])->exists()) {
                return redirect()->route('seller.earnings')->with('success', 'Your payout request was already sent.');
            }
            if (SellerPayout::where('seller_id', $sellerId)->where('status', 'pending')->exists()) {
                return back()->withInput()->withErrors(['amount' => 'You already have a payout request waiting. You can request another once it is paid.']);
            }
            $available = $this->balances($sellerId)['available'];
            if (round((float) $data['amount'], 2) > round($available, 2)) {
                return back()->withInput()->withErrors(['amount' => 'You can request up to your available balance of ₱' . number_format($available, 2) . '.']);
            }

            $payout = DB::transaction(fn () => SellerPayout::create([
                'seller_id'     => $sellerId,
                'amount'        => round((float) $data['amount'], 2),
                'status'        => 'pending',
                'request_token' => $data['request_token'],
            ]));
            \App\Models\ActivityLog::note('Payout request ₱' . number_format($payout->amount, 2), $payout);

            return redirect()->route('seller.earnings')->with('success', 'Payout of ₱' . number_format($payout->amount, 2) . ' requested. It shows as a pending payout until ALVY sends it.');
        });
    }

    /** ['pending' => not-yet-completed earnings, 'completed' => all completed earnings, 'paid_out' => requested payouts, 'available' => completed - payouts]. */
    public function balances(int $sellerId): array
    {
        $productIds = Product::withTrashed()->where('seller_id', $sellerId)->pluck('id');
        $sum = fn (callable $scope) => (float) OrderItem::whereIn('product_id', $productIds)
            ->whereHas('order', $scope)->sum('seller_earning');

        $completed = $sum(fn ($q) => $q->where('status', Order::COMPLETED));
        $pending   = $sum(fn ($q) => $q->whereNotIn('status', array_merge(Order::NOT_SALES, [Order::COMPLETED])));
        $requested = (float) SellerPayout::where('seller_id', $sellerId)->whereIn('status', ['pending', 'paid'])->sum('amount');
        $pendingPayout = (float) SellerPayout::where('seller_id', $sellerId)->where('status', 'pending')->sum('amount');

        return [
            'pending'        => round($pending, 2),
            'completed'      => round($completed, 2),
            'requested'      => round($requested, 2),
            'pending_payout' => round($pendingPayout, 2),
            'available'      => round(max(0, $completed - $requested), 2),
        ];
    }
}
