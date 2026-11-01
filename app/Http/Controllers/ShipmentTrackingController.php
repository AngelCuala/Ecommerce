<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelDelivery;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/** Proof-of-delivery photo: only the order's buyer, a seller with items in it, or an admin may see it. */
class ShipmentTrackingController extends Controller
{
    public function proofOfDelivery(int $order)
    {
        $order = Order::findOrFail($order);
        $this->authorizeOrder($order);

        $path = ParcelDelivery::whereHas('parcel', fn ($q) => $q->where('order_id', $order->id))
            ->where('status', 'delivered')->whereNotNull('pod_path')->latest('id')->value('pod_path');
        abort_unless($path, 404);

        return \App\Support\ProtectedFile::response($path);   // kept on the private disk
    }

    /** The GCash receipt the buyer uploaded at checkout (private file): the buyer, the order's seller or an admin. */
    public function paymentProof(int $order)
    {
        $order = Order::findOrFail($order);
        $this->authorizeOrder($order);
        abort_unless($order->payment_proof_path && Storage::disk('local')->exists($order->payment_proof_path), 404);

        return response()->file(Storage::disk('local')->path($order->payment_proof_path), ['Cache-Control' => 'private, max-age=600']);
    }

    /** Only the order's buyer, the seller whose items are in it, or an admin. */
    private function authorizeOrder(Order $order): void
    {
        $user = auth()->user();
        $sellerOwns = OrderItem::where('order_id', $order->id)
            ->whereIn('product_id', Product::withTrashed()->where('seller_id', $user->id)->select('id'))->exists();
        abort_unless((int) $order->user_id === (int) $user->id || $sellerOwns || $user->isAdmin(), 403);
    }
}
