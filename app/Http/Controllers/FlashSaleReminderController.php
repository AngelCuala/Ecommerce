<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\FlashSaleReminder;

/** "Remind me": a buyer gets a notification shortly before a flash sale starts. */
class FlashSaleReminderController extends Controller
{
    public function toggle(FlashSale $flashSale)
    {
        if (! $flashSale->starts_at->isFuture()) {
            return back()->with('error', 'This flash sale has already started.');
        }

        $existing = FlashSaleReminder::where('flash_sale_id', $flashSale->id)->where('user_id', auth()->id())->first();
        if ($existing) {
            $existing->delete();
            return back()->with('success', 'Reminder removed.');
        }

        FlashSaleReminder::create(['flash_sale_id' => $flashSale->id, 'user_id' => auth()->id()]);

        return back()->with('success', 'We\'ll remind you ' . FlashSale::REMIND_MINUTES_BEFORE . ' minutes before the flash sale starts.');
    }
}
