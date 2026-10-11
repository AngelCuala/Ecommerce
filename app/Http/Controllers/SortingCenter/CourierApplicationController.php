<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Services\CourierReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Courier applications sent to this sorting center. Couriers choose the sorting center
 * they want to deliver for when they apply; only that sorting center sees and reviews them.
 */
class CourierApplicationController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : 'pending';
        $mine   = Courier::where('sorting_center_id', auth()->id());

        $couriers = (clone $mine)->with('user')
            ->where('status', $status)
            ->latest('submitted_at')->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = (clone $mine)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('sc.courier-applications.index', compact('couriers', 'counts', 'status'));
    }

    public function show(Courier $courier)
    {
        $this->authorizeHub($courier);
        $courier->load(['user', 'reviewedBy']);

        return view('sc.courier-applications.show', compact('courier'));
    }

    /** Stream one of the courier's uploaded documents (never a public link). */
    public function document(Courier $courier, string $document)
    {
        $this->authorizeHub($courier);

        $path = match ($document) {
            'license' => $courier->id_license_path,
            'or-cr'   => $courier->or_cr_path,
            default   => null,
        };
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Courier $courier, CourierReview $review)
    {
        $this->authorizeHub($courier);
        if (! $courier->isPending()) {
            return back()->with('error', 'Only pending applications can be approved.');
        }

        $review->approve($courier, auth()->user());

        return redirect()->route('sc.courier-applications.index')
            ->with('success', $courier->fullName() . ' is now an approved courier for your sorting center.');
    }

    public function reject(Request $request, Courier $courier, CourierReview $review)
    {
        $this->authorizeHub($courier);
        if (! $courier->isPending()) {
            return back()->with('error', 'Only pending applications can be rejected.');
        }

        $data = $request->validate(['rejection_reason' => 'required|string|max:500'], [
            'rejection_reason.required' => 'Please tell the applicant why the application was rejected.',
        ]);

        $review->reject($courier, auth()->user(), $data['rejection_reason']);

        return redirect()->route('sc.courier-applications.index')
            ->with('success', $courier->fullName() . '\'s application was rejected.');
    }

    /** Another sorting center's applicant looks like a missing page. */
    private function authorizeHub(Courier $courier): void
    {
        abort_unless((int) $courier->sorting_center_id === (int) auth()->id(), 404);
    }
}
