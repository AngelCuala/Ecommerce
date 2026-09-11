<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function create()
    {
        $existing = Courier::where('user_id', auth()->id())->first();
        return view('courier.register', compact('existing'));
    }

    public function store(Request $request)
    {
        if (Courier::where('user_id', auth()->id())->exists()) {
            return back()->with('error', 'You already have a sorting center application.');
        }

        $data = $request->validate([
            'last_name'      => 'required|string|max:100',
            'first_name'     => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'sex'            => 'required|in:Male,Female',
            'contact_no'     => 'required|string|max:20',
            'birthday'       => 'required|date|before:today',
            'province'       => 'required|string|max:120',
            'municipality'   => 'required|string|max:120',
            'barangay'       => 'required|string|max:120',
            'street'         => 'nullable|string|max:255',
            'house_number'   => 'nullable|string|max:100',
            'business_name'  => 'nullable|string|max:255',
            'id_upload'      => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'dti_permit'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $data['age']     = \Carbon\Carbon::parse($data['birthday'])->age;
        $data['user_id'] = auth()->id();

        $data['id_license_path'] = $request->file('id_upload')
            ->store('sorting-center/ids', 'public');

        if ($request->hasFile('dti_permit')) {
            $data['dti_permit_path'] = $request->file('dti_permit')
                ->store('sorting-center/permits', 'public');
        }

        // Sorting centers don't use vehicle fields — set defaults
        $data['vehicle_type'] = 'N/A';
        $data['plate_number'] = 'N/A';
        $data['or_cr_path']   = '';

        Courier::create($data);

        auth()->user()->update(['role' => 'courier_pending']);

        return redirect()->route('courier.status')
            ->with('success', 'Application submitted! Please wait for administrator approval. You will be notified via email at ' . auth()->user()->email . '.');
    }

    public function status()
    {
        $courier = Courier::where('user_id', auth()->id())->first();
        return view('courier.status', compact('courier'));
    }
}
