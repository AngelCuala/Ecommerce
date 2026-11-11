<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ContactStrike;
use App\Services\ContactStrikes;
use Illuminate\Http\Request;

/** The seller's own contact strikes: when, what kind, the chat it was in, and an appeal ("this was a mistake"). */
class StrikesController extends Controller
{
    public function index(ContactStrikes $strikes)
    {
        $seller = auth()->user();
        $list = ContactStrike::where('user_id', $seller->id)->with('message')->latest()->paginate(20);

        return view('seller.strikes', ['list' => $list, 'count' => $strikes->count($seller)]);
    }

    public function appeal(Request $request, ContactStrike $strike, ContactStrikes $strikes)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:500'],
            ['reason.required' => 'Tell ALVY why this was a mistake (10 to 500 characters).', 'reason.min' => 'Tell ALVY why this was a mistake (10 to 500 characters).']);
        abort_unless((int) $strike->user_id === (int) auth()->id(), 404);
        try {
            $strikes->appeal($strike, auth()->user(), trim($data['reason']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Appeal sent. ALVY will review it and tell you the result.');
    }
}
