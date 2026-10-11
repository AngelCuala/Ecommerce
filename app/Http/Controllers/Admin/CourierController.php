<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\UserNotification;
use App\Services\CourierReview;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $couriers = Courier::with('user')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->get();

        $counts = [
            'all'       => Courier::count(),
            'pending'   => Courier::where('status', 'pending')->count(),
            'approved'  => Courier::where('status', 'approved')->count(),
            'rejected'  => Courier::where('status', 'rejected')->count(),
            'suspended' => Courier::where('status', 'suspended')->count(),
        ];

        return view('admin.couriers.index', compact('couriers', 'counts', 'status'));
    }

    public function show(Courier $courier)
    {
        $courier->load(['user', 'deliveries.order.user']);
        return view('admin.couriers.show', compact('courier'));
    }

    public function approve(Courier $courier, CourierReview $review)
    {
        $review->approve($courier, auth()->user());

        return back()->with('success', $courier->fullName() . ' approved as courier.');
    }

    public function reject(Request $request, Courier $courier, CourierReview $review)
    {
        $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $review->reject($courier, auth()->user(), $request->rejection_reason);

        return back()->with('success', $courier->fullName() . '\'s application rejected.');
    }

    public function suspend(Courier $courier)
    {
        $courier->update(['status' => 'suspended']);
        $courier->user->update(['role' => 'suspended']);

        $this->notify($courier, 'Courier account suspended',
            'Your courier account has been suspended. Please contact the Logistics team for details.',
            'warning', null);

        return back()->with('success', $courier->fullName() . ' suspended.');
    }

    /** Create an in-app notification for the courier's user. */
    private function notify(Courier $courier, string $title, string $body, string $type, ?string $link): void
    {
        UserNotification::create([
            'user_id' => $courier->user_id,
            'title'   => $title,
            'body'    => $body,
            'type'    => $type,
            'link'    => $link,
        ]);
    }
}
