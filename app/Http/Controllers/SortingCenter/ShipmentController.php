<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use App\Models\ParcelTransfer;
use App\Models\ShipmentException;
use App\Services\ShipmentTracker;
use App\Services\ShipmentWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The sorting center's Shipment Board: only parcels and transfers this center is responsible for
 * (it holds them, expects them, or sent / receives the transfer). Every action re-checks custody
 * and status on the server (ShipmentWorkflow); buttons are only shown when the action is valid.
 */
class ShipmentController extends Controller
{
    public const TABS = [
        'all'                => 'All parcels',
        'incoming'           => 'Incoming Packages',
        'awaiting_sorting'   => 'Awaiting Sorting',
        'sorting'            => 'Sorting in Progress',
        'ready'              => 'Ready for Dispatch',
        'outgoing_transfers' => 'Outgoing Transfers',
        'incoming_transfers' => 'Incoming Transfers',
        'in_transit'         => 'In Transit',
        'final_delivery'     => 'Awaiting Final Delivery',
        'delayed'            => 'Delayed',
        'exceptions'         => 'Exceptions',
        'completed'          => 'Completed',
    ];

    public function __construct(private ShipmentWorkflow $flow) {}

    private function me() { return auth()->user(); }

    /** Parcels this center may see: held here, or in a transfer from / to here. */
    private function visible()
    {
        $me = $this->me()->id;
        return Parcel::query()->where(fn ($q) => $q->where('current_sorting_center_id', $me)
            ->orWhereHas('transfers', fn ($t) => $t->whereIn('status', ParcelTransfer::ACTIVE)
                ->where(fn ($w) => $w->where('from_sorting_center_id', $me)->orWhere('to_sorting_center_id', $me))));
    }

    private function tabQuery(string $tab)
    {
        $me = $this->me()->id;
        $held = Parcel::where('current_sorting_center_id', $me);

        return match ($tab) {
            'incoming'           => (clone $held)->whereIn('status', ['pickup_approved', 'in_transit_to_hub']),
            'awaiting_sorting'   => (clone $held)->where('status', 'picked_up')->whereNull('transfer_status'),
            'sorting'            => (clone $held)->where('status', 'sorting')->whereNull('transfer_status'),
            'ready'              => (clone $held)->where(fn ($q) => $q->where(fn ($w) => $w->where('status', 'sorted')->whereNull('transfer_status'))
                ->orWhereHas('transfers', fn ($t) => $t->where('status', 'pending')->where('from_sorting_center_id', $me))),
            'outgoing_transfers' => Parcel::whereHas('transfers', fn ($t) => $t->whereIn('status', ParcelTransfer::ACTIVE)->where('from_sorting_center_id', $me)),
            'incoming_transfers' => Parcel::whereHas('transfers', fn ($t) => $t->whereIn('status', ['pending', 'in_transit'])->where('to_sorting_center_id', $me)),
            'in_transit'         => $this->visible()->where('status', 'in_transfer'),
            'final_delivery'     => (clone $held)->whereIn('status', ['sorted', 'assigned', 'in_transit', 'failed'])->whereNull('transfer_status')->whereNotNull('area_id'),
            'delayed'            => Parcel::whereHas('transfers', fn ($t) => $t->where('status', 'in_transit')->whereNotNull('delayed_at')
                ->where(fn ($w) => $w->where('from_sorting_center_id', $me)->orWhere('to_sorting_center_id', $me))),
            'exceptions'         => Parcel::whereHas('exceptions', fn ($e) => $e->where('status', 'open'))
                ->where(fn ($w) => $w->where('current_sorting_center_id', $me)
                    ->orWhereHas('exceptions', fn ($e) => $e->where('status', 'open')->where('sorting_center_id', $me))
                    ->orWhereHas('transfers', fn ($t) => $t->where('from_sorting_center_id', $me)->orWhere('to_sorting_center_id', $me))),
            'completed'          => (clone $held)->whereIn('status', ['delivered', 'returned', 'cancelled', 'lost', 'damaged']),
            default              => $this->visible(),   // 'all': the whole inventory this center handles
        };
    }

    public function index(Request $request)
    {
        $request->validate(['tab' => 'nullable|in:' . implode(',', array_keys(self::TABS)), 'q' => 'nullable|string|max:80',
            'status' => 'nullable|in:' . implode(',', array_keys(Parcel::STATUS_LABELS)),
            // The dashboard's counts open the board filtered the same way (bucket + date range by creation day).
            'bucket' => 'nullable|in:' . implode(',', array_keys(Parcel::BUCKETS)),
            'from' => 'nullable|date', 'to' => 'nullable|date' . ($request->filled('from') ? '|after_or_equal:from' : '')],
            ['to.after_or_equal' => 'The end date must be on or after the start date.']);
        $this->flow->flagDelayedTransfers();

        // The website's board is the inventory list (status links go to each job's page); the app keeps its tabs.
        $tab = $request->input('tab', $request->is('api/*') ? 'incoming' : 'all');
        $counts = collect(self::TABS)->map(fn ($l, $key) => $this->tabQuery($key)->count());

        // A dashboard count (bucket / dates) counts the parcels this center holds, so the list matches it.
        $parcels = (collect(['bucket', 'from', 'to'])->contains(fn ($k) => $request->filled($k)) && $tab === 'all' ? Parcel::where('current_sorting_center_id', $this->me()->id) : $this->tabQuery($tab))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->q);
                $like = '%' . \App\Support\ProductSearch::likeEscape($term) . '%';
                $orderId = (int) ltrim(preg_replace('/\D/', '', $term), '0');
                // An order number ("123", "#000123", "ORD-123") finds that order only, not every code containing the digits.
                if ($orderId > 0 && preg_match('/^(#|ORD-?)?\d{1,7}$/i', $term)) {
                    $q->where(fn ($w) => $w->where('order_id', $orderId)->orWhere('tracking_number', $term)->orWhere('waybill_number', $term));
                    return;
                }
                $q->where(fn ($w) => $w->where('tracking_number', 'like', $like)->orWhere('waybill_number', 'like', $like)
                    ->orWhere('destination_municipality', 'like', $like)->orWhere('receiver_name', 'like', $like)
                    ->orWhereHas('seller', fn ($s) => $s->where('name', 'like', $like))
                    ->when($orderId > 0 && preg_match('/^(#|ORD-?)?\d+$/i', $term), fn ($o) => $o->orWhere('order_id', $orderId)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('bucket'), fn ($q) => $q->whereIn('status', Parcel::BUCKETS[$request->bucket]))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', \Carbon\Carbon::parse($request->from)->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', \Carbon\Carbon::parse($request->to)->endOfDay()))
            ->with(['order', 'seller', 'area', 'nextCenter', 'exceptions', 'activeTransfer.toSortingCenter', 'activeTransfer.fromSortingCenter', 'pickup.rider', 'parcelDelivery.rider',
                'events' => fn ($e) => $e->latest('occurred_at')->latest('id')->limit(1)])
            ->latest('updated_at')->paginate(25)->withQueryString();

        $next = $parcels->getCollection()->mapWithKeys(fn ($p) => [$p->id => $p->isAtHub() ? $this->flow->nextDestination($p, $this->me()) : null]);

        return view('sc.tracking.index', compact('tab', 'counts', 'parcels', 'next'));
    }

    public function show(Parcel $parcel)
    {
        $this->authorizeParcel($parcel);
        $me = $this->me();
        $parcel->load(['order.items.product', 'seller', 'transfers.fromSortingCenter', 'transfers.toSortingCenter', 'transfers.transporter',
            'exceptions.reporter', 'pickups.rider', 'deliveries.rider', 'currentSortingCenter']);
        $events = $parcel->order ? app(ShipmentTracker::class)->timeline($parcel->order, true)->where('parcel_id', $parcel->id)->values()
            : $parcel->events()->orderBy('occurred_at')->get();

        $transfer = $parcel->transfers->first(fn ($t) => in_array($t->status, ParcelTransfer::ACTIVE, true));
        $canReceive = ($parcel->status === 'in_transit_to_hub' && (int) $parcel->current_sorting_center_id === $me->id)
            || ($parcel->status === 'in_transfer' && $transfer?->isInTransit());
        $next = $parcel->isAtHub() && (int) $parcel->current_sorting_center_id === $me->id ? $this->flow->nextDestination($parcel, $me) : null;
        $hubs = $this->flow->eligibleHubs($me);
        $transporters = \App\Models\Rider::where('sorting_center_id', $me->id)->where('application_status', 'approved')
            ->where('is_active', true)->where('can_transport_transfers', true)->orderBy('full_name')->get();

        // This hub serves the address: its barangays to sort into, the buyer's one picked from the address.
        $areas = ($next['type'] ?? null) === 'local' ? \App\Models\DeliveryArea::where('sorting_center_id', $me->id)->orderBy('name')->get() : collect();
        $suggestedArea = $areas->isNotEmpty() ? app(\App\Services\ParcelService::class)->suggestArea($parcel, $areas) : null;

        return view('sc.tracking.show', compact('parcel', 'events', 'transfer', 'canReceive', 'next', 'hubs', 'transporters', 'areas', 'suggestedArea'));
    }

    /** Confirm Package Received: only with the explicit confirmation, after checking the parcel. */
    public function receive(Request $request, Parcel $parcel)
    {
        $this->authorizeParcel($parcel);
        $data = $request->validate([
            'confirm_received' => 'accepted',
            'condition'        => 'nullable|in:good,damaged',
            'notes'            => 'nullable|string|max:500|required_if:condition,damaged',
        ], [
            'confirm_received.accepted' => 'Tick the box to confirm the parcel is physically here and you checked it.',
            'notes.required_if'         => 'Describe the damage.',
        ]);
        try {
            $message = $this->flow->confirmReceipt($parcel, $this->me(), auth()->user(), $data['condition'] ?? 'good', $data['notes'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(str_contains($message, 'NOT received') ? 'error' : 'success', $message);
    }

    public function startSorting(Parcel $parcel)
    {
        $this->authorizeParcel($parcel);
        try {
            $this->flow->startSorting($parcel, $this->me(), auth()->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', "Sorting started for {$parcel->tracking_number}.");
    }

    /** Flag Shipment Exception (damage found later, missing item, other). */
    public function reportException(Request $request, Parcel $parcel)
    {
        $this->authorizeParcel($parcel);
        $data = $request->validate([
            'type'    => 'required|in:damaged_on_arrival,missing,other',
            'details' => 'required|string|min:5|max:500',
        ], ['details.required' => 'Describe the problem.']);
        $this->flow->openException($parcel, $data['type'], $data['details'], $this->me(), auth()->user(), $parcel->activeTransfer?->id);

        return back()->with('success', 'Flagged. The parcel is on hold until an administrator resolves it.');
    }

    /** Allow / stop one of this center's riders carrying parcels between hubs (separate from last-mile work). */
    public function toggleTransport(\App\Models\Rider $rider)
    {
        abort_unless((int) $rider->sorting_center_id === (int) $this->me()->id, 403, 'This rider belongs to another sorting center.');
        $rider->forceFill(['can_transport_transfers' => ! $rider->can_transport_transfers])->save();

        return back()->with('success', $rider->full_name . ($rider->can_transport_transfers ? ' can now transport hub transfers.' : ' can no longer transport hub transfers.'));
    }

    private function authorizeParcel(Parcel $parcel): void
    {
        $me = $this->me()->id;
        $allowed = (int) $parcel->current_sorting_center_id === $me
            || $parcel->transfers()->where(fn ($w) => $w->where('from_sorting_center_id', $me)->orWhere('to_sorting_center_id', $me))->exists()
            // A parcel in transit to another hub that physically turned up here (found by scanning its label).
            || ($parcel->status === 'in_transfer' && in_array($parcel->id, (array) session('sc.scanned', []), true));
        abort_unless($allowed, 403, 'This parcel is handled by another sorting center.');
    }
}
