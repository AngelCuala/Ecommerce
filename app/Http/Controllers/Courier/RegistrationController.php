<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function create()
    {
        $existing = Courier::where('user_id', auth()->id())->first();

        // Approved couriers belong in the portal, not the form.
        if ($existing && $existing->isApproved() && auth()->user()->isCourier()) {
            return redirect()->route('courier.dashboard');
        }

        return view('courier.register', compact('existing'));
    }

    public function store(Request $request)
    {
        // Block duplicate active/approved applications (allow re-apply after rejection).
        $existing = Courier::where('user_id', auth()->id())->first();
        if ($existing && in_array($existing->status, ['pending', 'approved', 'suspended'])) {
            return back()->with('error', 'You already have a courier application on file.');
        }

        $data = $request->validate([
            'last_name'       => 'required|string|max:100',
            'first_name'      => 'required|string|max:100',
            'middle_initial'  => 'nullable|string|max:5',
            'sex'             => 'required|in:Male,Female',
            'contact_no'      => 'required|string|max:20',
            'birthday'        => 'required|date|before:today',
            'province'        => 'required|string|max:120',
            'municipality'    => 'required|string|max:120',
            'barangay'        => 'required|string|max:120',
            'street'          => 'nullable|string|max:255',
            'house_number'    => 'nullable|string|max:100',
            'address_details' => 'nullable|string|max:255',
            'vehicle_type'    => 'required|in:Motorcycle,Bicycle,Tricycle,Car,Van,Other',
            'plate_number'    => 'required|string|max:30',
            'or_cr'           => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'id_upload'       => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'or_cr.required'     => 'The OR/CR document is required.',
            'id_upload.required' => "A valid ID or driver's license is required.",
        ]);

        // Fold the extra "building/other" detail into the stored street value.
        $street = trim(implode(' ', array_filter([$data['street'] ?? null, $data['address_details'] ?? null])));

        $courier = Courier::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'last_name'       => $data['last_name'],
                'first_name'      => $data['first_name'],
                'middle_initial'  => $data['middle_initial'] ?? null,
                'sex'             => $data['sex'],
                'contact_no'      => $data['contact_no'],
                'birthday'        => $data['birthday'],
                'age'             => \Carbon\Carbon::parse($data['birthday'])->age,
                'province'        => $data['province'],
                'municipality'    => $data['municipality'],
                'barangay'        => $data['barangay'],
                'street'          => $street ?: null,
                'house_number'    => $data['house_number'] ?? null,
                'vehicle_type'    => $data['vehicle_type'],
                'plate_number'    => $data['plate_number'],
                'or_cr_path'      => $request->file('or_cr')->store('couriers/or-cr', 'public'),
                'id_license_path' => $request->file('id_upload')->store('couriers/ids', 'public'),
                'status'          => 'pending',
                'rejection_reason'=> null,
                'submitted_at'    => now(),
                'reviewed_at'     => null,
                'reviewed_by'     => null,
            ]
        );

        // Mark the user as a pending courier applicant.
        auth()->user()->update(['role' => 'courier_pending']);

        // In-app notification for the applicant.
        UserNotification::create([
            'user_id' => auth()->id(),
            'title'   => 'Courier application submitted',
            'body'    => 'Your registration has been submitted successfully. Please wait for approval from the Logistics team. You will receive an email once your application has been reviewed.',
            'type'    => 'info',
            'link'    => route('courier.status'),
        ]);

        return redirect()->route('courier.status')->with(
            'success',
            'Your registration has been submitted successfully. Please wait for approval from the Logistics/Sorting Center. You will receive an email once your application has been reviewed.'
        );
    }

    public function status()
    {
        $courier = Courier::where('user_id', auth()->id())->first();
        return view('courier.status', compact('courier'));
    }
}
