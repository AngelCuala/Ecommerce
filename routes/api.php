<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;

Route::get('/logistics-providers', function () {
    $providers = User::where('role', 'logistics')
        ->where('approval_status', 'approved')
        ->select('id', 'name', 'business_name')
        ->selectRaw("CONCAT_WS(', ', street, barangay, municipality, province) as hub_address")
        ->get()
        ->map(function($provider) {
            return [
                'id' => $provider->id,
                'name' => $provider->business_name ?: $provider->name,
                'hub_address' => $provider->hub_address
            ];
        });
    
    return response()->json($providers);
});
