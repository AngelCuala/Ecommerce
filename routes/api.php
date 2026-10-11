<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;

// Sorting centers (hubs) a new courier can choose on the sign-up form.
// The hub's name/address come from its approved application, else from the account itself.
Route::get('/logistics-providers', function () {
    $providers = User::query()
        ->where('users.role', 'sorting_center')
        ->where('users.approval_status', 'approved')
        ->leftJoin('sorting_center_applications as sca', function ($join) {
            $join->on('sca.user_id', '=', 'users.id')->where('sca.status', 'approved');
        })
        ->orderBy('users.name')
        ->get([
            'users.id', 'users.name', 'users.street', 'users.barangay', 'users.municipality', 'users.province',
            'sca.business_name as hub_name', 'sca.street as hub_street', 'sca.barangay as hub_barangay',
            'sca.municipality as hub_municipality', 'sca.province as hub_province',
        ])
        ->unique('id')
        ->map(function ($p) {
            $address = $p->hub_municipality
                ? [$p->hub_street, $p->hub_barangay, $p->hub_municipality, $p->hub_province]
                : [$p->street, $p->barangay, $p->municipality, $p->province];

            return [
                'id'          => $p->id,
                'name'        => $p->hub_name ?: $p->name,
                'hub_address' => implode(', ', array_filter($address)) ?: null,
            ];
        })
        ->values();

    return response()->json($providers);
});
