<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Support\VoucherRules;
use Illuminate\Http\Request;

/** Store vouchers: only work on this seller's products; the seller pays for the discount. */
class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::where('scope', Voucher::SCOPE_SELLER)
            ->where('seller_id', auth()->id())
            ->latest()
            ->get();

        return view('seller.vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        return view('seller.vouchers.form', ['voucher' => new Voucher(['per_user_limit' => 1, 'is_active' => true, 'discount_type' => 'fixed']),
            'sellerProducts' => $this->products()]);
    }

    public function store(Request $request)
    {
        $data = VoucherRules::validate($request, null, auth()->id());

        Voucher::create($data + [
            'scope' => Voucher::SCOPE_SELLER, 'seller_id' => auth()->id(), 'created_by' => auth()->id(),
        ]);

        return redirect()->route('seller.vouchers.index')->with('success', 'Store voucher ' . $data['code'] . ' created.');
    }

    public function edit(Voucher $voucher)
    {
        $this->authorizeOwn($voucher);

        return view('seller.vouchers.form', ['voucher' => $voucher, 'sellerProducts' => $this->products()]);
    }

    public function update(Request $request, Voucher $voucher)
    {
        $this->authorizeOwn($voucher);

        $voucher->update(VoucherRules::validate($request, $voucher, auth()->id()));

        return redirect()->route('seller.vouchers.index')->with('success', 'Voucher ' . $voucher->code . ' updated.');
    }

    public function toggle(Voucher $voucher)
    {
        $this->authorizeOwn($voucher);
        if ($voucher->is_active) {
            $voucher->end();   // also leaves the wallets of buyers who never used it (A27)
        } else {
            $voucher->update(['is_active' => true]);
        }

        return back()->with('success', 'Voucher ' . $voucher->code . ' turned ' . ($voucher->is_active ? 'on' : 'off') . '.');
    }

    public function destroy(Voucher $voucher)
    {
        $this->authorizeOwn($voucher);
        // Buyers claimed or used it: end it instead, so their wallets show it as ended.
        if ($voucher->used_count > 0 || $voucher->redemptions()->exists() || $voucher->claims()->exists()) {
            $voucher->end(true);

            return back()->with('success', 'Buyers already claimed ' . $voucher->code . ', so it was ended instead of deleted. It no longer works at checkout.');
        }

        $voucher->delete();

        return back()->with('success', 'Voucher deleted.');
    }

    /** The seller's products, for limiting a voucher to some of them. */
    private function products()
    {
        return \App\Models\Product::where('seller_id', auth()->id())->where('status', '!=', 'draft')->orderBy('title')->get(['id', 'title', 'product_code']);
    }

    /** Another seller's voucher (or an ALVY one) looks like a missing page. */
    private function authorizeOwn(Voucher $voucher): void
    {
        abort_unless($voucher->scope === Voucher::SCOPE_SELLER && (int) $voucher->seller_id === (int) auth()->id(), 404);
    }
}
