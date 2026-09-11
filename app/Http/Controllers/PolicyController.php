<?php

namespace App\Http\Controllers;

use App\Models\PlatformPolicy;

class PolicyController extends Controller
{
    public function show(string $key)
    {
        $policy   = PlatformPolicy::where('key', $key)->first();
        $defaults = collect(PlatformPolicy::defaultPolicies())->keyBy('key');
        $title    = $policy?->title ?? ($defaults[$key]['title'] ?? ucwords(str_replace('_', ' ', $key)));

        return view('policies.show', compact('policy', 'key', 'title'));
    }
}
