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
        return view('seller.vouchers.form', ['voucher' => new Voucher(['per_user_limit' => 1, 'is_active' => true, 'discount_type' => 'fixed'])]);
    }

    public function store(Request $request)
    {
        $data = VoucherRules::validate($request);

        Voucher::create($data + [
            'scope' => Voucher::SCOPE_SELLER, 'seller_id' => auth()->id(), 'created_by' => auth()->id(),
        ]);

        return redirect()->route('seller.vouchers.index')->with('success', 'Store voucher ' . $data['code'] . ' created.');
    }

    public function edit(Voucher $voucher)
    {
        $this->authorizeOwn($voucher);

        return view('seller.vouchers.form', compact('voucher'));
    }

    public function update(Request $request, Voucher $voucher)
    {
        $this->authorizeOwn($voucher);

        $voucher->update(VoucherRules::validate($request, $voucher));

        return redirect()->route('seller.vouchers.index')->with('success', 'Voucher ' . $voucher->code . ' updated.');
    }

    public function toggle(Voucher $voucher)
    {
        $this->authorizeOwn($voucher);
        $voucher->update(['is_active' => ! $voucher->is_active]);

        return back()->with('success', 'Voucher ' . $voucher->code . ' turned ' . ($voucher->is_active ? 'on' : 'off') . '.');
    }

    public function destroy(Voucher $voucher)
    {
        $this->authorizeOwn($voucher);
        if ($voucher->used_count > 0 || $voucher->redemptions()->exists()) {
            return back()->with('error', 'Voucher ' . $voucher->code . ' has been used, so it can\'t be deleted. Turn it off instead.');
        }

        $voucher->delete();

        return back()->with('success', 'Voucher deleted.');
    }

    /** Another seller's voucher (or an ALVY one) looks like a missing page. */
    private function authorizeOwn(Voucher $voucher): void
    {
        abort_unless($voucher->scope === Voucher::SCOPE_SELLER && (int) $voucher->seller_id === (int) auth()->id(), 404);
    }
}
