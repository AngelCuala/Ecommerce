<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Get the app": the Android app (ALVY.apk) to download from the website. The app uses this same site
 * (/api/v1) as its backend, so one server serves both. The file sits in storage/app/app-releases (not in git).
 */
class AppDownloadController extends Controller
{
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

        return response()->download($path, 'ALVY.apk', [
            'Content-Type'  => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
