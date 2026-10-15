<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Store Settings: what buyers see on the store page (name, description, logo, banner),
 * the contact number and pickup address used on waybills, and vacation mode.
 */
class StoreSettingsController extends Controller
{
    /** Logo / banner: JPG or PNG, up to 5 MB (5,120 KB). */
    public const IMAGE_RULES = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:5120'];

    public function edit()
    {
        $application = $this->application();

        return view('seller.store-settings', compact('application'));
    }

    public function update(Request $request)
    {
        $application = $this->application();

        if (is_string($phone = $request->input('phone'))) {
            $phone = preg_replace('/[\s\-().]/', '', $phone);
            $request->merge(['phone' => str_starts_with($phone, '+63') ? '0' . substr($phone, 3) : $phone]);
        }
        $request->merge(['shop_name' => trim((string) $request->input('shop_name'))]);

        $data = $request->validate([
            'shop_name'         => ['required', 'string', 'min:3', 'max:30', function ($attr, $value, $fail) use ($application) {
                $taken = SellerApplication::whereRaw('LOWER(TRIM(shop_name)) = ?', [mb_strtolower($value)])
                    ->where('id', '!=', $application->id)->exists();
                if ($taken) {
                    $fail('This store name is already taken. Please choose another.');
                }
            }],
            'description'       => 'nullable|string|max:500',
            'phone'             => ['required', 'regex:/^09\d{9}$/'],
            'region'            => 'required|string|max:120',
            'province'          => 'required|string|max:120',
            'municipality'      => 'required|string|max:120',
            'barangay'          => 'required|string|max:120',
            'street'            => 'required|string|max:255',
            'zip_code'          => ['required', 'regex:/^\d{4}$/'],
            'province_code'     => ['nullable', 'regex:/^\d{9,10}$/'],
            'municipality_code' => ['nullable', 'regex:/^\d{9,10}$/'],
            'logo'              => self::IMAGE_RULES,
            'banner'            => self::IMAGE_RULES,
            'is_on_vacation'    => 'nullable|boolean',
        ], [
            'shop_name.required' => 'Please enter your store name.',
            'shop_name.min'      => 'Store name must be 3 to 30 characters.',
            'shop_name.max'      => 'Store name must be 3 to 30 characters.',
            'phone.required'     => 'Please enter a contact number.',
            'phone.regex'        => 'Please enter a valid mobile number: 11 digits starting with 09 (e.g. 09171234567).',
            'region.required'    => 'Please choose the pickup address region.',
            'province.required'  => 'Please choose the pickup address province.',
            'municipality.required' => 'Please choose the pickup address city or municipality.',
            'barangay.required'  => 'Please choose the pickup address barangay.',
            'street.required'    => 'Please enter the pickup street address.',
            'zip_code.required'  => 'Please enter the pickup postal code.',
            'zip_code.regex'     => 'The postal code must be 4 digits.',
            'logo.image'         => 'The logo must be a JPG or PNG image.',
            'logo.mimes'         => 'The logo must be a JPG or PNG image.',
            'logo.mimetypes'     => 'The logo must be a JPG or PNG image.',
            'logo.max'           => 'The logo must be 5 MB or smaller.',
            'banner.image'       => 'The banner must be a JPG or PNG image.',
            'banner.mimes'       => 'The banner must be a JPG or PNG image.',
            'banner.mimetypes'   => 'The banner must be a JPG or PNG image.',
            'banner.max'         => 'The banner must be 5 MB or smaller.',
        ]);

        // Store name: still at most 3 changes per calendar month.
        $updates = [];
        if ($data['shop_name'] !== $application->shop_name) {
            if (! $application->canChangeShopName()) {
                return back()->withInput()->withErrors(['shop_name' => 'You have used all 3 store name changes this month. You can change it again after '
                    . $application->shopNameResetsAt()->format('M d, Y') . '.']);
            }
            $changes = $application->shop_name_last_changed_at && ! $application->shop_name_last_changed_at->isSameMonth(now())
                ? 0 : (int) $application->shop_name_changes_this_month;
            $updates += [
                'shop_name'                    => $data['shop_name'],
                'shop_name_changes_this_month' => $changes + 1,
                'shop_name_last_changed_at'    => now(),
            ];
        }

        foreach (['logo' => 'store-logos', 'banner' => 'store-banners'] as $field => $dir) {
            if ($request->hasFile($field)) {
                if ($old = $application->{$field . '_path'}) {
                    Storage::disk('public')->delete($old);
                }
                $updates[$field . '_path'] = $request->file($field)->store($dir, 'public');
            }
        }

        $application->update($updates + [
            'description'       => $data['description'] ?? null,
            'phone'             => $data['phone'],
            'region'            => $data['region'],
            'province'          => $data['province'],
            'province_code'     => $data['province_code'] ?? $application->province_code,
            'municipality'      => $data['municipality'],
            'municipality_code' => $data['municipality_code'] ?? $application->municipality_code,
            'barangay'          => $data['barangay'],
            'street'            => $data['street'],
            'zip_code'          => $data['zip_code'],
            'address'           => implode(', ', array_filter([$data['street'], $data['barangay'], $data['municipality'], $data['province'], $data['zip_code']])),
            'is_on_vacation'    => $request->boolean('is_on_vacation'),
        ]);

        \App\Models\ActivityLog::note('Store "' . $application->shop_name . '"' . ($application->is_on_vacation ? ' · vacation mode on' : ''), $application);

        return redirect()->route('seller.store-settings')->with('success', $application->is_on_vacation
            ? 'Store settings saved. Vacation mode is on: buyers can see your products but can\'t buy them.'
            : 'Store settings saved.');
    }

    private function application(): SellerApplication
    {
        return SellerApplication::where('user_id', auth()->id())->latest()->firstOrFail();
    }
}
