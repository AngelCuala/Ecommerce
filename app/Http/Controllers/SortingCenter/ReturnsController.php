<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\ReturnShipment;
use App\Services\ReturnShipments;
use App\Services\RiderDispatch;
use Illuminate\Http\Request;

/** Return Items: buyers' returns collected like a pickup and brought back to the seller (App\Services\ReturnShipments). */
class ReturnsController extends Controller
{
    public const TABS = [
        'pickup' => ['To pick up', ['requested', 'pickup_assigned']],
        'hub'    => ['Coming to the hub', ['picked_up']],
        'seller' => ['Back to the seller', ['at_hub', 'to_seller']],
        'done'   => ['Done', ['returned', 'cancelled']],
    ];

    public function __construct(private ReturnShipments $returns) {}

    private function mine() { return ReturnShipment::where('sorting_center_id', auth()->id()); }

    public function index(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'pickup';
        $counts = collect(self::TABS)->map(fn ($t) => $this->mine()->whereIn('status', $t[1])->count());
        $items = $this->mine()->whereIn('status', self::TABS[$tab][1])
            ->with(['order.user', 'parcel.seller', 'returnRequest', 'pickupRider', 'sellerRider'])
            ->latest('updated_at')->paginate(20)->withQueryString();
        $riderChoices = app(RiderDispatch::class)->riderChoices((int) auth()->id());

        return view('sc.returns', compact('tab', 'counts', 'items', 'riderChoices'));
    }

    private function run(callable $fn, string $success)
    {
        try {
            $fn();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', $success);
    }

    public function assignPickup(Request $request, ReturnShipment $return)
    {
        $request->validate(['rider_id' => 'required|integer'], ['rider_id.required' => 'Choose a rider.']);
        return $this->run(fn () => $this->returns->assignPickup($return, auth()->user(), (int) $request->rider_id),
            'Rider assigned to collect the return of ' . $return->order?->number() . '.');
    }

    public function receive(ReturnShipment $return)
    {
        return $this->run(fn () => $this->returns->receive($return, auth()->user(), auth()->user()),
            'Return of ' . $return->order?->number() . ' received at the hub. Assign a rider to bring it to the seller.');
    }

    public function assignSeller(Request $request, ReturnShipment $return)
    {
        $request->validate(['rider_id' => 'required|integer'], ['rider_id.required' => 'Choose a rider.']);
        return $this->run(fn () => $this->returns->assignToSeller($return, auth()->user(), (int) $request->rider_id),
            'Rider assigned to bring the return of ' . $return->order?->number() . ' to the seller.');
    }
}
