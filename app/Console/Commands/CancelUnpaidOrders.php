<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

/**
 * Online orders not paid within config('payments.unpaid_minutes') are cancelled (stock and
 * vouchers come back). The gateway is checked once more first. Scheduled every minute.
 */
class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid';

    protected $description = 'Cancel online orders that were not paid in time';

    public function handle(PaymentService $payments): int
    {
        $this->info('Cancelled ' . $payments->cancelOverdue() . ' unpaid order(s).');

        return self::SUCCESS;
    }
}
