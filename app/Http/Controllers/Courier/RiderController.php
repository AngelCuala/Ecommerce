<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Models\RiderJobOffer;
use App\Models\RiderRemittance;
use App\Models\UserNotification;
use App\Services\ParcelService;
use App\Services\RiderDispatch;
use App\Services\RiderWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The rider side of the logistics company's work, brought over from the mobile app:
 * profile and vehicle, Online/Offline, job offers, pickups (seller → hub),
 * deliveries (hub → buyer) with photo + OTP + COD, failed attempts, history, pay and COD remittance.
 */
class RiderController extends Controller
{
    public const VEHICLES = ['Motorcycle', 'Bicycle', 'Tricycle', 'Car', 'Van', 'Truck'];

    public function __construct(private RiderDispatch $dispatch, private RiderWallet $wallet) {}

    /** The signed-in rider (seen now, so Online stays fresh). */
    private function rider(): Rider
    {
        $rider = Rider::with('sortingCenter')->where('user_id', auth()->id())->first();
        abort_unless($rider && ($rider->isApproved()), 403, 'This account is not a rider of a logistics company.');
        if ($rider->is_online && (! $rider->last_seen_at || $rider->last_seen_at->lt(now()->subMinute()))) {
            $rider->forceFill(['last_seen_at' => now()])->save();
        }
        return $rider;
    }

    // ── Jobs (offers + active work) ─────────────────────────────

    public function index()
    {
        $this->dispatch->expireStale();
        $rider = $this->rider();

        $offers = RiderJobOffer::where('rider_id', $rider->id)->where('status', 'pending')->where('expires_at', '>', now())
            ->with('parcel.order', 'parcel.seller')->latest()->get();
        $pickups = ParcelPickup::where('rider_id', $rider->id)->whereIn('status', ParcelPickup::OPEN)->with('parcel.seller')->oldest()->get();
        $deliveries = ParcelDelivery::where('rider_id', $rider->id)->whereIn('status', ParcelDelivery::OPEN)->with('parcel.order')->oldest()->get();
        $returns = \App\Models\Parcel::where('rts_rider_id', $rider->id)->where('status', 'return_to_sender')->whereNull('returned_at')->with('seller')->oldest('rts_assigned_at')->get();

        return view('courier.rider.jobs', compact('rider', 'offers', 'pickups', 'deliveries', 'returns'));
    }

    /** Return to Sender: the rider handed the parcel back to the seller (A10). The seller then confirms receipt. */
    public function returnedToSeller(int $parcel)
    {
        $rider  = $this->rider();
        $parcel = \App\Models\Parcel::where('rts_rider_id', $rider->id)->findOrFail($parcel);
        if ($parcel->status !== 'return_to_sender' || $parcel->returned_at) {
            return back()->with('error', "{$parcel->tracking_number} was already handed back.");
        }

        $parcel->update(['status' => 'returned', 'returned_at' => now()]);
        $svc = app(\App\Services\ParcelService::class);
        $svc->syncOrder($parcel->fresh());   // buyer: order Cancelled (failed delivery); stock waits for the seller
        $svc->notifySeller($parcel, 'Returned parcel delivered',
            "{$parcel->tracking_number} was brought back to you. Please confirm you received it so its stock returns to your inventory.", 'warning');
        \App\Models\ActivityLog::note($parcel->tracking_number . ' handed back to the seller by ' . $rider->full_name, $parcel);

        return back()->with('success', "{$parcel->tracking_number} handed back to the seller.");
    }

    public function toggleOnline(Request $request)
    {
        $rider = $this->rider();
        $goOnline = $request->has('online') ? $request->boolean('online') : ! $rider->is_online;

        if ($goOnline && $rider->isSuspended()) {
            $rider->forceFill(['is_online' => false])->save();
            return back()->with('error', 'Your rider account is suspended by ' . $rider->companyName() . '. You can\'t go Online or receive jobs. Please contact them.');
        }

        $rider->forceFill(['is_online' => $goOnline, 'last_seen_at' => now()])->save();
        $active = $rider->activeJobsCount();

        return back()->with('success', $goOnline
            ? 'You are Online. New job offers will appear here.'
            : 'You are Offline. You won\'t get new offers' . ($active ? ', but your ' . $active . ' active ' . Str::plural('job', $active) . ' stay with you.' : '.'));
    }

    public function accept(int $offer)
    {
        $rider = $this->rider();
        $offer = RiderJobOffer::where('rider_id', $rider->id)->findOrFail($offer);   // another rider's or company's job: not found

        try {
            $this->dispatch->accept($offer, $rider);
        } catch (\RuntimeException $e) {
            return redirect()->route('courier.rider')->with('error', $e->getMessage());
        }
        \App\Models\ActivityLog::note('Accepted ' . $offer->kind . ' job ' . $offer->parcel->tracking_number, $offer->parcel);

        return redirect()->route('courier.rider')->with('success', 'Job accepted: ' . $offer->kind . ' of ' . $offer->parcel->tracking_number . '.');
    }

    public function decline(int $offer)
    {
        $rider = $this->rider();
        $offer = RiderJobOffer::where('rider_id', $rider->id)->findOrFail($offer);
        $this->dispatch->decline($offer);

        return redirect()->route('courier.rider')->with('success', 'Offer declined. It goes to the next rider.');
    }

    // ── Pickup (seller → hub) ───────────────────────────────────

    public function pickup(int $pickup)
    {
        $rider  = $this->rider();
        $pickup = ParcelPickup::where('rider_id', $rider->id)->with('parcel.seller.sellerApplication', 'parcel.order.items.product')->findOrFail($pickup);

        return view('courier.rider.pickup', compact('rider', 'pickup'));
    }

    /** Scan the waybill barcode (or type the tracking number) at the seller. */
    public function confirmPickup(Request $request, int $pickup)
    {
        $rider = $this->rider();
        $data = $request->validate(['tracking_number' => 'required|string|max:50'], ['tracking_number.required' => 'Scan the waybill or type its tracking number.']);

        return DB::transaction(function () use ($rider, $pickup, $data) {
            $pickup = ParcelPickup::where('rider_id', $rider->id)->lockForUpdate()->findOrFail($pickup);
            $parcel = $pickup->parcel;

            if ($pickup->status !== 'assigned') {
                return back()->with('error', 'This parcel is already picked up (' . $pickup->picked_up_at?->format('M j, g:i A') . ').');
            }
            if (strtoupper(trim($data['tracking_number'])) !== strtoupper($parcel->tracking_number)) {
                return back()->withInput()->withErrors(['tracking_number' => 'The tracking number does not match this job. Check the waybill on the parcel.']);
            }

            $pickup->update(['status' => 'picked_up', 'picked_up_at' => now()]);
            $parcel->update(['status' => 'in_transit_to_hub']);
            $svc = app(ParcelService::class);
            $svc->syncOrder($parcel);
            $svc->notifySeller($parcel, 'Parcel picked up', "Rider {$rider->full_name} picked up {$parcel->tracking_number} at " . now()->format('g:i A') . '.');
            $svc->notifyBuyer($parcel, 'Order picked up', "{$svc->orderRef($parcel)} was picked up from the seller and is on its way to our hub.");
            \App\Models\ActivityLog::note('Picked up ' . $parcel->tracking_number, $parcel);

            return redirect()->route('courier.rider')->with('success', "{$parcel->tracking_number} picked up. You earned ₱" . number_format(RiderDispatch::PAY_PICKUP, 2) . '. Bring it to the hub.');
        });
    }

    // ── Delivery (hub → buyer) ──────────────────────────────────

    public function delivery(int $delivery)
    {
        $rider    = $this->rider();
        $delivery = ParcelDelivery::where('rider_id', $rider->id)->with('parcel.order.items.product', 'area')->findOrFail($delivery);

        return view('courier.rider.delivery', compact('rider', 'delivery'));
    }

    /** Assigned → Out for Delivery. The buyer gets a 6-digit OTP to give the rider. */
    public function startDelivery(int $delivery)
    {
        $rider = $this->rider();

        return DB::transaction(function () use ($rider, $delivery) {
            $delivery = ParcelDelivery::where('rider_id', $rider->id)->lockForUpdate()->findOrFail($delivery);
            if ($delivery->status !== 'assigned') {
                return back()->with('error', 'This delivery is already ' . $delivery->statusLabel() . '.');
            }

            $delivery->update(['status' => 'out_for_delivery', 'picked_up_at' => now(),
                'otp_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT), 'otp_attempts' => 0, 'otp_locked_until' => null]);
            $parcel = $delivery->parcel;
            $parcel->update(['status' => 'in_transit']);
            $svc = app(ParcelService::class);
            $svc->syncOrder($parcel);
            $svc->notifyBuyer($parcel, 'Out for delivery',
                "{$svc->orderRef($parcel)} is out for delivery with {$rider->full_name}. Give the rider the 6-digit OTP shown on your order page.");

            return back()->with('success', 'Out for delivery. Ask the buyer for the OTP on their order page.');
        });
    }

    /** Out for Delivery → Delivered: photo, the buyer's OTP and the exact COD amount (A24, A32). */
    public function deliver(Request $request, int $delivery)
    {
        $rider    = $this->rider();
        $delivery = ParcelDelivery::where('rider_id', $rider->id)->findOrFail($delivery);

        if ($delivery->status !== 'out_for_delivery') {
            return back()->with('error', $delivery->status === 'delivered' ? 'This parcel is already delivered.' : 'Mark the parcel Out for Delivery first.');
        }

        $cod = (float) $delivery->cod_amount > 0;
        $request->validate([
            'pod_photo'     => 'required|file|image|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
            'otp'           => ['required', 'regex:/^\d{6}$/'],
            'cod_collected' => $cod ? 'required|numeric|min:0' : 'nullable',
        ], [
            'pod_photo.required'  => 'Take a proof-of-delivery photo.',
            'pod_photo.image'     => 'The proof of delivery must be a JPG or PNG photo.',
            'pod_photo.mimes'     => 'The proof of delivery must be a JPG or PNG photo.',
            'pod_photo.mimetypes' => 'The proof of delivery must be a JPG or PNG photo.',
            'pod_photo.max'       => 'The photo must be 5 MB or smaller.',
            'otp.required'        => 'Enter the 6-digit OTP from the buyer.',
            'otp.regex'           => 'The OTP has exactly 6 digits.',
            'cod_collected.required' => 'Enter the COD amount you collected.',
        ]);

        // OTP: 5 wrong tries lock entry for 15 minutes (even the right OTP is refused meanwhile).
        if ($delivery->otpLocked()) {
            return back()->withErrors(['otp' => 'OTP entry is locked after ' . ParcelDelivery::OTP_MAX_TRIES . ' wrong tries. Try again after ' . $delivery->otp_locked_until->format('g:i A') . '.']);
        }
        if ($delivery->otp_locked_until && $delivery->otp_locked_until->isPast()) {
            $delivery->update(['otp_attempts' => 0, 'otp_locked_until' => null]);
        }
        if (! hash_equals((string) $delivery->otp_code, (string) $request->otp)) {
            $tries = $delivery->otp_attempts + 1;
            $locked = $tries >= ParcelDelivery::OTP_MAX_TRIES;
            $delivery->update(['otp_attempts' => $tries, 'otp_locked_until' => $locked ? now()->addMinutes(ParcelDelivery::OTP_LOCK_MINUTES) : null]);
            return back()->withErrors(['otp' => $locked
                ? 'Wrong OTP. OTP entry is now locked for ' . ParcelDelivery::OTP_LOCK_MINUTES . ' minutes.'
                : 'Wrong OTP. ' . (ParcelDelivery::OTP_MAX_TRIES - $tries) . ' ' . Str::plural('try', ParcelDelivery::OTP_MAX_TRIES - $tries) . ' left.']);
        }
        if ($cod && round((float) $request->cod_collected, 2) !== round((float) $delivery->cod_amount, 2)) {
            return back()->withInput()->withErrors(['cod_collected' => 'The COD amount doesn\'t match. Collect exactly ₱' . number_format($delivery->cod_amount, 2) . '.']);
        }

        DB::transaction(function () use ($request, $delivery, $cod, $rider) {
            $delivery->update([
                'status' => 'delivered', 'delivered_at' => now(), 'otp_attempts' => 0,
                'pod_path' => $request->file('pod_photo')->store('proof-of-delivery', 'public'),
                'cod_collected' => $cod ? round((float) $delivery->cod_amount, 2) : 0,
            ]);
            $parcel = $delivery->parcel;
            $parcel->update(['status' => 'delivered']);

            $svc = app(ParcelService::class);
            $svc->syncOrder($parcel);
            $order = $parcel->order;
            if ($order && ! in_array($order->status, [Order::DELIVERED, Order::COMPLETED], true)
                && $order->parcels()->where('status', '!=', 'delivered')->doesntExist()) {
                $order->update(['status' => Order::DELIVERED] + ($cod ? ['payment_status' => 'Paid'] : []));
            }
            $svc->notifySeller($parcel, 'Parcel delivered', "{$svc->orderRef($parcel)} ({$parcel->tracking_number}) was delivered to the buyer.");
            if ($scId = $parcel->current_sorting_center_id) {
                $svc->notify((int) $scId, 'Parcel delivered', "{$parcel->tracking_number} delivered by {$rider->full_name}" . ($cod ? ' · COD ₱' . number_format($delivery->cod_amount, 2) . ' collected.' : '.'));
            }
            \App\Models\ActivityLog::note('Delivered ' . $parcel->tracking_number, $parcel);
        });

        return redirect()->route('courier.rider')->with('success', 'Delivered. You earned ₱' . number_format(RiderDispatch::PAY_DELIVERY, 2)
            . ($cod ? ' and collected ₱' . number_format($delivery->cod_amount, 2) . ' COD to remit.' : '.'));
    }

    /** A failed attempt with a reason; after the 3rd, or when Refused, the parcel goes back to the seller (A13). */
    public function fail(Request $request, int $delivery)
    {
        $rider = $this->rider();
        $data = $request->validate(['reason' => ['required', \Illuminate\Validation\Rule::in(ParcelDelivery::FAILURE_REASONS)]], [
            'reason.required' => 'Choose why the delivery failed.',
            'reason.in'       => 'Choose a reason from the list.',
        ]);

        return DB::transaction(function () use ($rider, $delivery, $data) {
            $delivery = ParcelDelivery::where('rider_id', $rider->id)->lockForUpdate()->findOrFail($delivery);
            if (! in_array($delivery->status, ParcelDelivery::OPEN, true)) {
                return back()->with('error', 'This delivery is already ' . $delivery->statusLabel() . '.');
            }

            $parcel = $delivery->parcel;
            $returnToSender = $data['reason'] === 'Refused' || $delivery->attempt >= ParcelDelivery::MAX_ATTEMPTS;
            $delivery->update(['status' => 'failed', 'failure_reason' => $data['reason'], 'remarks' => $data['reason']]);
            $parcel->update(['status' => $returnToSender ? 'return_to_sender' : 'failed', 'failure_reason' => $data['reason']]);

            $svc = app(ParcelService::class);
            $ref = $svc->orderRef($parcel);
            $attempt = "attempt {$delivery->attempt} of " . ParcelDelivery::MAX_ATTEMPTS;
            if ($returnToSender) {
                $svc->notifyBuyer($parcel, 'Returning to seller', "{$ref} could not be delivered ({$data['reason']}, {$attempt}). It is being returned to the seller.", 'warning');
                $svc->notifySeller($parcel, 'Parcel returning to you', "{$ref} ({$parcel->tracking_number}) is Return to Sender: {$data['reason']}, {$attempt}.", 'warning');
            } else {
                $svc->notifyBuyer($parcel, 'Delivery attempt failed', "{$ref} could not be delivered: {$data['reason']} ({$attempt}). We'll try again.", 'warning');
            }
            if ($scId = $parcel->current_sorting_center_id) {
                $svc->notify((int) $scId, 'Failed delivery', "{$parcel->tracking_number}: {$data['reason']} ({$attempt})" . ($returnToSender ? ' → Return to Sender.' : '. Reschedule it.'), 'warning');
            }
            $svc->syncOrder($parcel->fresh());
            \App\Models\ActivityLog::note('Failed delivery ' . $parcel->tracking_number . ': ' . $data['reason'], $parcel);

            return redirect()->route('courier.rider')->with('success', $returnToSender
                ? "{$parcel->tracking_number} is Return to Sender. No further attempt."
                : "Failed delivery recorded ({$attempt}). Logistics will reschedule it.");
        });
    }

    // ── Profile, vehicle and documents ──────────────────────────

    public function profile(?int $rider = null)
    {
        $me = $this->rider();
        abort_if($rider !== null && $rider !== $me->id, 403, 'You can only view your own rider profile.');

        return view('courier.rider.profile', ['rider' => $me, 'vehicles' => self::VEHICLES]);
    }

    public function updateProfile(Request $request)
    {
        $rider = $this->rider();
        $request->merge(['plate_number' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('plate_number')))]);

        $data = $request->validate([
            'vehicle_type'   => ['required', \Illuminate\Validation\Rule::in(self::VEHICLES)],
            'plate_number'   => ['required', 'regex:/^[A-Z0-9-]{4,10}$/', function ($attr, $value, $fail) use ($rider) {
                $norm = fn ($col) => "UPPER(REPLACE({$col}, ' ', '')) = ?";
                $taken = Rider::where('id', '!=', $rider->id)->whereRaw($norm('plate_number'), [$value])->exists()
                    || Courier::where('user_id', '!=', $rider->user_id)->whereRaw($norm('plate_number'), [$value])->exists();
                if ($taken) {
                    $fail('Plate number ' . $value . ' is already registered to another rider.');
                }
            }],
            'license_number' => 'required|string|max:30',
            'license_expiry' => 'required|date|after_or_equal:today',
            'license_file'   => 'nullable|file|image|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
            'or_cr_file'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|mimetypes:image/jpeg,image/png,application/pdf|max:5120',
        ], [
            'plate_number.regex'          => 'Enter the plate number as shown on the OR/CR, e.g. 123ABC or NBC1234.',
            'license_expiry.after_or_equal' => 'Your driver\'s license has expired. Enter an expiry date of today or later.',
            'license_file.image'          => 'The driver\'s license must be a JPG or PNG photo.',
            'license_file.mimes'          => 'The driver\'s license must be a JPG or PNG photo.',
            'license_file.mimetypes'      => 'The driver\'s license must be a JPG or PNG photo.',
            'license_file.max'            => 'The driver\'s license photo must be 5 MB or smaller.',
            'or_cr_file.mimes'            => 'The OR/CR must be a PDF, JPG or PNG file.',
            'or_cr_file.mimetypes'        => 'The OR/CR must be a PDF, JPG or PNG file.',
            'or_cr_file.max'              => 'The OR/CR must be 5 MB or smaller.',
        ]);

        $vehicleChanged = $data['plate_number'] !== (string) $rider->plate_number || $request->hasFile('or_cr_file');
        $updates = [
            'vehicle_type' => $data['vehicle_type'], 'plate_number' => $data['plate_number'],
            'license_number' => $data['license_number'], 'license_expiry' => $data['license_expiry'],
        ];
        foreach (['license_file' => 'license_path', 'or_cr_file' => 'or_cr_path'] as $field => $column) {
            if ($request->hasFile($field)) {
                $updates[$column] = $request->file($field)->store('rider-documents', 'local');
            }
        }
        if ($vehicleChanged) {
            $updates['needs_reverification'] = true;
        }
        $rider->update($updates);

        if ($vehicleChanged && $rider->sorting_center_id) {
            UserNotification::create(['user_id' => $rider->sorting_center_id, 'title' => 'Rider vehicle changed',
                'body' => "{$rider->full_name} changed their vehicle to {$data['plate_number']}. Please re-verify the OR/CR.", 'type' => 'warning',
                'link' => route('sc.riders.show', $rider)]);
        }
        \App\Models\ActivityLog::note('Rider profile · plate ' . $data['plate_number'], $rider);

        return redirect()->route('courier.rider.profile')->with('success', $vehicleChanged
            ? 'Profile saved. Your new vehicle details were sent to ' . $rider->companyName() . ' for re-verification.'
            : 'Profile saved.');
    }

    /** The rider's own license or OR/CR file. */
    public function document(int $rider, string $document)
    {
        $me = $this->rider();
        abort_unless($rider === $me->id, 403, 'You can only open your own documents.');

        return self::serveDocument($me, $document);
    }

    public static function serveDocument(Rider $rider, string $document)
    {
        $path = $document === 'license' ? $rider->license_path : $rider->or_cr_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    // ── History, pay and COD ────────────────────────────────────

    public function history(Request $request)
    {
        $rider = $this->rider();
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from'], ['to.after_or_equal' => 'The end date must be on or after the start date.']);
        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->from)->startOfDay() : null;
        $to   = $request->filled('to') ? \Carbon\Carbon::parse($request->to)->endOfDay() : null;
        $rows = $this->wallet->history($rider, $from, $to);

        return view('courier.rider.history', compact('rider', 'rows'));
    }

    public function earnings()
    {
        $rider = $this->rider();
        $today = $this->wallet->history($rider, now()->startOfDay(), now()->endOfDay());
        $week  = $this->wallet->pay($rider, now()->startOfWeek(), now()->endOfWeek());

        return view('courier.rider.earnings', ['rider' => $rider, 'today' => $today, 'todayPay' => round($today->sum('pay'), 2), 'weekPay' => $week]);
    }

    public function cod()
    {
        $rider = $this->rider();

        return view('courier.rider.cod', [
            'rider'       => $rider,
            'balance'     => $this->wallet->codBalance($rider),
            'pending'     => $this->wallet->pendingRemittance($rider),
            'orders'      => $this->wallet->codOrders($rider),
            'remittances' => RiderRemittance::where('rider_id', $rider->id)->latest()->take(20)->get(),
            'token'       => (string) Str::uuid(),
        ]);
    }

    /** Hand COD cash to the logistics company; it stays Pending until they confirm (A24, A37). */
    public function remit(Request $request)
    {
        $rider = $this->rider();
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|decimal:0,2', 'request_token' => 'required|uuid'], [
            'amount.min' => 'Enter the amount you are handing over.',
        ]);

        return Cache::lock('rider-remit:' . $rider->id, 10)->block(8, function () use ($rider, $data) {
            if (RiderRemittance::where('request_token', $data['request_token'])->exists()) {
                return redirect()->route('courier.rider.cod')->with('success', 'Your remittance was already sent.');
            }
            if ($this->wallet->pendingRemittance($rider) > 0) {
                return back()->withErrors(['amount' => 'You already have a remittance waiting for confirmation.']);
            }
            $balance = $this->wallet->codBalance($rider);
            if (round((float) $data['amount'], 2) > $balance) {
                return back()->withInput()->withErrors(['amount' => 'You can remit up to your COD balance of ₱' . number_format($balance, 2) . '.']);
            }

            $remit = RiderRemittance::create(['rider_id' => $rider->id, 'sorting_center_id' => $rider->sorting_center_id,
                'amount' => round((float) $data['amount'], 2), 'expected_amount' => $balance, 'status' => 'pending', 'request_token' => $data['request_token']]);
            UserNotification::create(['user_id' => $rider->sorting_center_id, 'title' => 'COD remittance to confirm',
                'body' => "{$rider->full_name} remitted ₱" . number_format($remit->amount, 2) . ' of ₱' . number_format($balance, 2) . ' COD.'
                    . ($remit->shortfall() > 0 ? ' Shortfall ₱' . number_format($remit->shortfall(), 2) . '.' : ''), 'type' => 'info', 'link' => route('sc.remittances')]);
            \App\Models\ActivityLog::note('COD remittance ₱' . number_format($remit->amount, 2), $remit);

            return redirect()->route('courier.rider.cod')->with('success', 'Remittance of ₱' . number_format($remit->amount, 2) . ' sent. It stays Pending until ' . $rider->companyName() . ' confirms it.');
        });
    }
}
