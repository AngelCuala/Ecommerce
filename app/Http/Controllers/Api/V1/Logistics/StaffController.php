<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SortingCenterController as WebSc;
use App\Models\Courier;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\SortingCenterApplication;
use App\Services\StaffAccounts;
use App\Support\LogisticsJson;
use App\Support\PersonRules;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The app's Add Rider and Add Courier: people with a login of their own, made by the logistics company.
 * They start Pending Activation and set their password from an emailed link (StaffAccounts). Also the
 * company's hubs (a courier's home hub).
 */
class StaffController extends Controller
{
    use CallsWeb;

    /** Linehaul vehicles: the load capacity range (kg) and the DL codes that cover them (any one of). */
    public const COURIER_VEHICLES = [
        'Van'                        => ['min' => 100, 'max' => 1000,  'codes' => ['B', 'B2']],
        '4-Wheeler Closed Van Truck' => ['min' => 100, 'max' => 4000,  'codes' => ['C']],
        '6-Wheeler Truck'            => ['min' => 100, 'max' => 10000, 'codes' => ['C']],
    ];

    public const DL_CODES = ['A', 'A1', 'B', 'B1', 'B2', 'C', 'D', 'BE', 'CE'];

    public const LICENSE_TYPES = ['Professional', 'Non-professional', 'Student permit'];

    private function company(): string
    {
        return SortingCenterApplication::where('user_id', auth()->id())->where('status', 'approved')->value('business_name') ?: auth()->user()->name;
    }

    // ── Riders ───────────────────────────────────────────────────

    /** The full Add Rider (see SortingCenterController::storeRiderAccount). */
    public function storeRider(): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'storeRiderAccount'));
    }

    public function resendRider(Rider $rider): JsonResponse
    {
        abort_unless((int) $rider->sorting_center_id === (int) auth()->id(), 403, 'This rider is managed by another logistics company.');

        return $this->resend($rider->user);
    }

    // ── Couriers ─────────────────────────────────────────────────

    /** What the Add Courier form offers: vehicles (capacity, DL codes), license types and the company's hubs. */
    public function courierForm(): JsonResponse
    {
        return response()->json([
            'vehicles' => collect(self::COURIER_VEHICLES)->map(fn ($v, $name) => ['name' => $name] + $v)->values(),
            'dl_codes' => self::DL_CODES,
            'license_types' => self::LICENSE_TYPES,
            'hubs' => $this->myHubs(),
        ]);
    }

    /**
     * first_name, last_name, birthday, email, phone, license_number, license_type, dl_codes[], license_expiry,
     * vehicle_type, plate_number, load_capacity_kg, home_hub_id, license_file, or_cr_file, nbi_file.
     */
    public function storeCourier(Request $request): JsonResponse
    {
        $sc = auth()->user();
        $request->merge([
            'first_name'     => trim((string) $request->input('first_name')),
            'last_name'      => trim((string) $request->input('last_name')),
            'email'          => mb_strtolower(trim((string) $request->input('email'))),
            'phone'          => PhoneNumber::normalize($request->input('phone')),
            'license_number' => strtoupper(trim((string) $request->input('license_number'))),
            'plate_number'   => strtoupper(preg_replace('/\s+/', '', (string) $request->input('plate_number'))),
            'dl_codes'       => array_values(array_unique(array_map(fn ($c) => strtoupper(trim((string) $c)),
                array_filter(is_array($request->input('dl_codes')) ? $request->input('dl_codes') : explode(',', (string) $request->input('dl_codes')), fn ($c) => trim((string) $c) !== '')))),
        ]);
        $vehicle = self::COURIER_VEHICLES[$request->input('vehicle_type')] ?? null;

        $docs = ['license_file' => 'Professional driver\'s license', 'or_cr_file' => 'Vehicle OR/CR', 'nbi_file' => 'NBI clearance'];
        $data = $request->validate([
            'first_name'       => PersonRules::name(),
            'last_name'        => PersonRules::name(),
            'birthday'         => PersonRules::adultBirthday(),
            'email'            => PersonRules::email(),
            'phone'            => PersonRules::mobile(),
            'license_number'   => PersonRules::licenseNumber(),
            'license_type'     => ['required', Rule::in(self::LICENSE_TYPES), Rule::in(['Professional'])],
            'dl_codes'         => ['required', 'array', 'min:1'],
            'dl_codes.*'       => [Rule::in(self::DL_CODES)],
            'license_expiry'   => 'required|date|after_or_equal:today',
            'vehicle_type'     => ['required', Rule::in(array_keys(self::COURIER_VEHICLES))],
            'plate_number'     => PersonRules::plate(),
            'load_capacity_kg' => array_merge(['required', 'regex:/^\d+$/'], $vehicle ? ['integer', 'min:' . $vehicle['min'], 'max:' . $vehicle['max']] : []),
            'home_hub_id'      => ['required', Rule::exists('logistics_hubs', 'id')->where('sorting_center_id', $sc->id)],
            'license_file'     => PersonRules::document(),
            'or_cr_file'       => PersonRules::document(),
            'nbi_file'         => PersonRules::document(),
        ], PersonRules::nameMessages('first_name', 'First name') + PersonRules::nameMessages('last_name', 'Last name')
            + PersonRules::birthdayMessages() + PersonRules::staffMessages($docs) + [
                'phone.regex'           => PhoneNumber::MESSAGE,
                'license_type.in'       => 'Couriers need a professional driver\'s license.',
                'dl_codes.required'     => 'Choose the DL codes on the license.',
                'dl_codes.*.in'         => 'Choose DL codes from the list.',
                'vehicle_type.in'       => 'Choose Van, 4-Wheeler Closed Van Truck or 6-Wheeler Truck.',
                'load_capacity_kg.regex' => 'Enter the load capacity as a whole number of kg' . ($vehicle ? ' (' . number_format($vehicle['min']) . ' to ' . number_format($vehicle['max']) . ' kg for a ' . $request->input('vehicle_type') . ').' : '.'),
                'load_capacity_kg.min'  => $vehicle ? 'A ' . $request->input('vehicle_type') . ' carries ' . number_format($vehicle['min']) . ' to ' . number_format($vehicle['max']) . ' kg.' : '',
                'load_capacity_kg.max'  => $vehicle ? 'A ' . $request->input('vehicle_type') . ' carries ' . number_format($vehicle['min']) . ' to ' . number_format($vehicle['max']) . ' kg.' : '',
                'home_hub_id.required'  => 'Choose the courier\'s home hub.',
                'home_hub_id.exists'    => 'Choose one of your company\'s hubs.',
            ]);

        // The license must cover the vehicle (A18): Van needs B or B2; the trucks need C.
        if (! array_intersect($data['dl_codes'], $vehicle['codes'])) {
            throw ValidationException::withMessages(['dl_codes' => 'A ' . $data['vehicle_type'] . ' needs DL code ' . implode(' or ', $vehicle['codes']) . ' on the license.']);
        }

        $paths = [];
        foreach (['license_file' => 'id_license_path', 'or_cr_file' => 'or_cr_path', 'nbi_file' => 'nbi_path'] as $field => $column) {
            $paths[$column] = $request->file($field)->store('courier-documents', \App\Support\ProtectedFile::DISK);
        }

        $courier = DB::transaction(function () use ($sc, $data, $paths) {
            $user = StaffAccounts::makeUser($data);

            return Courier::create([
                'user_id'           => $user->id,
                'sorting_center_id' => $sc->id,
                'home_hub_id'       => $data['home_hub_id'],
                'first_name'        => $data['first_name'],
                'last_name'         => $data['last_name'],
                'contact_no'        => $data['phone'],
                'birthday'          => $data['birthday'],
                'age'               => \Carbon\Carbon::parse($data['birthday'])->age,
                'vehicle_type'      => $data['vehicle_type'],
                'plate_number'      => $data['plate_number'],
                'license_number'    => $data['license_number'],
                'license_type'      => $data['license_type'],
                'dl_codes'          => implode(', ', $data['dl_codes']),
                'license_expiry'    => $data['license_expiry'],
                'load_capacity_kg'  => (int) $data['load_capacity_kg'],
                'status'            => 'pending_activation',
                'submitted_at'      => now(),
                'reviewed_by'       => $sc->id,
            ] + $paths);
        });

        $sent = StaffAccounts::sendActivation($courier->user, $this->company());

        return response()->json([
            'message' => $sent
                ? $courier->fullName() . ' was added as Pending Activation. An activation email was sent to ' . $data['email'] . '.'
                : $courier->fullName() . ' was added as Pending Activation, but the activation email could not be sent. Use Resend activation.',
            'courier' => LogisticsJson::courier($courier->fresh(['user', 'homeHub']), true),
        ], 201);
    }

    public function resendCourier(Courier $courier): JsonResponse
    {
        abort_unless((int) $courier->sorting_center_id === (int) auth()->id(), 403, 'This courier is managed by another logistics company.');

        return $this->resend($courier->user);
    }

    private function resend(?\App\Models\User $user): JsonResponse
    {
        if (! $user || ! $user->isPendingActivation()) {
            return response()->json(['message' => 'This account is already active: there is nothing to resend.'], 422);
        }

        return StaffAccounts::sendActivation($user, $this->company())
            ? response()->json(['message' => 'A new activation email was sent to ' . $user->email . '. The earlier link no longer works.'])
            : response()->json(['message' => 'The activation email could not be sent. Please try again.'], 503);
    }

    // ── Hubs ─────────────────────────────────────────────────────

    private function myHubs(): array
    {
        return LogisticsHub::forCompany(auth()->user())
            ->map(fn ($h) => \App\Support\LinehaulJson::hub($h))->all();
    }

    public function hubs(): JsonResponse
    {
        return response()->json(['hubs' => $this->myHubs()]);
    }

    /** Where the hub is: couriers depart and arrive within 500 m of it. */
    private const LOCATION_RULES = ['latitude' => 'nullable|numeric|between:-90,90|required_with:longitude', 'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude'];

    /** code (HUB-QC: unique), name, address, latitude, longitude. */
    public function storeHub(Request $request): JsonResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code'))), 'name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'code'    => ['required', 'regex:/^HUB-[A-Z0-9]{2,10}$/', 'unique:logistics_hubs,code'],
            'name'    => 'required|string|min:3|max:100',
            'address' => 'nullable|string|max:255',
        ] + self::LOCATION_RULES, [
            'code.regex'  => 'Hub codes look like HUB-QC: "HUB-" and 2 to 10 letters or numbers.',
            'code.unique' => 'This hub code is already used.',
        ]);
        $hub = LogisticsHub::create($data + ['sorting_center_id' => auth()->id()]);

        return response()->json(['message' => $hub->label() . ' was added.', 'hubs' => $this->myHubs()], 201);
    }

    /** name, address, latitude, longitude (the code stays). */
    public function updateHub(Request $request, LogisticsHub $hub): JsonResponse
    {
        abort_unless((int) $hub->sorting_center_id === (int) auth()->id(), 403, 'This hub belongs to another logistics company.');
        $data = $request->validate(['name' => 'sometimes|required|string|min:3|max:100', 'address' => 'nullable|string|max:255'] + self::LOCATION_RULES);
        $hub->update($data);

        return response()->json(['message' => $hub->label() . ' was saved.', 'hubs' => $this->myHubs()]);
    }
}
