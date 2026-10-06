<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SortingCenterApprovedMail;
use App\Mail\SortingCenterRejectedMail;
use App\Models\ActivityLog;
use App\Models\SortingCenterApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Admin review of Logistics / Sorting Center registrations (ERP: manage account
 * registrations, approve/disapprove, notify the applicant by email).
 */
class SortingCenterApplicationController extends Controller
{
    public function index()
    {
        $applications = SortingCenterApplication::with('user')->latest()->get();

        return view('admin.sc-applications.index', compact('applications'));
    }

    public function show(SortingCenterApplication $application)
    {
        $application->load(['user', 'reviewedBy']);

        // An operating sorting center already serving this municipality (routing must be unambiguous).
        $conflict = $this->coverageConflict($application);

        return view('admin.sc-applications.show', compact('application', 'conflict'));
    }

    /** Stream an uploaded document from the private disk. */
    public function document(SortingCenterApplication $application, string $type)
    {
        $path = match ($type) {
            'id'     => $application->government_id_path,
            'permit' => $application->business_permit_path,
            default  => abort(404),
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    public function approve(SortingCenterApplication $application)
    {
        if (! $application->isPending()) {
            return back()->with('error', 'This registration has already been reviewed.');
        }
        if ($conflict = $this->coverageConflict($application)) {
            return back()->with('error', "{$conflict->name} already serves {$application->municipality}. "
                . 'Re-assign that center first, or disapprove this registration.');
        }

        DB::transaction(function () use ($application) {
            $application->update([
                'status'           => 'approved',
                'rejection_reason' => null,
                'reviewed_by'      => auth()->id(),
                'reviewed_at'      => now(),
            ]);

            // Unlock the portal and set the coverage to the registered PSA municipality.
            $application->user->update([
                'role'                       => 'sorting_center',
                'approval_status'            => 'approved',
                'assigned_municipality'      => $application->municipality,
                'assigned_municipality_code' => $application->municipality_code,
                'assigned_province'          => $application->province,
                'assigned_province_code'     => $application->province_code,
            ]);
        });

        ActivityLog::record('sc_application_approved', 'Sorting Center Approved',
            "Approved sorting center {$application->business_name} ({$application->municipality}).", $application);

        $mailed = $this->mail($application, new SortingCenterApprovedMail($application));

        return back()->with('success', "{$application->business_name} has been approved and now serves {$application->municipality}."
            . ($mailed ? ' A notification email has been sent.' : ' The notification email could not be sent.'));
    }

    public function reject(Request $request, SortingCenterApplication $application)
    {
        if (! $application->isPending()) {
            return back()->with('error', 'This registration has already been reviewed.');
        }

        $data = $request->validate(['rejection_reason' => 'required|string|max:500'], [
            'rejection_reason.required' => 'Please give the reason for disapproving this registration.',
        ]);

        DB::transaction(function () use ($application, $data) {
            $application->update([
                'status'           => 'rejected',
                'rejection_reason' => $data['rejection_reason'],
                'reviewed_by'      => auth()->id(),
                'reviewed_at'      => now(),
            ]);
            // The login stays locked (role sorting_center_pending).
            $application->user->update(['approval_status' => 'rejected']);
        });

        ActivityLog::record('sc_application_rejected', 'Sorting Center Disapproved',
            "Disapproved sorting center {$application->business_name}.", $application);

        $mailed = $this->mail($application, new SortingCenterRejectedMail($application));

        return back()->with('success', "{$application->business_name}'s registration has been disapproved."
            . ($mailed ? ' A notification email has been sent.' : ' The notification email could not be sent.'));
    }

    private function coverageConflict(SortingCenterApplication $application): ?User
    {
        return User::where('role', 'sorting_center')
            ->where('assigned_municipality_code', $application->municipality_code)
            ->where('id', '!=', $application->user_id)
            ->first();
    }

    /** Send without breaking the review if mail is misconfigured (same as seller approvals). */
    private function mail(SortingCenterApplication $application, $mailable): bool
    {
        try {
            Mail::to($application->user->email)->send($mailable);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Sorting center review email failed: ' . $e->getMessage());
            return false;
        }
    }
}
