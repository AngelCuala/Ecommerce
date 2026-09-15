<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'buyer')
            ->orWhereNull('role')
            ->withCount('orders')
            ->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name',     'like', '%'.$request->search.'%')
                  ->orWhere('email',    'like', '%'.$request->search.'%')
                  ->orWhere('username', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'suspended') {
                // suspended is stored as role, not approval_status
                $query->where('role', 'suspended');
            } else {
                $query->where('approval_status', $status);
            }
        }

        $customers = $query->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(int $id)
    {
        $customer = User::with(['orders.items.book'])->findOrFail($id);
        return view('admin.customers.show', compact('customer'));
    }

    public function approve(int $id)
    {
        $customer = User::findOrFail($id);
        $customer->update(['approval_status' => 'approved']);

        return back()->with('success', "{$customer->full_name}'s account has been approved.");
    }

    public function reject(Request $request, int $id)
    {
        $request->validate([
            'rejection_reason'       => 'required|string',
            'rejection_reason_other' => 'nullable|string|max:500',
        ]);

        $reason = $request->rejection_reason === 'other'
            ? ($request->rejection_reason_other ?: 'Other')
            : $request->rejection_reason;

        $customer = User::findOrFail($id);
        $customer->update([
            'approval_status'  => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return back()->with('success', "{$customer->full_name}'s account has been rejected.");
    }

    public function suspend(int $id)
    {
        $customer = User::findOrFail($id);
        $customer->update(['role' => 'suspended']);

        return back()->with('success', "{$customer->full_name}'s account has been suspended.");
    }

    public function restore(int $id)
    {
        $customer = User::findOrFail($id);
        $customer->update(['role' => 'buyer']);

        return back()->with('success', "{$customer->full_name}'s account has been restored.");
    }

    public function validId(int $id)
    {
        $customer = User::findOrFail($id);

        abort_unless($customer->valid_id_path, 404, 'No ID uploaded.');

        $path = $customer->valid_id_path;

        // Try local disk first (where registrations are stored)
        if (Storage::disk('local')->exists($path)) {
            return response()->file(Storage::disk('local')->path($path));
        }

        // Fallback: public disk
        if (Storage::disk('public')->exists($path)) {
            return response()->file(Storage::disk('public')->path($path));
        }

        $fullPath = public_path($path);
        abort_unless(file_exists($fullPath), 404, 'ID file not found.');

        return response()->file($fullPath);
    }
}
