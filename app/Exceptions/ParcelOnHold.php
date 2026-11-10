<?php

namespace App\Exceptions;

use App\Models\Parcel;
use App\Models\ShipmentException;
use RuntimeException;

/**
 * A flagged parcel (open shipment exception) is on hold: it can't be moved, assigned, transferred or
 * delivered until an administrator resolves the flag. Nothing is saved. Extends RuntimeException, so
 * actions that already show RuntimeException messages show this one too; elsewhere the user is sent back.
 */
class ParcelOnHold extends RuntimeException
{
    public function __construct(public readonly Parcel $parcel, public readonly ShipmentException $flag)
    {
        parent::__construct("{$parcel->tracking_number} is on hold: it was flagged ({$flag->typeLabel()}: {$flag->details}). "
            . 'It can\'t be moved or processed until an administrator resolves the flag. Nothing was changed.');
    }

    public function render($request)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $this->getMessage()], 423)
            : back()->with('error', $this->getMessage());
    }
}
