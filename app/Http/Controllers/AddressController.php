<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\UserAddress;
use App\Services\PsgcDirectory;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

/**
 * Address book. Region → province → city → barangay come from the PSGC lists (their codes are
 * saved for shipping zones and delivery routing). A buyer always has exactly one default
 * address while they have any.
 */
class AddressController extends Controller
{
    private function validated(Request $request): array
    {
        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone'))]);

        $data = $request->validate([
            'label'             => 'required|string|max:50',
            'full_name'         => 'required|string|max:255',
            'phone'             => ['required', 'string', 'regex:' . PhoneNumber::PATTERN],
            'region'            => 'required|string|max:120',
            'province'          => 'required|string|max:120',
            'city'              => 'required|string|max:120',
            'barangay'          => 'required|string|max:120',
            'address_line'      => 'required|string|max:255',
            'zip'               => 'nullable|string|max:10',
            'province_code'     => ['nullable', 'regex:/^\d{9,10}$/'],
            'municipality_code' => ['required', 'regex:/^\d{9,10}$/'],
            'barangay_code'     => ['nullable', 'regex:/^\d{9,10}$/'],
            'is_default'        => 'boolean',
        ], [
            'phone.regex'                => 'Enter a valid PH mobile number: 09XXXXXXXXX (11 digits) or +639XXXXXXXXX.',
            'municipality_code.required' => 'Please choose the region, province, city and barangay from the lists.',
            'address_line.required'      => 'Please enter the house number and street.',
        ]);
        $data['country'] = 'Philippines';

        // Region → province → city → barangay must be one real PSA chain (no tampered codes);
        // the official names replace what was submitted. A 10-digit value is a PSGC code.
        PsgcDirectory::normalizeCodes($request);
        $in   = $request->only(['region_code', 'province_code', 'municipality_code', 'barangay_code', 'region_psgc', 'province_psgc', 'municipality_psgc', 'barangay_psgc']);
        $addr = app(PsgcDirectory::class)->resolve($in, true, $data['barangay'], ['municipality' => 'city']);
        $data['region']            = $addr['region'];
        $data['province']          = $addr['province_display'];
        $data['city']              = $addr['municipality'];
        $data['barangay']          = $addr['barangay'];
        $data['province_code']     = $addr['province_code'] ?? $addr['province_psgc'];
        $data['municipality_code'] = $addr['municipality_code'] ?? $addr['municipality_psgc'];
        $data['barangay_code']     = $addr['barangay_code'] ?? $addr['barangay_psgc'];

        return $data;
    }

    /** A30: a buyer keeps up to 10 addresses. */
    public const MAX_ADDRESSES = 10;

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->addresses()->count() >= self::MAX_ADDRESSES) {
            return back()->withInput()->with('error', 'You can save up to ' . self::MAX_ADDRESSES . ' addresses. Delete one to add another.');
        }
        $data = $this->validated($request);
        $data['user_id'] = $user->id;

        // The first address is always the default.
        if ($user->addresses()->count() === 0) {
            $data['is_default'] = true;
        } elseif (! empty($data['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = UserAddress::create($data);

        if ($request->input('return') === 'checkout') {
            session()->put('checkout_address', $address->id);
            return redirect()->route('checkout.index', session()->has('buy_now') ? ['buy_now' => 1] : [])
                ->with('success', 'Address added.');
        }

        return back()->with('success', 'Address added successfully.');
    }

    public function update(Request $request, UserAddress $address)
    {
        abort_unless((int) $address->user_id === (int) auth()->id(), 403);

        $data = $this->validated($request);

        if (! empty($data['is_default'])) {
            auth()->user()->addresses()->update(['is_default' => false]);
            $data['is_default'] = true;
        } else {
            // Unticking can't leave the buyer with no default; choose another address as default instead.
            unset($data['is_default']);
        }

        // Orders already placed keep their own copy of the address, so they don't change.
        $address->update($data);

        return back()->with('success', 'Address updated successfully.');
    }

    public function destroy(UserAddress $address)
    {
        abort_unless((int) $address->user_id === (int) auth()->id(), 403);

        // A30: the default address stays until another one is made the default.
        if ($address->is_default) {
            return back()->with('error', 'This is your default address. Make another address your default first, then delete this one.');
        }

        // Orders on their way keep a copy of the address, but the buyer may need it to follow up.
        $inUse = Order::where('address_id', $address->id)
            ->whereIn('status', [Order::PENDING, Order::PROCESSING, Order::SHIPPED])->exists();
        if ($inUse) {
            return back()->with('error', 'This address is used by an order that hasn\'t arrived yet. You can delete it after the order is delivered.');
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // There is never zero defaults while addresses exist: the oldest remaining one takes over.
        if ($wasDefault) {
            auth()->user()->addresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Address removed.' . ($wasDefault && auth()->user()->addresses()->exists() ? ' Another address is now your default.' : ''));
    }

    public function setDefault(UserAddress $address)
    {
        abort_unless((int) $address->user_id === (int) auth()->id(), 403);

        auth()->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', 'Default address updated.');
    }
}
