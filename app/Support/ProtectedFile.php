<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploads that must not be public: IDs, permits, licenses, OR/CR, proof-of-delivery, return evidence,
 * parcel exception photos and chat pictures. They are kept on the private disk (storage/app/private),
 * not under /storage, and shown only through a signed link that expires (files.show). A page or API
 * answer only gets such a link after it has checked who is asking, so the link itself is the permission;
 * it can't be guessed and stops working after a few hours if it is copied.
 *
 * Paths in the database stay the same ("seller-ids/abc.png"); only where the file is kept changed.
 */
final class ProtectedFile
{
    public const FOLDERS = [
        'seller-ids/', 'seller-dti/', 'seller-bir/', 'seller-permits/', 'seller-sec/',
        'courier-licenses/', 'courier-vehicle-regs/', 'couriers/', 'courier-documents/',
        'proof-of-delivery/', 'returns/', 'parcel-exceptions/', 'chat/',
    ];

    /** How long a link works. */
    public const HOURS = 6;

    /** The disk new uploads of a protected kind go to. */
    public const DISK = 'local';

    public static function isProtected(?string $path): bool
    {
        if (! $path || str_contains($path, '..')) {
            return false;
        }
        foreach (self::FOLDERS as $folder) {
            if (str_starts_with(ltrim($path, '/'), $folder)) {
                return true;
            }
        }
        return false;
    }

    /** Where to show a stored file: a signed, expiring link for a protected one; the public address otherwise. */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (! self::isProtected($path)) {
            return asset('storage/' . $path);
        }
        // Signed without the host, so it works the same through alvy.trade and on this PC.
        return url(URL::temporarySignedRoute('files.show', now()->addHours(self::HOURS), ['path' => ltrim($path, '/')], absolute: false));
    }

    /** The file on disk: the private disk, or the public one for an upload not moved yet. */
    public static function absolutePath(?string $path): ?string
    {
        if (! $path || str_contains($path, '..')) {
            return null;
        }
        foreach ([self::DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->path($path);
            }
        }
        return null;
    }

    public static function exists(?string $path): bool
    {
        return self::absolutePath($path) !== null;
    }

    /** The file as a response that no browser or Cloudflare keeps. */
    public static function response(?string $path, array $headers = []): Response
    {
        $file = self::absolutePath($path);
        abort_unless($file, 404);

        $response = response()->file($file, $headers + ['X-Content-Type-Options' => 'nosniff']);
        $response->setPrivate();   // file responses are "public" by default
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
