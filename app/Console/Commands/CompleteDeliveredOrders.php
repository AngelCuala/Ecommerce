<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Delivered orders the buyer didn't confirm complete by themselves Order::RETURN_DAYS days
 * after delivery (which also closes the return window). Scheduled hourly in routes/console.php.
 */
class CompleteDeliveredOrders extends Command
{
    protected $signature = 'orders:complete-delivered';

    protected $description = 'Complete delivered orders whose return window has passed';

    public function handle(): int
    {
        $done = 0;
        Order::where('status', Order::DELIVERED)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', now()->subDays(Order::RETURN_DAYS))
            ->chunkById(100, function ($orders) use (&$done) {
                foreach ($orders as $order) {
                    $order->update(['status' => Order::COMPLETED, 'payment_status' => 'Paid']);
                    $done++;
                }
            });

        $this->info("Completed {$done} order(s).");

        return self::SUCCESS;
    }
}
