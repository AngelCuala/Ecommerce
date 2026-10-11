<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SellerApprovedMail;
use App\Mail\SellerRejectedMail;
use App\Models\SellerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SellerApplicationController extends Controller
{
    public function index()
    {
        // Sign-ups that haven't verified their email yet aren't ready for review.
        $applications = SellerApplication::with('user')
            ->where('status', '!=', 'unverified')
            ->latest('updated_at')
            ->get();

        return view('admin.seller-applications.index', compact('applications'));
    }

    public function show(SellerApplication $sellerApplication)
    {
        return view('admin.seller-applications.show', [
            'application' => $sellerApplication->load('user'),
        ]);
    }

    public function approve(SellerApplication $sellerApplication)
    {
        if (! $sellerApplication->isPending()) {
            return back()->with('error', 'Only applications pending approval can be approved.');
        }

        $sellerApplication->update(['status' => 'approved', 'rejection_reason' => null]);

        // Upgrade the user's role to seller and open the seller panel
        $sellerApplication->user->forceFill(['role' => 'seller', 'approval_status' => 'approved'])->save();

        \App\Models\ActivityLog::record(
            'seller_application_approved',
            'Seller Application Approved',
            'Approved seller application for ' . $sellerApplication->user->name . ' (shop: ' . ($sellerApplication->shop_name ?? 'n/a') . ').',
            $sellerApplication
        );

        // Send approval email
        try {
            Mail::to($sellerApplication->user->email)
                ->send(new SellerApprovedMail($sellerApplication));
        } catch (\Throwable $e) {
            // Log but don't break the approval flow if mail fails
            \Log::warning('Seller approval email failed: ' . $e->getMessage());
        }

        return back()->with('success',
            $sellerApplication->user->name . ' has been approved as a seller. A notification email has been sent.');
    }

    public function reject(Request $request, SellerApplication $sellerApplication)
    {
        if (! $sellerApplication->isPending()) {
            return back()->with('error', 'Only applications pending approval can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ], [
            'rejection_reason.required' => 'Please give a reason — it is emailed to the applicant.',
        ]);

        $sellerApplication->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        // A seller sign-up account is rejected with its application. (A buyer who
        // applied keeps their buyer account.)
        if ($sellerApplication->user->isSeller()) {
            $sellerApplication->user->forceFill(['approval_status' => 'rejected'])->save();
        }

        \App\Models\ActivityLog::record(
            'seller_application_rejected',
            'Seller Application Rejected',
            'Rejected seller application for ' . $sellerApplication->user->name . '.',
            $sellerApplication
        );

        // Send rejection email
        try {
            Mail::to($sellerApplication->user->email)
                ->send(new SellerRejectedMail($sellerApplication));
        } catch (\Throwable $e) {
            \Log::warning('Seller rejection email failed: ' . $e->getMessage());
        }

        return back()->with('success',
            $sellerApplication->user->name . '\'s application has been rejected. A notification email has been sent.');
    }
}
