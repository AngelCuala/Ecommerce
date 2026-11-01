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
        // Buyers, including suspended ones (suspending a buyer sets role = 'suspended'). The OR is grouped,
        // so the search and status filters below apply to every buyer.
        $query = User::where(fn ($q) => $q->whereIn('role', ['buyer', 'suspended'])->orWhereNull('role'))
            ->withCount('orders')
            ->latest();

        if ($request->filled('search')) {
            $term = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('username', 'like', $term);
            });
        }

        $status = $request->status;
        if ($status === 'suspended') {
            $query->where('role', 'suspended');
        } elseif (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            // Same rule as User::status: a suspended account shows as Suspended, whatever its approval.
            $query->where(fn ($q) => $q->where('role', '!=', 'suspended')->orWhereNull('role'))
                ->when($status === 'pending',
                    fn ($q) => $q->where(fn ($p) => $p->where('approval_status', 'pending')->orWhereNull('approval_status')),
                    fn ($q) => $q->where('approval_status', $status));
        }

        $customers = $query->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(int $id)
    {
        $customer = User::with(['orders.items.product'])->findOrFail($id);
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
