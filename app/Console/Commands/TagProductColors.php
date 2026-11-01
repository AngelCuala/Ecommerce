<?php

namespace App\Console\Commands;

use App\Services\ProductColorTagger;
use Illuminate\Console\Command;

/**
 * Reads the colors of product pictures not read yet (see ProductColorTagger). Runs every 10 minutes
 * for new products; run it by hand with a bigger --limit to read the whole catalogue the first time.
 */
class TagProductColors extends Command
{
    protected $signature = 'products:tag-colors {--limit=20 : Most pictures to read this run} {--pause=4.5 : Seconds between pictures (free tier: 15 a minute)}';

    protected $description = 'Read the main colors of product pictures with the AI, so color searches work';

    public function handle(ProductColorTagger $tagger): int
    {
        if (! config('services.assistant.gemini_key')) {
            $this->warn('No GEMINI_API_KEY: product colors were not read.');
            return self::SUCCESS;
        }

        $products = ProductColorTagger::pending()->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();
        $done = 0;
        foreach ($products as $i => $product) {
            if ($i > 0) {
                usleep((int) ((float) $this->option('pause') * 1_000_000));
            }
            // Busy models rest a minute: wait it out twice (a short overload), then leave the rest for a later run.
            for ($wait = 0; ! $tagger->tag($product); $wait++) {
                if ($wait === 2 || (float) $this->option('pause') === 0.0) {
                    $this->warn('The AI is at its limit for now; the rest will be read on a later run.');
                    break 2;
                }
                sleep(61);
            }
            $done++;
            $this->line(sprintf('%-45s %s', mb_strimwidth($product->title, 0, 44, '…'), implode(', ', $product->colors ?: ['(no clear color)'])));
        }

        $left = ProductColorTagger::pending()->count();
        $this->info("Read {$done} picture(s). {$left} still to read.");
        return self::SUCCESS;
    }
}
