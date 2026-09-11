<?php

namespace App\Http\Controllers;

use App\Models\DeliveryArea;
use App\Models\Message;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\Rider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SortingCenterController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────────
    public function dashboard()
    {
        $stats = [
            'total_parcels_today' => Parcel::whereDate('created_at', today())->count(),
            'in_transit'          => ParcelDelivery::where('status', 'out_for_delivery')->count(),
            'delivered_today'     => ParcelDelivery::where('status', 'delivered')->whereDate('delivered_at', today())->count(),
            'active_riders'       => Rider::where('application_status', 'approved')->where('is_active', true)->count(),
            'pending_pickup'      => Parcel::where('status', 'pending_pickup')->count(),
            'sorting_queue'       => Parcel::where('status', 'picked_up')->count(),
        ];

        $recentParcels = ParcelDelivery::with(['parcel.seller', 'rider', 'area'])
            ->latest()->take(6)->get();

        $areaRates = DeliveryArea::select('delivery_areas.id', 'delivery_areas.name',
                DB::raw('COUNT(pd.id) as total'),
                DB::raw("SUM(CASE WHEN pd.status='delivered' THEN 1 ELSE 0 END) as delivered")
            )
            ->leftJoin('parcel_deliveries as pd', 'pd.area_id', '=', 'delivery_areas.id')
            ->groupBy('delivery_areas.id', 'delivery_areas.name')
            ->get();

        return view('sc.dashboard', compact('stats', 'recentParcels', 'areaRates'));
    }

    // ── Riders ────────────────────────────────────────────────
    public function riders(Request $request)
    {
        $tab = $request->get('tab', 'all');

        $query = Rider::with(['user', 'area']);

        if ($tab === 'pending')  $query->where('application_status', 'pending');
        if ($tab === 'active')   $query->where('application_status', 'approved')->where('is_active', true);
        if ($tab === 'inactive') $query->where('application_status', 'approved')->where('is_active', false);
        if ($tab === 'rejected') $query->where('application_status', 'rejected');

        $riders = $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'all'      => Rider::count(),
            'pending'  => Rider::where('application_status', 'pending')->count(),
            'active'   => Rider::where('application_status', 'approved')->where('is_active', true)->count(),
            'inactive' => Rider::where('application_status', 'approved')->where('is_active', false)->count(),
            'rejected' => Rider::where('application_status', 'rejected')->count(),
        ];

        return view('sc.riders', compact('riders', 'counts'));
    }

    public function approveRider(Rider $rider)
    {
        $rider->update(['application_status' => 'approved', 'is_active' => true, 'approved_at' => now(), 'approved_by' => auth()->id()]);
        return back()->with('success', "{$rider->full_name} approved.");
    }

    public function rejectRider(Request $request, Rider $rider)
    {
        $rider->update(['application_status' => 'rejected', 'rejection_reason' => $request->reason, 'is_active' => false]);
        return back()->with('success', "{$rider->full_name} rejected.");
    }

    public function toggleRider(Rider $rider)
    {
        $rider->update(['is_active' => !$rider->is_active]);
        return back()->with('success', "{$rider->full_name} " . ($rider->is_active ? 'activated' : 'deactivated') . '.');
    }

    // ── Pickup Requests ───────────────────────────────────────
    public function pickupRequests(Request $request)
    {
        $tab = $request->get('tab', 'all');

        $query = Parcel::with('seller');
        if ($tab === 'pending')   $query->where('status', 'pending_pickup');
        if ($tab === 'confirmed') $query->where('status', 'pickup_approved');
        if ($tab === 'approved')  $query->whereIn('status', ['picked_up', 'sorted', 'assigned', 'in_transit', 'delivered']);
        if ($tab === 'rejected')  $query->where('status', 'pickup_rejected');

        $requests = $query->latest()->paginate(15)->withQueryString();

        return view('sc.pickup-requests', compact('requests'));
    }

    public function approvePickup(Parcel $parcel)
    {
        $parcel->update(['status' => 'pickup_approved', 'verified_by' => auth()->id(), 'verified_at' => now()]);
        return back()->with('success', "Pickup for {$parcel->tracking_number} approved.");
    }

    public function rejectPickup(Request $request, Parcel $parcel)
    {
        $parcel->update(['status' => 'pickup_rejected', 'notes' => trim(($parcel->notes ?? '') . "\nRejected: " . $request->reason)]);
        return back()->with('success', "Pickup request rejected.");
    }

    // ── Incoming Parcels ──────────────────────────────────────
    public function incomingParcels(Request $request)
    {
        $tab = $request->get('tab', 'all');

        $query = Parcel::with(['seller', 'area']);
        if ($tab !== 'all') $query->where('status', $tab);
        else $query->whereIn('status', ['pickup_approved', 'picked_up', 'sorted', 'assigned']);

        if ($request->search) {
            $q = $request->search;
            $query->where(fn($x) => $x->where('tracking_number', 'like', "%$q%")
                ->orWhere('receiver_name', 'like', "%$q%"));
        }

        $parcels = $query->latest()->paginate(15)->withQueryString();

        $miniStats = [
            'received'    => Parcel::where('status', 'pickup_approved')->count(),
            'logged'      => Parcel::where('status', 'pickup_approved')->count(),
            'for_sorting' => Parcel::where('status', 'picked_up')->count(),
            'sorted'      => Parcel::where('status', 'sorted')->count(),
        ];

        return view('sc.incoming-parcels', compact('parcels', 'miniStats'));
    }

    public function advanceParcel(Parcel $parcel)
    {
        $next = match($parcel->status) {
            'pickup_approved' => 'picked_up',
            'picked_up'       => 'sorted',
            default           => $parcel->status,
        };
        $parcel->update(['status' => $next]);
        return back()->with('success', "{$parcel->tracking_number} advanced to " . ucwords(str_replace('_', ' ', $next)) . '.');
    }

    // ── Parcel Sorting ────────────────────────────────────────
    public function parcelSorting()
    {
        $areas = DeliveryArea::withCount(['parcels as sorted_count' => fn($q) => $q->where('status', 'sorted')])
            ->orderBy('name')->get();

        $pending     = Parcel::with('area')->where('status', 'picked_up')->get();
        $sortedToday = Parcel::where('status', 'sorted')->whereDate('updated_at', today())->count();

        return view('sc.parcel-sorting', compact('areas', 'pending', 'sortedToday'));
    }

    public function sortParcel(Request $request, Parcel $parcel)
    {
        $request->validate(['area_id' => 'required|exists:delivery_areas,id']);
        $parcel->update(['area_id' => $request->area_id, 'status' => 'sorted']);
        return back()->with('success', "{$parcel->tracking_number} sorted.");
    }

    // ── Delivery Assignment ───────────────────────────────────
    public function deliveryAssignment(Request $request)
    {
        $parcels = Parcel::with('area')
            ->where('status', 'sorted')
            ->when($request->area_id, fn($q) => $q->where('area_id', $request->area_id))
            ->paginate(15)->withQueryString();

        $riders = Rider::where('application_status', 'approved')->where('is_active', true)
            ->with('area')->get();

        $areas = DeliveryArea::orderBy('name')->get();

        return view('sc.delivery-assignment', compact('parcels', 'riders', 'areas'));
    }

    public function assignParcel(Request $request, Parcel $parcel)
    {
        $request->validate(['rider_id' => 'required|exists:riders,id']);
        $rider = Rider::findOrFail($request->rider_id);

        ParcelDelivery::create([
            'parcel_id'   => $parcel->id,
            'rider_id'    => $rider->id,
            'area_id'     => $parcel->area_id,
            'assigned_by' => auth()->id(),
            'status'      => 'assigned',
        ]);

        $parcel->update(['status' => 'assigned']);
        return back()->with('success', "{$parcel->tracking_number} assigned to {$rider->full_name}.");
    }

    // ── Delivery Monitoring ───────────────────────────────────
    public function deliveryMonitoring(Request $request)
    {
        $deliveries = ParcelDelivery::with(['parcel', 'rider', 'area'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()->paginate(20)->withQueryString();

        $monitorStats = [
            'dispatched'       => ParcelDelivery::where('status', 'assigned')->count(),
            'out_for_delivery' => ParcelDelivery::where('status', 'out_for_delivery')->count(),
            'delivered'        => ParcelDelivery::where('status', 'delivered')->whereDate('delivered_at', today())->count(),
            'failed'           => ParcelDelivery::where('status', 'failed')->count(),
            'returned'         => ParcelDelivery::where('status', 'returned')->count(),
        ];

        return view('sc.delivery-monitoring', compact('deliveries', 'monitorStats'));
    }

    public function updateDeliveryStatus(Request $request, ParcelDelivery $delivery)
    {
        $request->validate(['status' => 'required|in:assigned,out_for_delivery,delivered,failed,returned']);

        $delivery->update([
            'status'       => $request->status,
            'delivered_at' => $request->status === 'delivered' ? now() : $delivery->delivered_at,
        ]);

        match($request->status) {
            'delivered'        => $delivery->parcel->update(['status' => 'delivered']),
            'out_for_delivery' => $delivery->parcel->update(['status' => 'in_transit']),
            'failed'           => $delivery->parcel->update(['status' => 'failed']),
            default            => null,
        };

        return back()->with('success', 'Delivery status updated.');
    }

    // ── Reports ───────────────────────────────────────────────
    public function reports(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : now()->subDays(5);
        $to   = $request->to   ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        // ── Daily Summary ─────────────────────────────────────
        $days   = collect();
        $cursor = $from->copy();
        while ($cursor <= $to) {
            $date = $cursor->toDateString();
            $days->push((object)[
                'date'      => $date,
                'received'  => Parcel::whereDate('created_at', $date)->count(),
                'sorted'    => Parcel::where('status','sorted')->whereDate('updated_at',$date)->count(),
                'delivered' => ParcelDelivery::where('status','delivered')->whereDate('delivered_at',$date)->count(),
                'failed'    => ParcelDelivery::where('status','failed')->whereDate('updated_at',$date)->count(),
                'returned'  => ParcelDelivery::where('status','returned')->whereDate('updated_at',$date)->count(),
            ]);
            $cursor->addDay();
        }

        // ── Rider Performance ─────────────────────────────────
        $riderPerformance = Rider::with(['user', 'area'])
            ->where('application_status', 'approved')
            ->get()
            ->map(function ($rider) use ($from, $to) {
                $base  = $rider->parcelDeliveries()
                    ->whereBetween('created_at', [$from, $to]);

                $total     = (clone $base)->count();
                $delivered = (clone $base)->where('status', 'delivered')->count();
                $failed    = (clone $base)->where('status', 'failed')->count();
                $returned  = (clone $base)->where('status', 'returned')->count();
                $rate      = $total > 0 ? round(($delivered / $total) * 100) : 0;

                return (object)[
                    'id'        => $rider->id,
                    'name'      => $rider->full_name,
                    'area'      => $rider->area->name ?? '—',
                    'total'     => $total,
                    'delivered' => $delivered,
                    'failed'    => $failed,
                    'returned'  => $returned,
                    'rate'      => $rate,
                    'active'    => $rider->is_active,
                ];
            })
            ->sortByDesc('delivered');

        // ── Area Summary ──────────────────────────────────────
        $areaSummary = DeliveryArea::get()->map(function ($area) use ($from, $to) {
            $base  = ParcelDelivery::where('area_id', $area->id)
                ->whereBetween('created_at', [$from, $to]);

            $total     = (clone $base)->count();
            $delivered = (clone $base)->where('status', 'delivered')->count();
            $failed    = (clone $base)->where('status', 'failed')->count();
            $returned  = (clone $base)->where('status', 'returned')->count();
            $pending   = Parcel::where('area_id', $area->id)
                ->whereIn('status', ['sorted', 'assigned'])->count();
            $rate      = $total > 0 ? round(($delivered / $total) * 100) : 0;

            return (object)[
                'name'      => $area->name,
                'code'      => $area->code,
                'total'     => $total,
                'delivered' => $delivered,
                'failed'    => $failed,
                'returned'  => $returned,
                'pending'   => $pending,
                'rate'      => $rate,
            ];
        })->sortByDesc('delivered');

        return view('sc.reports', compact('from', 'to', 'riderPerformance', 'areaSummary')
            + ['dailyData' => $days]);
    }

    // ── Chat ──────────────────────────────────────────────────
    public function chat(Request $request)
    {
        $contacts = User::where('id', '!=', auth()->id())
            ->whereIn('role', ['courier', 'courier_pending', 'seller', 'admin'])
            ->orderBy('name')->get();

        $activeContact = null;
        $messages      = collect();

        if ($request->contact_id) {
            $activeContact = User::findOrFail($request->contact_id);
            $messages = Message::where(function($q) use($activeContact) {
                    $q->where('sender_id', auth()->id())->where('receiver_id', $activeContact->id);
                })->orWhere(function($q) use($activeContact) {
                    $q->where('sender_id', $activeContact->id)->where('receiver_id', auth()->id());
                })->oldest()->get();

            Message::where('sender_id', $activeContact->id)
                ->where('receiver_id', auth()->id())
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return view('sc.chat', compact('contacts', 'activeContact', 'messages'));
    }

    public function sendMessage(Request $request)
    {
        $request->validate(['receiver_id' => 'required|exists:users,id', 'body' => 'required|string|max:2000']);
        Message::create(['sender_id' => auth()->id(), 'receiver_id' => $request->receiver_id, 'body' => $request->body]);
        return back()->with('success', 'Message sent.');
    }

    public function pollMessages(Request $request)
    {
        $request->validate(['contact_id' => 'required|exists:users,id', 'after_id' => 'nullable|integer']);

        $messages = Message::where(function($q) use($request) {
                $q->where('sender_id', auth()->id())->where('receiver_id', $request->contact_id);
            })->orWhere(function($q) use($request) {
                $q->where('sender_id', $request->contact_id)->where('receiver_id', auth()->id());
            })
            ->when($request->after_id, fn($q) => $q->where('id', '>', $request->after_id))
            ->oldest()->get();

        return response()->json($messages);
    }

    // ── Account ───────────────────────────────────────────────
    public function account()
    {
        $accounts = User::where('role', 'sorting_center')->latest()->get();
        return view('sc.account', compact('accounts'));
    }

    public function updateAccount(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update($request->only('name', 'email'));

        if ($request->filled('current_password')) {
            $request->validate([
                'current_password' => 'required|current_password',
                'password'         => 'required|confirmed|min:8',
            ]);
            $user->update(['password' => Hash::make($request->password)]);
        }

        return back()->with('success', 'Account updated successfully.');
    }

    // ── Logout ────────────────────────────────────────────────
    public function logout(Request $request)
    {
        \Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('sc.login');
    }
}
