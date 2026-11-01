<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Support\SellerApplicationRules;
use Illuminate\Http\Request;

/**
 * Where a seller who isn't approved yet lands after logging in: shows the
 * application status (and rejection reason), and lets a rejected seller
 * upload corrected documents to send the application back for review.
 */
class StatusController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->isApproved()) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.status', [
            'user'        => $user,
            'application' => $user->sellerApplication,
        ]);
    }

    public function resubmit(Request $request)
    {
        $user = $request->user();
        $application = $user->sellerApplication;

        if (! $application || ! $application->isRejected()) {
            return redirect()->route('seller.status');
        }

        $document = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        $request->validate([
            'government_id'   => ['bail', ...$document, new \App\Rules\GovernmentId],
            'dti_certificate' => $document,
            'sec_certificate' => $document,
            'bir_2303'        => $document,
        ], SellerApplicationRules::messages(), SellerApplicationRules::attributes());

        $paths = [];
        foreach ([
            'government_id'   => ['government_id_path',   'seller-ids'],
            'dti_certificate' => ['dti_certificate_path', 'seller-dti'],
            'sec_certificate' => ['sec_certificate_path', 'seller-sec'],
            'bir_2303'        => ['bir_2303_path',        'seller-bir'],
        ] as $field => [$column, $dir]) {
            if ($request->hasFile($field)) {
                $paths[$column] = $request->file($field)->store($dir, 'public');
            }
        }

        if (! $paths) {
            return back()->withErrors(['documents' => 'Please upload at least one corrected document.']);
        }

        $application->update($paths + \App\Rules\GovernmentId::columnsFor('government_id')
            + ['status' => 'pending', 'rejection_reason' => null]);
        $user->forceFill(['approval_status' => 'pending'])->save();

        return redirect()->route('seller.status')
            ->with('success', 'Thanks! Your updated documents were sent. Your application is back in the review queue.');
    }
}
