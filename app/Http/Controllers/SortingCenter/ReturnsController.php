<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\ReturnShipment;
use App\Services\ReturnShipments;
use App\Services\RiderDispatch;
use Illuminate\Http\Request;

/**
 * Return Items: buyers' returns collected like a pickup and brought back to the seller (App\Services\ReturnShipments).
 * At the hub each item is processed like a parcel: 1 sort → 2 check → 3 scan for the record → 4 send (a rider to the
 * seller, or a hub-to-hub trip to the hub of the seller's town). The process page shows the steps one at a time.
 */
class ReturnsController extends Controller
{
    public const TABS = [
        'pickup'  => ['To pick up', ['requested', 'pickup_assigned']],
        'hub'     => ['Coming to the hub', ['picked_up']],
        'process' => ['At the hub', ['at_hub']],
        'seller'  => ['On the way', ['in_transit', 'to_seller']],
        'done'    => ['Done', ['returned', 'cancelled', 'missing']],
    ];

    public function __construct(private ReturnShipments $returns) {}

    private function mine() { return ReturnShipment::where('sorting_center_id', auth()->id()); }

    private function own(ReturnShipment $return): ReturnShipment
    {
        abort_unless((int) $return->sorting_center_id === (int) auth()->id(), 404);

        return $return;
    }

    public function index(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'pickup';
        $counts = collect(self::TABS)->map(fn ($t) => $this->mine()->whereIn('status', $t[1])->count());
        $items = $this->mine()->whereIn('status', self::TABS[$tab][1])
            ->with(['order.user', 'parcel.seller', 'returnRequest', 'pickupRider', 'sellerRider', 'nextHub', 'trip'])
            ->latest('updated_at')->paginate(20)->withQueryString();
        $riderChoices = app(RiderDispatch::class)->riderChoices((int) auth()->id());

        return view('sc.returns', compact('tab', 'counts', 'items', 'riderChoices'));
    }

    /** One return at the hub: the four steps, its record and the photos. */
    public function show(ReturnShipment $return)
    {
        $rs = $this->own($return)->load(['order', 'parcel.seller', 'returnRequest', 'pickupRider', 'sellerRider', 'currentHub', 'nextHub.company', 'trip', 'scans.hub', 'scans.actor']);
        $next = in_array($rs->status, ['picked_up', 'at_hub'], true) ? $this->returns->nextStopFrom($rs, auth()->user()) : null;
        $riderChoices = app(RiderDispatch::class)->riderChoices((int) auth()->id());

        return view('sc.return-process', compact('rs', 'next', 'riderChoices'));
    }

    private function run(callable $fn, string $success)
    {
        try {
            $fn();
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }
        return back()->with('success', $success);
    }

    public function assignPickup(Request $request, ReturnShipment $return)
    {
        $request->validate(['rider_id' => 'required|integer'], ['rider_id.required' => 'Choose a rider.']);
        return $this->run(fn () => $this->returns->assignPickup($return, auth()->user(), (int) $request->rider_id),
            'Rider assigned to collect the return of ' . $return->order?->number() . '.');
    }

    /** The item arrived (step 1 also does this). */
    public function receive(ReturnShipment $return)
    {
        return $this->run(fn () => $this->returns->receive($return, auth()->user(), auth()->user()),
            'Return of ' . $return->order?->number() . ' received at the hub. Sort it, check it and scan it next.');
    }

    public function sort(ReturnShipment $return)
    {
        $next = null;
        $res = $this->run(function () use ($return, &$next) { $next = $this->returns->sort($return, auth()->user(), auth()->user()); },
            'Sorted.');
        if ($next) {
            session()->flash('success', 'Sorted: ' . ($next['stop'] === 'hub' ? 'it goes to ' . $next['hub']->code . ' (the seller\'s town). Check it next.' : 'it goes to the seller from this hub. Check it next.'));
        }

        return $res;
    }

    public function check(Request $request, ReturnShipment $return)
    {
        $request->validate(['photo' => 'nullable|file|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120', 'note' => 'nullable|string|max:300'],
            ['photo.*' => 'The photo must be a JPG or PNG of up to 5 MB.']);

        return $this->run(fn () => $this->returns->check($return, auth()->user(), auth()->user(), $request->input('condition'), $request->input('note'), $request->file('photo')),
            'Checked. Scan its waybill for the record next.');
    }

    public function scan(Request $request, ReturnShipment $return)
    {
        return $this->run(fn () => $this->returns->scan($return, auth()->user(), auth()->user(), (string) $request->input('tracking_number', '')),
            'Waybill scanned for the record. Send it on next.');
    }

    /** Another town: onto a hub-to-hub trip (an open one on that route, or a new one); the trip page opens. */
    public function send(ReturnShipment $return)
    {
        try {
            $trip = $this->returns->sendToHub($return, auth()->user(), auth()->user());
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('sc.trips.show', $trip)->with('success', 'The return of ' . $return->order?->number() . " is on {$trip->code} to {$trip->destination?->code}."
            . ($trip->courier_id ? '' : ' Assign a truck; at loading, scan the driver\'s Handover QR as usual.'));
    }

    public function assignSeller(Request $request, ReturnShipment $return)
    {
        $request->validate(['rider_id' => 'required|integer'], ['rider_id.required' => 'Choose a rider.']);
        return $this->run(fn () => $this->returns->assignToSeller($return, auth()->user(), (int) $request->rider_id),
            'Rider assigned to bring the return of ' . $return->order?->number() . ' to the seller.');
    }
}
