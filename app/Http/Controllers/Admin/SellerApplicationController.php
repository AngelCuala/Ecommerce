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
        $applications = SellerApplication::with('user')
            ->latest()
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
        $sellerApplication->update(['status' => 'approved']);

        // Upgrade the user's role to seller
        $sellerApplication->user->update(['role' => 'seller']);

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
        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $sellerApplication->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

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
