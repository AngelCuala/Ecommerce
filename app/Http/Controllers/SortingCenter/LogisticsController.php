<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Models\RiderJobOffer;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\UserNotification;
use App\Services\ParcelService;
use App\Services\RiderDispatch;
use App\Services\ShippingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Logistics workbook (L1–L8) pages of the sorting-center portal: the shipment list behind the
 * dashboard counts, reassign / bulk assign, Lost / Damaged exceptions, the Return-to-Sender
 * queue, shipping rates and zones, and the daily COD reconciliation.
 */
class LogisticsController extends Controller
{
    public function __construct(private RiderDispatch $dispatch, private ParcelService $parcels, private ShippingService $shipping) {}

    private function scId(): int
    {
        return (int) auth()->id();
    }

    private function myParcels()
    {
        return Parcel::where('current_sorting_center_id', $this->scId());
    }

    private function guard(Parcel $parcel): void
    {
        abort_unless((int) $parcel->current_sorting_center_id === $this->scId(), 403, 'This parcel is handled by another logistics company.');
    }

    /** The date filter (both days included); null when not set. Throws a validation error for an end before the start. */
    private function dateRange(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date' . ($request->filled('from') ? '|after_or_equal:from' : ''),
        ], ['to.after_or_equal' => 'The end date must be on or after the start date.']);

        return [$request->filled('from') ? Carbon::parse($request->from)->startOfDay() : null,
                $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : null];
    }

    /** Parcels of this company in a dashboard bucket and date range (by when the parcel was created). */
    public function bucketQuery(?string $bucket, ?Carbon $from = null, ?Carbon $to = null)
    {
        return $this->myParcels()
            ->when($bucket && isset(Parcel::BUCKETS[$bucket]), fn ($q) => $q->whereIn('status', Parcel::BUCKETS[$bucket]))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
    }

    /** Count per bucket, for the dashboard and the shipment list tabs. */
    public function bucketCounts(?Carbon $from = null, ?Carbon $to = null): array
    {
        $out = [];
        foreach (array_keys(Parcel::BUCKETS) as $bucket) {
            $out[$bucket] = $this->bucketQuery($bucket, $from, $to)->count();
        }
        return $out;
    }

    // ── L1 / L5: shipment list with status, date and search ─────

    public function shipments(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $status = $request->get('status');
        $q = trim((string) $request->get('q'));

        $query = $this->bucketQuery($status, $from, $to)->with(['order', 'seller'])
            ->when($q !== '', function ($query) use ($q) {
                $id = ctype_digit(ltrim(preg_replace('/^(ORD|#)-?/i', '', $q), '0')) ? (int) preg_replace('/\D/', '', $q) : null;
                $query->where(fn ($w) => $w->where('tracking_number', $q)
                    ->orWhere('receiver_name', 'like', '%' . $q . '%')
                    ->when($id, fn ($w) => $w->orWhere('order_id', $id)));
            });

        $parcels = $query->latest()->paginate(25)->withQueryString();
        $counts  = $this->bucketCounts($from, $to);

        return view('sc.shipments', compact('parcels', 'counts', 'status', 'q'));
    }

    // ── L3: reassign before pickup, bulk assignment ───────────────

    public function reassign(Request $request, Parcel $parcel)
    {
        $this->guard($parcel);
        $data = $request->validate(['rider_id' => 'required|integer'], ['rider_id.required' => 'Choose a rider.']);
        $rider = Rider::where('sorting_center_id', $this->scId())->find($data['rider_id']);
        if (! $rider) {
            return back()->with('error', 'Choose one of your own riders.');
        }

        try {
            [$old, $kind] = DB::transaction(function () use ($parcel, $rider) {
                $parcel = Parcel::lockForUpdate()->findOrFail($parcel->id);
                $pickup   = ParcelPickup::where('parcel_id', $parcel->id)->where('status', 'assigned')->first();
                $delivery = ParcelDelivery::where('parcel_id', $parcel->id)->where('status', 'assigned')->first();
                if (! $pickup && ! $delivery) {
                    throw new \RuntimeException("{$parcel->tracking_number} was already picked up, so it can't be reassigned.");
                }
                $current = $pickup ?: $delivery;
                if ((int) $current->rider_id === (int) $rider->id) {
                    throw new \RuntimeException("{$rider->full_name} already has this job.");
                }
                if ($reason = $this->dispatch->ineligibleReason($rider, $this->scId())) {
                    throw new \RuntimeException($reason);
                }
                $old = Rider::find($current->rider_id);
                $parcel->timeline(ucfirst($kind = $pickup ? 'pickup' : 'delivery') . ' taken from rider ' . ($old?->full_name ?? '—'));
                if ($pickup) {
                    $pickup->update(['status' => 'cancelled', 'remarks' => 'Reassigned to ' . $rider->full_name . '.']);
                } else {
                    $delivery->delete();   // the attempt never started
                    Parcel::whereKey($parcel->id)->update(['status' => 'sorted']);   // back in the queue, not a new timeline step
                }
                $kind = $this->dispatch->assignManually($parcel->fresh(), $rider, auth()->user());
                return [$old, $kind];
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($old) {
            UserNotification::create(['user_id' => $old->user_id, 'title' => 'Job reassigned',
                'body' => 'The ' . $kind . ' of ' . $parcel->tracking_number . ' was given to another rider.', 'type' => 'warning', 'link' => route('courier.rider')]);
        }
        UserNotification::create(['user_id' => $rider->user_id, 'title' => 'New ' . $kind . ' job',
            'body' => 'You were assigned the ' . $kind . ' of ' . $parcel->tracking_number . '.', 'type' => 'info', 'link' => route('courier.rider')]);
        ActivityLog::change($old?->full_name ?? '—', $rider->full_name, 'Reassigned ' . $kind);
        ActivityLog::note($parcel->tracking_number . ' ' . $kind . ' reassigned from ' . ($old?->full_name ?? '—') . ' to ' . $rider->full_name, $parcel);

        return back()->with('success', "{$parcel->tracking_number} reassigned to {$rider->full_name}.");
    }

    public function bulkAssign(Request $request)
    {
        $data = $request->validate([
            'rider_id'     => 'required|integer',
            'parcel_ids'   => 'required|array|min:1',
            'parcel_ids.*' => 'integer',
        ], ['parcel_ids.required' => 'Select at least one parcel.', 'rider_id.required' => 'Choose a rider.']);
        $rider = Rider::where('sorting_center_id', $this->scId())->find($data['rider_id']);
        if (! $rider) {
            return back()->with('error', 'Choose one of your own riders.');
        }

        $assigned = [];
        $skipped  = [];
        foreach ($this->myParcels()->whereIn('id', $data['parcel_ids'])->orderBy('id')->get() as $parcel) {
            try {
                $this->dispatch->assignManually($parcel, $rider->fresh(), auth()->user());
                $assigned[] = $parcel->tracking_number;
            } catch (\RuntimeException $e) {
                $skipped[] = $parcel->tracking_number . ' (' . $e->getMessage() . ')';   // it stays in the queue
            }
        }
        if ($assigned) {
            UserNotification::create(['user_id' => $rider->user_id, 'title' => count($assigned) . ' new jobs',
                'body' => 'You were assigned ' . implode(', ', $assigned) . '.', 'type' => 'info', 'link' => route('courier.rider')]);
            ActivityLog::note(count($assigned) . ' parcel(s) assigned to ' . $rider->full_name . ': ' . implode(', ', $assigned));
        }

        $message = count($assigned) . ' assigned to ' . $rider->full_name . '.'
            . ($skipped ? ' ' . count($skipped) . ' skipped: ' . implode('; ', $skipped) . '.' : '');

        return back()->with($assigned ? ($skipped ? 'warning' : 'success') : 'error', $message);
    }

    // ── L5: Lost / Damaged ───────────────────────────────────────

    public function markException(Request $request, Parcel $parcel)
    {
        $this->guard($parcel);
        $data = $request->validate([
            'type'   => 'required|in:lost,damaged',
            'reason' => 'required|string|min:10|max:300',
            'photo'  => ($request->input('type') === 'damaged' ? 'required' : 'nullable') . '|file|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
        ], [
            'reason.required' => 'Give the reason (10 to 300 characters).',
            'reason.min'      => 'The reason must be 10 to 300 characters.',
            'reason.max'      => 'The reason must be 10 to 300 characters.',
            'photo.required'  => 'Add a photo of the damage.',
            'photo.mimes'     => 'The photo must be a JPG or PNG image.',
            'photo.mimetypes' => 'The photo must be a real JPG or PNG image.',
            'photo.max'       => 'The photo can be at most 5 MB.',
        ]);
        if (in_array($parcel->status, Parcel::CLOSED_STATUSES, true)) {
            return back()->with('error', "{$parcel->tracking_number} is already {$parcel->statusLabel()}.");
        }

        $old   = $parcel->statusLabel();
        $label = $data['type'] === 'lost' ? 'Lost' : 'Damaged';
        DB::transaction(function () use ($parcel, $data, $request) {
            ParcelPickup::where('parcel_id', $parcel->id)->whereIn('status', ParcelPickup::OPEN)->update(['status' => 'cancelled', 'remarks' => ucfirst($data['type']) . '.']);
            ParcelDelivery::where('parcel_id', $parcel->id)->whereIn('status', ParcelDelivery::OPEN)->update(['status' => 'failed', 'failure_reason' => $data['reason']]);
            RiderJobOffer::where('parcel_id', $parcel->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            $parcel->update([
                'status' => $data['type'], 'failure_reason' => $data['reason'],
                'exception_photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('parcel-exceptions', 'public') : null,
            ]);

            // The buyer can't receive it: the order is refunded (claim) — no stock comes back.
            $order = $parcel->order;
            if ($order && ! in_array($order->status, [Order::COMPLETED, Order::CANCELLED, Order::REFUNDED], true)) {
                $order->update(['status' => Order::REFUNDED]);
            }
        });

        ActivityLog::change($old, $label, $data['reason']);
        ActivityLog::note($parcel->tracking_number . ' marked ' . $label, $parcel);
        $ref = $this->parcels->orderRef($parcel);
        $this->parcels->notifyBuyer($parcel, 'Parcel ' . strtolower($label),
            "{$ref} was marked {$label} by the logistics company: {$data['reason']}. Your refund is on its way.", 'warning');
        $this->parcels->notifySeller($parcel, 'Parcel ' . strtolower($label),
            "{$ref} ({$parcel->tracking_number}) was marked {$label}: {$data['reason']}. A claim was opened with the logistics company.", 'warning');

        return back()->with('success', "{$parcel->tracking_number} marked {$label}. The buyer and seller were notified.");
    }

    // ── L6: Return to Sender ─────────────────────────────────────

    public function rtsQueue()
    {
        $parcels = $this->myParcels()->whereIn('status', ['return_to_sender'])->whereNull('returned_at')
            ->with(['order', 'seller', 'rtsRider', 'deliveries' => fn ($q) => $q->orderBy('attempt')])->oldest('updated_at')->get();
        $awaiting = $this->myParcels()->where('status', 'returned')->whereNull('returned_received_at')->with(['seller', 'order'])->latest('returned_at')->get();
        $riders = $this->dispatch->eligibleRiders($this->scId());

        return view('sc.rts', compact('parcels', 'awaiting', 'riders'));
    }

    public function assignRts(Request $request, Parcel $parcel)
    {
        $this->guard($parcel);
        $data = $request->validate(['rider_id' => 'required|integer'], ['rider_id.required' => 'Choose a rider.']);
        if ($parcel->status !== 'return_to_sender' || $parcel->returned_at) {
            return back()->with('error', "{$parcel->tracking_number} is not waiting to go back to the seller.");
        }
        $rider = Rider::where('sorting_center_id', $this->scId())->find($data['rider_id']);
        if (! $rider) {
            return back()->with('error', 'Choose one of your own riders.');
        }
        if ($reason = $this->dispatch->ineligibleReason($rider, $this->scId())) {
            return back()->with('error', $reason);
        }

        $previous = $parcel->rtsRider;
        if ($previous && (int) $previous->id === (int) $rider->id) {
            return back()->with('error', "{$rider->full_name} already has this return.");
        }
        $parcel->update(['rts_rider_id' => $rider->id, 'rts_assigned_at' => now()]);
        if ($previous) {
            UserNotification::create(['user_id' => $previous->user_id, 'title' => 'Return reassigned',
                'body' => 'The return of ' . $parcel->tracking_number . ' was given to another rider.', 'type' => 'warning', 'link' => route('courier.rider')]);
        }
        UserNotification::create(['user_id' => $rider->user_id, 'title' => 'Return to seller',
            'body' => 'Bring ' . $parcel->tracking_number . ' back to ' . ($parcel->seller->name ?? 'the seller') . ': ' . $parcel->pickup_address, 'type' => 'info', 'link' => route('courier.rider')]);
        ActivityLog::note($parcel->tracking_number . ' return trip assigned to ' . $rider->full_name, $parcel);

        return back()->with('success', "{$parcel->tracking_number} will be returned to the seller by {$rider->full_name}.");
    }

    // ── A35: this company's audit log (read only — there is no edit or delete) ──

    public function activity()
    {
        $logs = ActivityLog::where('admin_id', $this->scId())->latest('id')->paginate(30);

        return view('sc.activity', compact('logs'));
    }

    // ── L7: shipping rates and zones ─────────────────────────────

    public function rates()
    {
        $rates = $this->shipping->rateTable();
        $zones = ShippingZone::orderBy('name')->get();
        $uncovered = collect(config('shipping.unserviceable_provinces'))->reject(fn ($n, $code) => $zones->contains('province_code', $code));

        return view('sc.rates', compact('rates', 'zones', 'uncovered'));
    }

    private function rateRules(): array
    {
        $money = ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'];
        return [['first_kg' => $money, 'additional_kg' => $money], [
            'first_kg.*'      => 'The first-kg rate must be an amount of at least ₱0.01.',
            'additional_kg.*' => 'The rate for each additional kg must be an amount of at least ₱0.01.',
        ]];
    }

    public function updateRate(Request $request)
    {
        [$rules, $messages] = $this->rateRules();
        $data = $request->validate(['route' => ['required', \Illuminate\Validation\Rule::in(array_keys(config('shipping.rates')))]] + $rules, $messages);

        [$oldFirst, $oldExtra] = $this->shipping->rateTable()[$data['route']];
        ShippingRate::updateOrCreate(['route' => $data['route']],
            ['first_kg' => $data['first_kg'], 'additional_kg' => $data['additional_kg'], 'updated_by' => $this->scId()]);

        $label = str_replace('-', ' → ', $data['route']);
        ActivityLog::change("{$label}: ₱" . number_format($oldFirst, 2) . ' + ₱' . number_format($oldExtra, 2) . '/kg',
            "{$label}: ₱" . number_format($data['first_kg'], 2) . ' + ₱' . number_format($data['additional_kg'], 2) . '/kg', 'Shipping rate changed');
        ActivityLog::note('Shipping rate ' . $label . ' changed');

        return back()->with('success', "Rate for {$label} saved. It applies to checkouts started from now on.");
    }

    public function storeZone(Request $request)
    {
        [$rules, $messages] = $this->rateRules();
        $data = $request->validate(['province_code' => 'required|string', 'name' => 'nullable|string|max:60'] + $rules, $messages);

        $province = $this->shipping->provinceOf($data['province_code']);
        $name = config('shipping.unserviceable_provinces')[$province] ?? null;
        if (! $name) {
            return back()->withErrors(['province_code' => 'Choose one of the provinces no zone covers yet.']);
        }
        if (ShippingZone::where('province_code', $province)->exists()) {
            return back()->withErrors(['province_code' => "{$name} already has a zone."]);
        }

        ShippingZone::create(['name' => ($data['name'] ?? null) ?: $name, 'province_code' => $province, 'province_name' => $name,
            'first_kg' => $data['first_kg'], 'additional_kg' => $data['additional_kg'], 'created_by' => $this->scId()]);
        ActivityLog::change('Not serviceable', "Zone {$name}: ₱" . number_format($data['first_kg'], 2) . ' + ₱' . number_format($data['additional_kg'], 2) . '/kg', 'Zone added');
        ActivityLog::note('Delivery zone added for ' . $name);

        return back()->with('success', "{$name} is now covered. Buyers there can check out.");
    }
}
