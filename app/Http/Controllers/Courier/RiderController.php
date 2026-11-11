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
 * deliveries (hub → buyer) with a proof-of-delivery photo + COD, failed attempts, history, pay and COD remittance.
 */
class RiderController extends Controller
{
    public const VEHICLES = ['Motorcycle', 'Bicycle', 'Tricycle', 'Car', 'Van', 'Truck'];

    public function __construct(private RiderDispatch $dispatch, private RiderWallet $wallet) {}

    /** The signed-in rider (seen now, so Online stays fresh). */
    /** The app build that sends the pickup photo and the delivery waybill scan (older builds: optional until they update). */
    public const APP_BUILD_WITH_SCANS = 10;

    /** Required on the website and from the app build that has the camera steps. */
    private function scanRequired(Request $request): bool
    {
        return ! $request->is('api/*') || (int) $request->header('X-App-Build') >= self::APP_BUILD_WITH_SCANS;
    }

    /** Does a scanned code (waybill QR / barcode, or a typed number) belong to this parcel? */
    public static function scanMatches(?string $scanned, \App\Models\Parcel $parcel): bool
    {
        $code = strtoupper(trim((string) $scanned));
        return $code !== '' && in_array($code, array_filter([strtoupper($parcel->tracking_number), strtoupper((string) $parcel->waybill_number)]), true);
    }

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
        if ($rider->isTruck()) {
            return redirect()->route('courier.trips');   // a 4-wheel rider's Hub Jobs are hub-to-hub trips
        }

        $offers = RiderJobOffer::where('rider_id', $rider->id)->where('status', 'pending')->where('expires_at', '>', now())
            ->with('parcel.order', 'parcel.seller')->latest()->get();
        $pickups = ParcelPickup::where('rider_id', $rider->id)->whereIn('status', ParcelPickup::OPEN)->with('parcel.seller')->oldest()->get();
        $deliveries = ParcelDelivery::where('rider_id', $rider->id)->whereIn('status', ParcelDelivery::OPEN)->with('parcel.order')->oldest()->get();
        $returns = \App\Models\Parcel::where('rts_rider_id', $rider->id)->where('status', 'return_to_sender')->whereNull('returned_at')->with('seller')->oldest('rts_assigned_at')->get();
        // Hub-to-hub transport (only riders the hub allowed to carry transfers ever get one).
        $transfers = \App\Models\ParcelTransfer::where('transporter_rider_id', $rider->id)->whereIn('status', ['pending', 'in_transit'])
            ->with('parcel', 'fromSortingCenter', 'toSortingCenter')->latest()->get();
        $recent = ParcelDelivery::where('rider_id', $rider->id)->whereIn('status', ['delivered', 'failed'])->with('parcel')->latest('updated_at')->take(10)->get();
        // Buyers' returns: collect from the buyer, or bring back to the seller.
        $returnJobs = \App\Models\ReturnShipment::where(fn ($q) => $q->where(fn ($w) => $w->where('pickup_rider_id', $rider->id)->where('status', 'pickup_assigned'))
                ->orWhere(fn ($w) => $w->where('seller_rider_id', $rider->id)->where('status', 'to_seller')))
            ->with('order', 'parcel.seller')->oldest('updated_at')->get();

        return view('courier.rider.jobs', compact('rider', 'offers', 'pickups', 'deliveries', 'returns', 'transfers', 'recent', 'returnJobs'));
    }

    /**
     * The rider confirms they saw a job a hub assigned to them. Only their own open job; the
     * matching notification is marked read. Sending it twice changes nothing.
     */
    public function acknowledge(string $kind, int $job)
    {
        $rider = $this->rider();
        $model = $kind === 'pickup' ? ParcelPickup::class : ParcelDelivery::class;
        $row = $model::where('rider_id', $rider->id)->whereKey($job)->firstOrFail();   // someone else's job: 404
        if ($row->status !== 'assigned') {
            return back()->with('error', 'This job has already moved on (' . $row->statusLabel() . ').');
        }
        if (! $row->acknowledged_at) {
            $row->forceFill(['acknowledged_at' => now()])->save();
            app(\App\Services\ShipmentTracker::class)->record($row->parcel, $kind === 'delivery' ? 'delivery_rider_accepted' : 'pickup_rider_accepted', [
                $kind . '_id' => $row->id, 'dedupe_key' => "rider-job:{$kind}:{$row->id}:accepted"]);
            \App\Models\UserNotification::where('user_id', auth()->id())->where('dedupe_key', "rider-job:{$kind}:{$row->id}:assigned")
                ->whereNull('read_at')->update(['read_at' => now()]);
        }

        return back()->with('success', ucfirst($kind) . ' of ' . ($row->parcel->waybill_number ?: $row->parcel->tracking_number) . ' acknowledged.');
    }

    public const DECLINE_REASONS = ['Vehicle problem', 'Outside my area', 'Too many jobs', 'Not available at that time', 'Other'];

    /** Kept for older app versions that still show Decline: barangay jobs cannot be declined. */
    public function declineJob(Request $request, string $kind, int $job)
    {
        // Riders are assigned per barangay, so a job given by the hub is not declined; the hub reassigns it if needed.
        return back()->with('error', 'Jobs are assigned to the rider of each barangay and cannot be declined. If you cannot do it, contact your hub so they can reassign it.');
    }

    /** "On my way" to the seller: Rider Arriving (the parcel is not collected yet). */
    public function arriving(int $pickup)
    {
        $rider = $this->rider();
        $row = ParcelPickup::where('rider_id', $rider->id)->findOrFail($pickup);
        if ($row->status !== 'assigned') {
            return back()->with('error', 'This pickup is already ' . $row->statusLabel() . '.');
        }
        if (! $row->en_route_at) {
            $row->forceFill(['en_route_at' => now(), 'acknowledged_at' => $row->acknowledged_at ?? now()])->save();
            app(\App\Services\ShipmentTracker::class)->record($row->parcel, 'pickup_rider_arriving', ['pickup_id' => $row->id,
                'dedupe_key' => "rider-job:pickup:{$row->id}:arriving"]);
        }

        return back()->with('success', 'The seller was told you are on the way. Confirm the pickup once you have the parcel.');
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

    // ── Return Items: collect a buyer's return (like a pickup), then bring it to the seller ──

    private function myReturn(int $id): \App\Models\ReturnShipment
    {
        $rider = $this->rider();
        return \App\Models\ReturnShipment::where(fn ($q) => $q->where('pickup_rider_id', $rider->id)->orWhere('seller_rider_id', $rider->id))
            ->with('order.items.product', 'parcel.seller.sellerApplication', 'returnRequest')->findOrFail($id);   // someone else's: 404
    }

    public function returnPickup(int $return)
    {
        return view('courier.rider.return-pickup', ['rider' => $this->rider(), 'rs' => $this->myReturn($return)]);
    }

    public function acceptReturnPickup(int $return)
    {
        try {
            app(\App\Services\ReturnShipments::class)->acknowledge($this->myReturn($return), $this->rider());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Return pickup accepted. Go to the buyer, take a photo of the item and scan its waybill.');
    }

    public function confirmReturnPickup(Request $request, int $return)
    {
        $data = $request->validate([
            'tracking_number' => 'required|string|max:50',
            'proof_photo'     => 'required|file|image|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
        ], [
            'tracking_number.required' => 'Scan the waybill or type its tracking number.',
            'proof_photo.required'     => 'Take a photo of the item as proof of pickup.',
            'proof_photo.image'        => 'The proof of pickup must be a JPG or PNG photo.',
            'proof_photo.max'          => 'The photo must be 5 MB or smaller.',
        ]);
        try {
            app(\App\Services\ReturnShipments::class)->confirmPickup($this->myReturn($return), $this->rider(), $data['tracking_number'], $request->file('proof_photo'));
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['tracking_number' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('courier.rider')->with('success', 'Return picked up. Bring it to the hub.');
    }

    public function returnHandedToSeller(Request $request, int $return)
    {
        $request->validate(['proof_photo' => 'nullable|file|image|mimes:jpg,jpeg,png|max:5120']);
        try {
            app(\App\Services\ReturnShipments::class)->handedToSeller($this->myReturn($return), $this->rider(), $request->file('proof_photo'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('courier.rider')->with('success', 'Returned item handed back to the seller.');
    }

    public function toggleOnline(Request $request)
    {
        $rider = $this->rider();
        if ($rider->isTruck()) {
            return redirect()->route('courier.trips')->with('info', 'Go Online here: answer the truck checklist first.');
        }
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
        // The proof photo is required on the website; the app sends it once its pickup screen has the camera step.
        $photo = $this->scanRequired($request) ? 'required' : 'nullable';
        $data = $request->validate([
            'tracking_number' => 'required|string|max:50',
            'proof_photo'     => $photo . '|file|image|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
        ], [
            'tracking_number.required' => 'Scan the waybill or type its tracking number.',
            'proof_photo.required'     => 'Take a photo of the parcel as proof of pickup.',
            'proof_photo.image'        => 'The proof of pickup must be a JPG or PNG photo.',
            'proof_photo.mimes'        => 'The proof of pickup must be a JPG or PNG photo.',
            'proof_photo.mimetypes'    => 'The proof of pickup must be a JPG or PNG photo.',
            'proof_photo.max'          => 'The photo must be 5 MB or smaller.',
        ]);

        return DB::transaction(function () use ($rider, $pickup, $data, $request) {
            $pickup = ParcelPickup::where('rider_id', $rider->id)->lockForUpdate()->findOrFail($pickup);
            $parcel = $pickup->parcel;

            if ($pickup->status !== 'assigned') {
                return back()->with('error', 'This parcel is already picked up (' . $pickup->picked_up_at?->format('M j, g:i A') . ').');
            }
            if (! $pickup->acknowledged_at) {
                return back()->with('error', 'Accept this pickup job first, then confirm the pickup at the seller.');
            }
            if (! self::scanMatches($data['tracking_number'], $parcel)) {
                return back()->withInput()->withErrors(['tracking_number' => 'This waybill does not match this job. Check the label on the parcel.']);
            }

            $proof = $request->hasFile('proof_photo') ? $request->file('proof_photo')->store('proof-of-pickup', \App\Support\ProtectedFile::DISK) : null;
            $pickup->update(['status' => 'picked_up', 'picked_up_at' => now(), 'proof_path' => $proof]);
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

    /** Assigned → Out for Delivery. The buyer is told the rider is on the way. */
    public function startDelivery(int $delivery)
    {
        $rider = $this->rider();

        return DB::transaction(function () use ($rider, $delivery) {
            $delivery = ParcelDelivery::where('rider_id', $rider->id)->lockForUpdate()->findOrFail($delivery);
            if ($delivery->status !== 'assigned') {
                return back()->with('error', 'This delivery is already ' . $delivery->statusLabel() . '.');
            }

            $delivery->update(['status' => 'out_for_delivery', 'picked_up_at' => now()]);
            $parcel = $delivery->parcel;
            $parcel->update(['status' => 'in_transit']);
            $svc = app(ParcelService::class);
            $svc->syncOrder($parcel);
            $svc->notifyBuyer($parcel, 'Out for delivery',
                "{$svc->orderRef($parcel)} is out for delivery with {$rider->full_name}. Please be ready to receive it.");

            return back()->with('success', 'Out for delivery. Take a proof-of-delivery photo when you hand the parcel over.');
        });
    }

    /** Out for Delivery → Delivered: the proof-of-delivery photo and, for COD, the exact amount collected (A24). */
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
            // Proof of delivery: the parcel photo and the rider's scan of its waybill.
            'tracking_number' => ($this->scanRequired($request) ? 'required' : 'nullable') . '|string|max:50',
            'cod_collected' => $cod ? 'required|numeric|min:0' : 'nullable',
        ], [
            'tracking_number.required' => 'Scan the waybill on the parcel.',
            'pod_photo.required'  => 'Take a proof-of-delivery photo.',
            'pod_photo.image'     => 'The proof of delivery must be a JPG or PNG photo.',
            'pod_photo.mimes'     => 'The proof of delivery must be a JPG or PNG photo.',
            'pod_photo.mimetypes' => 'The proof of delivery must be a JPG or PNG photo.',
            'pod_photo.max'       => 'The photo must be 5 MB or smaller.',
            'cod_collected.required' => 'Enter the COD amount you collected.',
        ]);

        if ($request->filled('tracking_number') && ! self::scanMatches($request->tracking_number, $delivery->parcel)) {
            return back()->withInput()->withErrors(['tracking_number' => 'This waybill does not match this delivery. Check the label on the parcel.']);
        }
        if ($cod && round((float) $request->cod_collected, 2) !== round((float) $delivery->cod_amount, 2)) {
            return back()->withInput()->withErrors(['cod_collected' => 'The COD amount doesn\'t match. Collect exactly ₱' . number_format($delivery->cod_amount, 2) . '.']);
        }

        DB::transaction(function () use ($request, $delivery, $cod, $rider) {
            $delivery->update([
                'status' => 'delivered', 'delivered_at' => now(),
                'pod_path' => $request->file('pod_photo')->store('proof-of-delivery', \App\Support\ProtectedFile::DISK),
                'cod_collected' => $cod ? round((float) $delivery->cod_amount, 2) : 0,
            ]);
            $parcel = $delivery->parcel;
            $parcel->update(['status' => 'delivered']);

            $svc = app(ParcelService::class);
            $svc->syncOrder($parcel);   // every parcel delivered → order Delivered (a COD order paid)
            // The buyer and seller are told by the tracking event (ShipmentTracker).
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
            // Refused, or the last attempt (a wrong address always goes back to the seller): the order is cancelled and the parcel stays at the hub (no Return to Sender).
            $final = $data['reason'] === 'Refused' || ($data['reason'] !== 'Wrong address' && $delivery->attempt >= ParcelDelivery::MAX_ATTEMPTS);
            $delivery->update(['status' => 'failed', 'failure_reason' => $data['reason'], 'remarks' => $data['reason']]);
            $parcel->update(['status' => 'failed', 'failure_reason' => $data['reason']]);

            $svc = app(ParcelService::class);
            $ref = $svc->orderRef($parcel);
            $attempt = "attempt {$delivery->attempt} of " . ParcelDelivery::MAX_ATTEMPTS;
            \App\Models\ActivityLog::note('Failed delivery ' . $parcel->tracking_number . ': ' . $data['reason'], $parcel);
            if ($final) {
                $svc->cancelUndeliverable($parcel->fresh(), "{$data['reason']}, {$attempt}");
                return redirect()->route('courier.rider')->with('success', "{$parcel->tracking_number} could not be delivered, so the order was cancelled. Bring the parcel back to the hub.");
            }

            // Wrong address: no point trying again — the parcel goes back to the seller (Return to Sender).
            if ($data['reason'] === 'Wrong address') {
                $parcel->update(['status' => 'return_to_sender', 'failure_reason' => 'Wrong address']);
                $svc->syncOrder($parcel->fresh());
                $svc->notifyBuyer($parcel, 'Delivery failed: wrong address', "{$ref} could not be delivered because the address is wrong. The parcel is being returned to the seller.", 'warning');
                $svc->notifySeller($parcel, 'Parcel coming back', "{$parcel->tracking_number} could not be delivered (wrong address). A rider will bring it back to you; confirm when you receive it.", 'warning');
                if ($scId = $parcel->current_sorting_center_id) {
                    $svc->notify((int) $scId, 'Return to seller', "{$parcel->tracking_number}: wrong address. Assign a rider to bring it back to the seller.", 'warning', route('sc.rts'));
                }
                return redirect()->route('courier.rider')->with('success', "{$parcel->tracking_number}: wrong address. Bring the parcel back to the hub; it will be returned to the seller.");
            }

            // Buyer not home (and anything else not final): rescheduled at once for the next delivery run.
            $parcel->update(['status' => 'sorted']);
            $svc->notifyBuyer($parcel, 'Delivery rescheduled', "{$ref} could not be delivered: {$data['reason']} ({$attempt}). We'll deliver it again on the next delivery run, so please be home to receive it.", 'warning');
            if ($scId = $parcel->current_sorting_center_id) {
                $svc->notify((int) $scId, 'Delivery rescheduled', "{$parcel->tracking_number}: {$data['reason']} ({$attempt}). It is back in the queue: assign a rider for the next attempt.", 'warning', route('sc.delivery-assignment', ['area_id' => $parcel->area_id]));
            }
            $svc->syncOrder($parcel->fresh());

            return redirect()->route('courier.rider')->with('success', "Failed delivery recorded ({$attempt}). It was rescheduled for another attempt; bring the parcel back to the hub.");
        });
    }

    // ── Profile, vehicle and documents ──────────────────────────

    public function profile(?int $rider = null)
    {
        $me = $this->rider();
        abort_if($rider !== null && $rider !== $me->id, 403, 'You can only view your own rider profile.');

        // The rider profile now lives on the Account page.
        return redirect()->to(route('courier.account') . '#rider-profile');
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

        return redirect()->to(route('courier.account') . '#rider-profile')->with('success', $vehicleChanged
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
        if ($rider->isTruck()) {
            return redirect()->route('courier.trips.history', $request->only('from', 'to'));
        }
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from'], ['to.after_or_equal' => 'The end date must be on or after the start date.']);
        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->from)->startOfDay() : null;
        $to   = $request->filled('to') ? \Carbon\Carbon::parse($request->to)->endOfDay() : null;
        $rows = $this->wallet->history($rider, $from, $to);

        return view('courier.rider.history', compact('rider', 'rows'));
    }

    /** Periods on Rider Pay. Days and weeks are counted in the business time zone (Philippine time), not UTC. */
    public const PAY_PERIODS = ['today' => 'Today', 'week' => 'This week', 'last_week' => 'Last week', 'month' => 'This month'];

    /** E-wallet: the pay the hub sent in, and the pay earned but not sent yet. */
    public function wallet()
    {
        $rider = $this->rider();
        if ($rider->isTruck()) {
            return redirect()->route('courier.trips.pay');
        }

        return view('courier.rider.wallet', [
            'rider'   => $rider,
            'balance' => $this->wallet->walletBalance($rider),
            'toSend'  => $this->wallet->toSend($rider),
            'unsent'  => $this->wallet->unsentJobs($rider),
            'entries' => $rider->walletEntries()->with('sortingCenter')->latest('id')->paginate(20),
            'pendingCashout' => $this->wallet->pendingCashout($rider),
            'lastGcash' => $rider->walletEntries()->where('type', 'cashout')->latest('id')->first(['gcash_number', 'gcash_name']),
        ]);
    }

    /** Cash out from the wallet to the rider's GCash; their logistics company sends it. */
    public function cashout(Request $request)
    {
        $rider = $this->rider();
        $data = $request->validate([
            'amount'       => 'required|numeric|min:' . RiderWallet::CASHOUT_MIN . '|max:1000000',
            'gcash_number' => ['required', 'regex:/^09\d{9}$/'],
            'gcash_name'   => 'required|string|min:2|max:120',
        ], [
            'amount.min'         => 'The smallest cash-out is ₱' . number_format(RiderWallet::CASHOUT_MIN, 2) . '.',
            'gcash_number.regex' => 'Enter your 11-digit GCash number, e.g. 09171234567.',
            'gcash_name.required' => 'Enter the name on your GCash account.',
        ]);
        try {
            $entry = $this->wallet->requestCashout($rider, (float) $data['amount'], $data['gcash_number'], trim($data['gcash_name']));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cash-out of ₱' . number_format(abs((float) $entry->amount), 2) . ' requested. '
            . $rider->companyName() . ' will send it to your GCash ' . $entry->gcash_number . '.');
    }

    public function earnings(Request $request)
    {
        $rider = $this->rider();
        if ($rider->isTruck()) {
            return redirect()->route('courier.trips.pay', $request->only('period'));
        }
        $request->validate(['period' => 'nullable|in:' . implode(',', array_keys(self::PAY_PERIODS))]);
        $period = $request->input('period', 'week');
        $tz = config('app.business_timezone', 'Asia/Manila');
        $now = now($tz);

        $ranges = [
            'today'     => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week'      => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'month'     => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        ];
        $all = $this->wallet->history($rider);
        $in = fn (array $r) => $all->filter(fn ($j) => $j['at']->between($r[0], $r[1]))->values();
        $sum = fn ($jobs) => round((float) $jobs->sum('pay'), 2);

        // The three headline cards.
        $cards = collect(['today', 'week', 'month'])->mapWithKeys(fn ($k) => [$k => ['pay' => $sum($in($ranges[$k])), 'jobs' => $in($ranges[$k])->count()]]);

        // The chosen period: totals by kind, day by day, and every job.
        [$from, $to] = $ranges[$period];
        $jobs = $in($ranges[$period]);
        $paid = fn (string $type) => $jobs->where('type', $type)->where('pay', '>', 0);
        $breakdown = [
            'pickups'    => ['count' => $paid('Pickup')->count(), 'pay' => $sum($paid('Pickup'))],
            'deliveries' => ['count' => $paid('Delivery')->count(), 'pay' => $sum($paid('Delivery'))],
            'failed'     => $jobs->where('pay', 0)->count(),
            'cod'        => round((float) $jobs->sum('cod'), 2),
            'total'      => $sum($jobs),
        ];
        $days = collect();
        if ($period !== 'today') {
            for ($d = $from->copy()->startOfDay(); $d->lte($to) && $d->lte($now); $d->addDay()) {
                $day = $jobs->filter(fn ($j) => $j['at']->copy()->tz($tz)->isSameDay($d));
                $days->push(['label' => $period === 'month' ? $d->format('M j') : $d->format('D, M j'), 'pay' => $sum($day), 'jobs' => $day->count(), 'today' => $d->isSameDay($now)]);
            }
            $days = $period === 'month' ? $days->filter(fn ($d) => $d['jobs'] > 0)->values() : $days;
        }

        // What jobs already given to the rider will pay once done.
        $pending = ParcelPickup::where('rider_id', $rider->id)->where('status', 'assigned')->count() * RiderDispatch::PAY_PICKUP
            + ParcelDelivery::where('rider_id', $rider->id)->whereIn('status', ParcelDelivery::OPEN)->count() * RiderDispatch::PAY_DELIVERY;

        return view('courier.rider.earnings', [
            'rider' => $rider, 'tz' => $tz, 'period' => $period, 'from' => $from, 'to' => $to,
            'cards' => $cards, 'jobs' => $jobs, 'breakdown' => $breakdown, 'days' => $days,
            'peak' => max(1, (float) $days->max('pay')), 'pending' => round((float) $pending, 2),
            // Kept for anything that reads the old names.
            'today' => $in($ranges['today']), 'todayPay' => $cards['today']['pay'], 'weekPay' => $cards['week']['pay'],
        ]);
    }

    public function cod()
    {
        $rider = $this->rider();

        return view('courier.rider.cod', [
            'rider'       => $rider,
            'balance'     => $this->wallet->codBalance($rider),
            'next'        => $this->wallet->toRemit($rider),   // COD − the rider's unpaid pay = what to hand over
            'pending'     => $this->wallet->pendingRemittance($rider),
            'orders'      => $this->wallet->codOrders($rider),
            'remittances' => RiderRemittance::where('rider_id', $rider->id)->latest()->take(20)->get(),
            'token'       => (string) Str::uuid(),
        ]);
    }

    /**
     * Hand COD cash to the logistics company; it stays Pending until they confirm (A24, A37). The amount is worked
     * out, not typed: the COD on hand minus the rider's unpaid pay, which the rider keeps out of the cash.
     */
    public function remit(Request $request)
    {
        $rider = $this->rider();
        $data = $request->validate(['request_token' => 'required|uuid']);

        return Cache::lock('rider-remit:' . $rider->id, 10)->block(8, function () use ($rider, $data) {
            if ($sent = RiderRemittance::where('request_token', $data['request_token'])->first()) {
                return redirect()->route('courier.rider.cod.receipt', $sent)->with('success', 'Your remittance was already sent.');
            }
            if ($this->wallet->pendingRemittance($rider) > 0) {
                return back()->withErrors(['amount' => 'You already have a remittance waiting for confirmation.']);
            }
            $next = $this->wallet->toRemit($rider);
            if ($next['cod'] <= 0) {
                return back()->withErrors(['amount' => 'You have no COD to remit.']);
            }

            $remit = RiderRemittance::create(['rider_id' => $rider->id, 'sorting_center_id' => $rider->sorting_center_id,
                'amount' => $next['remit'], 'pay_kept' => $next['pay'], 'expected_amount' => $next['cod'], 'status' => 'pending', 'request_token' => $data['request_token']]);
            UserNotification::create(['user_id' => $rider->sorting_center_id, 'title' => 'COD remittance to confirm',
                'body' => "{$rider->full_name} remitted ₱" . number_format($remit->amount, 2) . ' of ₱' . number_format($next['cod'], 2) . ' COD'
                    . ($next['pay'] > 0 ? ', keeping ₱' . number_format($next['pay'], 2) . ' as their pay' : '') . '.', 'type' => 'info', 'link' => route('sc.remittances')]);
            \App\Models\ActivityLog::note('COD remittance ₱' . number_format($remit->amount, 2) . ($next['pay'] > 0 ? ' (pay kept ₱' . number_format($next['pay'], 2) . ')' : ''), $remit);

            return redirect()->route('courier.rider.cod.receipt', $remit)->with('success', 'Remittance of ₱' . number_format($remit->amount, 2) . ' sent'
                . ($next['pay'] > 0 ? ' (you kept ₱' . number_format($next['pay'], 2) . ' as your pay)' : '') . '. It stays Pending until ' . $rider->companyName() . ' confirms it.');
        });
    }

    /** The receipt for one of this rider's remittances (print or save as PDF from the browser). */
    public function remittanceReceipt(RiderRemittance $remittance)
    {
        $rider = $this->rider();
        abort_unless((int) $remittance->rider_id === (int) $rider->id, 404);
        $remittance->load(['sortingCenter', 'confirmer']);
        $deliveries = $remittance->coveredDeliveries();

        return view('courier.rider.remittance-receipt', [
            'remittance'  => $remittance,
            'rider'       => $rider,
            'deliveries'  => $deliveries,
            'carriedOver' => round(max(0, (float) $remittance->expected_amount - (float) $deliveries->sum('cod_collected')), 2),
            'tz'          => config('app.business_timezone'),
        ]);
    }
}
