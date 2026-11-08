<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Get the app": the Android app (ALVY.apk) to download from the website. The app uses this same site
 * (/api/v1) as its backend, so one server serves both. The file sits in storage/app/app-releases (not in git).
 */
class AppDownloadController extends Controller
{
    /** What was last published with `php artisan app:publish-apk`, kept beside the APK. */
    public static function versionPath(): string
    {
        return dirname(self::apkPath()) . DIRECTORY_SEPARATOR . 'version.json';
    }

    /**
     * The app asks this when it opens: a phone with an older build must update before using the app.
     * Nothing published yet: build 0, so nobody is blocked.
     */
    public function version(): \Illuminate\Http\JsonResponse
    {
        $v = is_file(self::versionPath()) ? json_decode((string) file_get_contents(self::versionPath()), true) : null;

        return response()->json([
            'build'         => (int) ($v['build'] ?? 0),
            'version'       => (string) ($v['version'] ?? ''),
            'size'          => (int) ($v['size'] ?? 0),
            'download_path' => '/app/download',
        ])->header('Cache-Control', 'no-store');
    }

    public static function apkPath(): string
    {
        return config('app.apk_path') ?: storage_path('app/app-releases/ALVY.apk');
    }

    public function show()
    {
        $path = self::apkPath();
        $apk = is_file($path) ? ['size' => filesize($path), 'updated_at' => \Carbon\Carbon::createFromTimestamp(filemtime($path))] : null;

        return view('app-download', compact('apk'));
    }

    public function download(): BinaryFileResponse
    {
        $path = self::apkPath();
        abort_unless(is_file($path), 404, 'The app is not available for download yet.');

        return response()->download($path, 'ALVY Shop.apk', [
            'Content-Type'  => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
