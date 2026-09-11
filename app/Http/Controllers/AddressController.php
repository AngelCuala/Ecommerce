<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'label'        => 'required|string|max:50',
            'full_name'    => 'required|string|max:255',
            'phone'        => 'nullable|string|max:30',
            'address_line' => 'required|string|max:255',
            'barangay'     => 'nullable|string|max:120',
            'city'         => 'required|string|max:120',
            'province'     => 'nullable|string|max:120',
            'zip'          => 'nullable|string|max:20',
            'country'      => 'nullable|string|max:100',
            'is_default'   => 'boolean',
        ]);

        $user = auth()->user();
        $data['user_id'] = $user->id;
        $data['country'] = $data['country'] ?? 'Philippines';

        // If marking as default, clear existing default first
        if (! empty($data['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        }

        // If this is the first address, auto-set as default
        if ($user->addresses()->count() === 0) {
            $data['is_default'] = true;
        }

        UserAddress::create($data);

        return back()->with('success', 'Address added successfully.');
    }

    public function update(Request $request, UserAddress $address)
    {
        abort_unless($address->user_id === auth()->id(), 403);

        $data = $request->validate([
            'label'        => 'required|string|max:50',
            'full_name'    => 'required|string|max:255',
            'phone'        => 'nullable|string|max:30',
            'address_line' => 'required|string|max:255',
            'barangay'     => 'nullable|string|max:120',
            'city'         => 'required|string|max:120',
            'province'     => 'nullable|string|max:120',
            'zip'          => 'nullable|string|max:20',
            'country'      => 'nullable|string|max:100',
            'is_default'   => 'boolean',
        ]);

        if (! empty($data['is_default'])) {
            auth()->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($data);

        return back()->with('success', 'Address updated successfully.');
    }

    public function destroy(UserAddress $address)
    {
        abort_unless($address->user_id === auth()->id(), 403);

        $wasDefault = $address->is_default;
        $address->delete();

        // Promote the oldest remaining address to default
        if ($wasDefault) {
            auth()->user()->addresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Address removed.');
    }

    public function setDefault(UserAddress $address)
    {
        abort_unless($address->user_id === auth()->id(), 403);

        auth()->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', 'Default address updated.');
    }
}
