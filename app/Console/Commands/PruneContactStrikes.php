<?php

namespace App\Console\Commands;

use App\Models\ContactStrike;
use Illuminate\Console\Command;

/** Data retention: contact strikes are deleted one year after they were given (Privacy Policy, section 6). */
class PruneContactStrikes extends Command
{
    protected $signature = 'contact-strikes:prune';

    protected $description = 'Delete contact strikes older than ' . ContactStrike::RETENTION_DAYS . ' days';

    public function handle(): int
    {
        $n = ContactStrike::where('created_at', '<', now()->subDays(ContactStrike::RETENTION_DAYS))->delete();
        $this->info("Deleted {$n} old contact " . ($n === 1 ? 'strike' : 'strikes') . '.');

        return self::SUCCESS;
    }
}
