<?php

namespace App\Exceptions;

use App\Models\Parcel;
use RuntimeException;

/**
 * A shipment status change the lifecycle rules don't allow (Parcel::TRANSITIONS). Nothing is saved.
 * Extends RuntimeException, so actions that already show RuntimeException messages show this one too;
 * anywhere else the user is sent back with the explanation instead of an error page.
 */
class InvalidShipmentTransition extends RuntimeException
{
    public function __construct(public readonly Parcel $parcel, public readonly string $from, public readonly string $to)
    {
        $label = fn (string $s) => Parcel::STATUS_LABELS[$s] ?? ucwords(str_replace('_', ' ', $s));
        parent::__construct("{$parcel->tracking_number} can't go from \"{$label($from)}\" to \"{$label($to)}\". Nothing was changed.");
    }

    public function render($request)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $this->getMessage()], 422)
            : back()->with('error', $this->getMessage());
    }
}
