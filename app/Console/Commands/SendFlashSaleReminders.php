<?php

namespace App\Console\Commands;

use App\Models\FlashSale;
use App\Models\UserNotification;
use Illuminate\Console\Command;

/** Tells buyers who pressed "Remind me" that a flash sale starts soon. Scheduled every minute. */
class SendFlashSaleReminders extends Command
{
    protected $signature = 'flash-sales:remind';

    protected $description = 'Send reminders for flash sales that start soon';

    public function handle(): int
    {
        $sent = 0;
        FlashSale::with('product', 'reminders')
            ->whereNull('reminders_sent_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addMinutes(FlashSale::REMIND_MINUTES_BEFORE))
            ->get()
            ->each(function (FlashSale $sale) use (&$sent) {
                foreach ($sale->reminders as $reminder) {
                    UserNotification::create([
                        'user_id' => $reminder->user_id,
                        'title'   => 'Flash sale starts soon',
                        'body'    => '"' . ($sale->product->title ?? 'An item') . '" drops to ₱' . number_format($sale->flash_price, 2)
                                   . ' at ' . $sale->starts_at->format('g:i A') . ' — only ' . $sale->quantity . ' at this price.',
                        'type'    => 'promo',
                        'link'    => $sale->product && ! $sale->product->trashed() ? route('products.show', $sale->product->slug, false) : null,
                    ]);
                    $sent++;
                }
                $sale->update(['reminders_sent_at' => now()]);
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
