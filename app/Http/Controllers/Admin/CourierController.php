<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CourierApprovedMail;
use App\Mail\CourierRejectedMail;
use App\Models\Courier;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    public function approve(Courier $courier)
    {
        $courier->update([
            'status'      => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
        $courier->user->update(['role' => 'courier']);

        $this->notify($courier, 'Courier application approved',
            'Congratulations! Your courier application has been approved. You can now log in and start accepting deliveries.',
            'info', route('courier.dashboard'));

        $this->email(fn () => Mail::to($courier->user->email)->send(new CourierApprovedMail($courier)));

        return back()->with('success', $courier->fullName() . ' approved as courier.');
    }

    public function reject(Request $request, Courier $courier)
    {
        $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $courier->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_at'      => now(),
            'reviewed_by'      => auth()->id(),
        ]);
        // Return the user to a plain buyer so they can re-apply.
        $courier->user->update(['role' => 'buyer']);

        $reason = $request->rejection_reason
            ? ' Reason: ' . $request->rejection_reason
            : '';
        $this->notify($courier, 'Courier application rejected',
            'Your courier application was not approved.' . $reason . ' You may re-apply from your account.',
            'warning', route('courier.register'));

        $this->email(fn () => Mail::to($courier->user->email)->send(new CourierRejectedMail($courier)));

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

    /** Send an email but never let a mail failure break the review action. */
    private function email(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::warning('Courier review email failed: ' . $e->getMessage());
        }
    }
}
