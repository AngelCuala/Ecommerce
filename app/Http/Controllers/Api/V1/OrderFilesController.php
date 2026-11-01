<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ShipmentTrackingController;

/** An order's private files for the app: the web's own checks (the buyer, a seller in the order, or an admin). */
class OrderFilesController extends Controller
{
    use CallsWeb;

    /** The GCash receipt uploaded at checkout. */
    public function paymentProof(int $id): mixed
    {
        return $this->web(ShipmentTrackingController::class, 'paymentProof', ['order' => $id]);
    }

    /** The rider's proof-of-delivery photo. */
    public function proofOfDelivery(int $id): mixed
    {
        return $this->web(ShipmentTrackingController::class, 'proofOfDelivery', ['order' => $id]);
    }
}
