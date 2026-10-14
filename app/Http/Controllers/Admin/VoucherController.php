<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Support\VoucherRules;
use Illuminate\Http\Request;

/**
 * ALVY (platform) vouchers: work on any seller's products, paid for by ALVY.
 * Admins can also see every store voucher and turn one off.
 */
class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $scope = $request->query('scope') === 'seller' ? Voucher::SCOPE_SELLER : Voucher::SCOPE_PLATFORM;

        $vouchers = Voucher::where('scope', $scope)
            ->with('seller.sellerApplication')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', '%' . $request->search . '%')
                ->orWhere('name', 'like', '%' . $request->search . '%')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'platform' => Voucher::where('scope', Voucher::SCOPE_PLATFORM)->count(),
            'seller'   => Voucher::where('scope', Voucher::SCOPE_SELLER)->count(),
        ];

        return view('admin.vouchers.index', compact('vouchers', 'scope', 'counts'));
    }

    public function create()
    {
        return view('admin.vouchers.form', ['voucher' => new Voucher(['per_user_limit' => 1, 'is_active' => true, 'discount_type' => 'fixed'])]);
    }

    public function store(Request $request)
    {
        $data = VoucherRules::validate($request);

        Voucher::create($data + ['scope' => Voucher::SCOPE_PLATFORM, 'created_by' => auth()->id()]);

        return redirect()->route('admin.vouchers.index')->with('success', 'ALVY voucher ' . $data['code'] . ' created.');
    }

    public function edit(Voucher $voucher)
    {
        abort_unless($voucher->isPlatform(), 404);   // store vouchers are edited by their seller

        return view('admin.vouchers.form', compact('voucher'));
    }

    public function update(Request $request, Voucher $voucher)
    {
        abort_unless($voucher->isPlatform(), 404);

        $voucher->update(VoucherRules::validate($request, $voucher));

        return redirect()->route('admin.vouchers.index')->with('success', 'Voucher ' . $voucher->code . ' updated.');
    }

    /** Turn any voucher on or off (also store vouchers, e.g. one that breaks the rules). */
    public function toggle(Voucher $voucher)
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);

        return back()->with('success', 'Voucher ' . $voucher->code . ' turned ' . ($voucher->is_active ? 'on' : 'off') . '.');
    }

    public function destroy(Voucher $voucher)
    {
        abort_unless($voucher->isPlatform(), 404);
        if ($voucher->used_count > 0 || $voucher->redemptions()->exists()) {
            return back()->with('error', 'Voucher ' . $voucher->code . ' has been used, so it can\'t be deleted. Turn it off instead.');
        }

        $voucher->delete();

        return back()->with('success', 'Voucher deleted.');
    }
}
