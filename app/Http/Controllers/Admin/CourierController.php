<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function index()
    {
        $couriers = Courier::with('user')->latest()->get();
        return view('admin.couriers.index', compact('couriers'));
    }

    public function show(Courier $courier)
    {
        $courier->load(['user', 'deliveries.order.user']);
        return view('admin.couriers.show', compact('courier'));
    }

    public function approve(Courier $courier)
    {
        $courier->update(['status' => 'approved']);
        $courier->user->update(['role' => 'courier']);

        return back()->with('success', $courier->fullName() . ' approved as courier.');
    }

    public function reject(Request $request, Courier $courier)
    {
        $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $courier->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);
        $courier->user->update(['role' => 'buyer']);

        return back()->with('success', $courier->fullName() . '\'s application rejected.');
    }

    public function suspend(Courier $courier)
    {
        $courier->update(['status' => 'suspended']);
        $courier->user->update(['role' => 'suspended']);
        return back()->with('success', $courier->fullName() . ' suspended.');
    }
}
