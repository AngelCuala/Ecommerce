<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Console\Command;

/** A31: a return request the seller hasn't answered within 3 days is approved automatically. */
class AutoApproveReturns extends Command
{
    protected $signature = 'returns:auto-approve';

    protected $description = 'Approve return requests the seller has not answered within 3 days';

    public function handle(): int
    {
        $due = ReturnRequest::where('status', 'requested')->where('created_at', '<=', now()->subDays(ReturnRequest::RESPOND_DAYS))->with('order')->get();
        foreach ($due as $return) {
            $return->update(['status' => 'approved', 'seller_note' => 'Approved automatically: the seller did not answer within ' . ReturnRequest::RESPOND_DAYS . ' days.', 'resolved_at' => now()]);
            if ($return->order && $return->order->status === Order::RETURN_REQUESTED) {
                $return->order->update(['status' => Order::REFUNDED]);
            }
        }
        $this->info($due->count() . ' return request(s) approved.');

        return self::SUCCESS;
    }
}
