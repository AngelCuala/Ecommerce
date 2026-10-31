<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Shared rules for the "Scan QR" pop-up on the Shipment Board: what a scanned or typed code
 * may look like, and the JSON answers the pop-up shows. The controller decides which parcels may be opened.
 */
class WaybillScan
{
    public const INVALID   = 'Invalid waybill QR code. Please scan again.';
    public const NOT_FOUND = 'No parcel found with this waybill number.';

    /** Upper-case, without spaces around it (a camera or keyboard wedge may add them). */
    public static function normalize(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    /** Waybill numbers (PH…) and tracking numbers (ALVY-…): letters, digits and dashes. Stray text is refused. */
    public static function looksValid(string $code): bool
    {
        return (bool) preg_match('/^[A-Z0-9-]{6,50}$/', $code);
    }

    public static function found(string $url, string $message): JsonResponse
    {
        return response()->json(['ok' => true, 'url' => $url, 'message' => $message]);
    }

    public static function invalid(): JsonResponse
    {
        return response()->json(['ok' => false, 'reason' => 'invalid', 'message' => self::INVALID], 422);
    }

    public static function notFound(): JsonResponse
    {
        return response()->json(['ok' => false, 'reason' => 'not_found', 'message' => self::NOT_FOUND], 404);
    }
}
