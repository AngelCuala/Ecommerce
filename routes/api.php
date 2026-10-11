<?php

use App\Support\SortingCenters;
use Illuminate\Support\Facades\Route;

// Sorting centers (hubs) a new courier can choose on the sign-up form.
Route::get('/logistics-providers', fn () => response()->json(SortingCenters::forCouriers()));
