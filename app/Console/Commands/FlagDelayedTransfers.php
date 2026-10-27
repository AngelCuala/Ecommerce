<?php

namespace App\Console\Commands;

use App\Services\ShipmentWorkflow;
use Illuminate\Console\Command;

/** Dispatched hub-to-hub transfers not confirmed received in time are flagged for investigation (once each). */
class FlagDelayedTransfers extends Command
{
    protected $signature = 'shipments:flag-delayed';
    protected $description = 'Flag dispatched transfers past their expected receipt time (LOGISTICS_TRANSFER_HOURS)';

    public function handle(ShipmentWorkflow $flow): int
    {
        $n = $flow->flagDelayedTransfers();
        $this->info("{$n} transfer(s) flagged as delayed.");
        return self::SUCCESS;
    }
}
