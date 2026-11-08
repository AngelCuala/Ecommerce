<?php

namespace App\Console\Commands;

use App\Http\Controllers\AppDownloadController;
use Illuminate\Console\Command;

/**
 * Publishes a new app build: the website's download becomes this APK, and phones on an older build
 * must update the next time they open the app. The APK goes first, so no phone is told to update to a
 * file that is not there yet. The APK it replaces is kept beside it.
 */
class PublishApk extends Command
{
    protected $signature = 'app:publish-apk {--dry-run : Show what would be published, change nothing}';

    protected $description = 'Publish the latest ALVY app build: website download and in-app update';

    /** Where Flutter leaves the release build, beside this project. */
    private function outputs(): string
    {
        return dirname(base_path()) . '/Ecommerce.app/build/app/outputs';
    }

    public function handle(): int
    {
        $source = $this->outputs() . '/flutter-apk/app-release.apk';
        $metaPath = $this->outputs() . '/apk/release/output-metadata.json';
        if (! is_file($source) || ! is_file($metaPath)) {
            $this->error('No release build found. Run: flutter build apk --release');

            return self::FAILURE;
        }
        // The version of the build itself, not pubspec.yaml: a raised number without a new build must not be published.
        $element = json_decode((string) file_get_contents($metaPath), true)['elements'][0] ?? [];
        $build = (int) ($element['versionCode'] ?? 0);
        $name = trim((string) ($element['versionName'] ?? ''));
        if ($build < 1 || $name === '') {
            $this->error('Could not read the version of the build.');

            return self::FAILURE;
        }
        $versionPath = AppDownloadController::versionPath();
        $current = is_file($versionPath) ? (array) json_decode((string) file_get_contents($versionPath), true) : [];
        if ($build <= (int) ($current['build'] ?? 0)) {
            $this->error('Build ' . $build . ' is not newer than the published build ' . (int) $current['build'] . '.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("Would publish ALVY {$name} (build {$build}), " . round(filesize($source) / 1048576, 1) . ' MB. Nothing was changed.');

            return self::SUCCESS;
        }

        $target = AppDownloadController::apkPath();
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        if (is_file($target)) {
            copy($target, dirname($target) . DIRECTORY_SEPARATOR . 'ALVY.before_v' . $name . '_' . date('Ymd_His') . '.apk');
        }
        // Copied beside the target and renamed, so a download never gets a half-written file.
        $temp = $target . '.uploading';
        if (! copy($source, $temp) || ! rename($temp, $target)) {
            $this->error('Could not copy the APK.');

            return self::FAILURE;
        }
        file_put_contents($versionPath, json_encode([
            'build' => $build, 'version' => $name, 'size' => filesize($target), 'published_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        $this->info("Published ALVY {$name} (build {$build}).");

        return self::SUCCESS;
    }
}
