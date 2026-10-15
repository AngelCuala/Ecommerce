<?php

namespace App\Console\Commands;

use App\Services\RiderDispatch;
use Illuminate\Console\Command;

/** Rider job offers older than 60 seconds expire and the job goes to the next rider. */
class ExpireRiderOffers extends Command
{
    protected $signature = 'riders:expire-offers';

    protected $description = 'Expire rider job offers after 60 seconds and offer the jobs to the next rider';

    public function handle(RiderDispatch $dispatch): int
    {
        $n = $dispatch->expireStale();
        $this->info($n . ' offer(s) expired.');

        return self::SUCCESS;
    }
}
