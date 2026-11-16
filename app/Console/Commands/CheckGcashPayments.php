<?php

namespace App\Console\Commands;

use App\Services\GcashChecks;
use Illuminate\Console\Command;

/** Reads new GCash receipts for the admin and sends the 24-hour reminders (scheduled every minute). */
class CheckGcashPayments extends Command
{
    protected $signature = 'gcash:check {--limit=10 : Receipts to read in one run}';

    protected $description = 'Read GCash receipts waiting for the admin and remind about old ones';

    public function handle(GcashChecks $checks): int
    {
        $done = $checks->run((int) $this->option('limit'));
        $this->info("Read {$done['read']} receipt(s), sent {$done['reminded']} reminder(s).");

        return self::SUCCESS;
    }
}
