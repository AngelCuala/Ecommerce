<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Small WebP copies of product pictures for the product cards (home, shop, categories, stores), so
 * pages load faster on phones. The original stays untouched and is still used on the product page;
 * a card shows the original until its copy exists. Runs every 10 minutes for new pictures.
 */
class MakeProductThumbs extends Command
{
    protected $signature = 'products:thumbs {--size=400 : Longest side in pixels} {--force : Make them again}';

    protected $description = 'Make small WebP copies of product pictures for product cards';

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->warn('PHP GD without WebP: no copies made.');
            return self::SUCCESS;
        }
        $size = max(100, (int) $this->option('size'));
        $made = $skipped = $failed = 0;
        $savedBytes = 0;

        foreach (Product::query()->whereNotNull('image')->pluck('image')->unique() as $image) {
            $src = storage_path('app/public/' . $image);
            $dst = storage_path('app/public/' . Product::thumbPath($image));
            if (! is_file($src) || (is_file($dst) && ! $this->option('force') && filemtime($dst) >= filemtime($src))) {
                $skipped++;
                continue;
            }
            $img = @imagecreatefromstring((string) file_get_contents($src));
            if (! $img) {
                $failed++;
                continue;
            }
            [$w, $h] = [imagesx($img), imagesy($img)];
            $scale = min(1, $size / max($w, $h));
            $small = imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)), IMG_BICUBIC);
            imagepalettetotruecolor($small);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            @mkdir(dirname($dst), 0775, true);
            imagewebp($small, $dst, 80);
            imagedestroy($img);
            imagedestroy($small);
            $savedBytes += max(0, filesize($src) - filesize($dst));
            $made++;
        }

        $this->info("Made {$made} card picture(s), {$skipped} already up to date, {$failed} could not be read. Saved "
            . round($savedBytes / 1024 / 1024, 1) . ' MB.');
        return self::SUCCESS;
    }
}
