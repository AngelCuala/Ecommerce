<?php

namespace App\Http\Controllers\Admin\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelTransfer;
use App\Models\ShipmentEvent;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\ShipmentTracker;
use App\Services\ShipmentWorkflow;
use App\Support\ServiceArea;
use Illuminate\Http\Request;

/**
 * Administrator / dispatcher view of every shipment: statuses across sorting centers, active and
 * delayed transfers, open exceptions, failed attempts, and the full event history. Exception
 * resolutions and record corrections always need a reason, and never erase earlier events.
 */
class ShipmentController extends Controller
{
    public function __construct(private ShipmentWorkflow $flow) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:' . implode(',', array_keys(Parcel::STATUS_LABELS)),
            'center' => 'nullable|integer',
            'view'   => 'nullable|in:all,transfers,delayed,exceptions,failed',
            'q'      => 'nullable|string|max:80',
        ]);
        $this->flow->flagDelayedTransfers();
        $view = $request->input('view', 'all');

        $parcels = Parcel::query()
            ->with(['order', 'seller', 'currentSortingCenter', 'activeTransfer.toSortingCenter', 'events' => fn ($e) => $e->latest('occurred_at')->latest('id')->limit(1)])
            ->withCount(['exceptions as open_exceptions' => fn ($e) => $e->where('status', 'open')])
            ->when($view === 'transfers', fn ($q) => $q->whereHas('transfers', fn ($t) => $t->whereIn('status', ParcelTransfer::ACTIVE)))
            ->when($view === 'delayed', fn ($q) => $q->whereHas('transfers', fn ($t) => $t->where('status', 'in_transit')->whereNotNull('delayed_at')))
            ->when($view === 'exceptions', fn ($q) => $q->whereHas('exceptions', fn ($e) => $e->where('status', 'open')))
            ->when($view === 'failed', fn ($q) => $q->whereHas('deliveries', fn ($d) => $d->where('status', 'failed')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('center'), fn ($q) => $q->where('current_sorting_center_id', (int) $request->center))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%' . \App\Support\ProductSearch::likeEscape(trim($request->q)) . '%';
                $q->where(fn ($w) => $w->where('tracking_number', 'like', $like)->orWhere('waybill_number', 'like', $like)->orWhere('destination_municipality', 'like', $like));
            })
            ->latest('updated_at')->paginate(25)->withQueryString();

        $stats = [
            'transfers'  => ParcelTransfer::whereIn('status', ['pending', 'in_transit'])->count(),
            'delayed'    => ParcelTransfer::where('status', 'in_transit')->whereNotNull('delayed_at')->count(),
            'exceptions' => ShipmentException::where('status', 'open')->count(),
            'failed'     => ParcelDelivery::where('status', 'failed')->where('updated_at', '>=', now()->subDays(7))->count(),
        ];
        $centers = User::where('role', 'sorting_center')->orderBy('name')->get(['id', 'name']);

        return view('admin.shipments.index', compact('parcels', 'stats', 'centers', 'view'));
    }

    public function show(Parcel $parcel)
    {
        $parcel->load(['order.items.product', 'seller', 'currentSortingCenter', 'transfers.fromSortingCenter', 'transfers.toSortingCenter',
            'transfers.transporter', 'exceptions.reporter', 'exceptions.resolver', 'exceptions.sortingCenter', 'pickups.rider', 'deliveries.rider']);
        $events = $parcel->order
            ? app(ShipmentTracker::class)->timeline($parcel->order, true)->filter(fn ($e) => $e->parcel_id === null || $e->parcel_id === $parcel->id)->values()
            : $parcel->events()->orderBy('occurred_at')->get();
        $hubs = User::where('role', 'sorting_center')->where('approval_status', 'approved')
            ->when(ServiceArea::enabled(), fn ($q) => $q->whereIn('assigned_municipality_code', ServiceArea::localityCodes()))->orderBy('name')->get();

        return view('admin.shipments.show', compact('parcel', 'events', 'hubs'));
    }

    public function resolveException(Request $request, ShipmentException $exception)
    {
        $data = $request->validate([
            'action'    => 'required|in:note,custody,lost',
            'reason'    => 'required|string|min:10|max:500',
            'center_id' => 'nullable|required_if:action,custody|integer',
        ], [
            'reason.required'       => 'Record why: every resolution needs a reason (10 to 500 characters).',
            'center_id.required_if' => 'Choose the sorting center that physically has the parcel.',
        ]);
        try {
            $this->flow->resolveException($exception, auth()->user(), $data['action'], $data['reason'], $data['center_id'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        \App\Models\ActivityLog::note('Resolved shipment exception #' . $exception->id . ' (' . $data['action'] . '): ' . $data['reason'], $exception->parcel);

        return back()->with('success', 'Exception resolved. The resolution was added to the shipment history.');
    }

    /** A correction is appended next to the original event; the original stays as it was. */
    public function correctEvent(Request $request, ShipmentEvent $event)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:500'], ['reason.required' => 'Explain the correction (10 to 500 characters).']);
        $this->flow->correctEvent($event, auth()->user(), $data['reason']);
        \App\Models\ActivityLog::note('Corrected shipment event #' . $event->id . ': ' . $data['reason'], $event->parcel);

        return back()->with('success', 'Correction recorded. The original event is kept.');
    }

    /** Allow / stop a rider carrying parcels between sorting centers (separate from last-mile work). */
    public function toggleTransport(Request $request, \App\Models\Rider $rider)
    {
        $rider->forceFill(['can_transport_transfers' => ! $rider->can_transport_transfers])->save();
        \App\Models\ActivityLog::note(($rider->can_transport_transfers ? 'Allowed' : 'Stopped') . ' hub transfers for rider ' . $rider->full_name, $rider);

        return back()->with('success', $rider->full_name . ($rider->can_transport_transfers ? ' can now transport hub transfers.' : ' can no longer transport hub transfers.'));
    }
}
