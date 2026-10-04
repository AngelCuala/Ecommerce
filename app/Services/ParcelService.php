<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;

/**
 * Business rules for the Sorting Center parcel pipeline:
 *
 *   Seller ready for pickup -> SC confirms pickup -> parcel received/scanned at SC
 *   -> delivery area determined -> sorted -> rider for that area assigned
 *   -> out for delivery -> delivered (buyer confirms) | failed -> reschedule / return
 *
 * Rider-side actions (mobile app) are not handled here; the sorting center records
 * the delivery outcome from its own monitoring screen.
 */
class ParcelService
{
    // ── Creating pickup requests ──────────────────────────────

    /**
     * Create (or refresh) the pickup request for a seller's part of an order.
     * One parcel per order + seller. A rejected request is re-opened.
     */
    public function createForSeller(Order $order, User $seller, $pickupAt = null, ?string $notes = null): Parcel
    {
        $parcel = Parcel::firstOrNew(['order_id' => $order->id, 'seller_id' => $seller->id]);

        // Already moving through the pipeline — just keep schedule/notes current.
        // (A cancelled request can be re-opened once routing sends the order to the sorting center.)
        if ($parcel->exists && ! in_array($parcel->status, ['pending_pickup', 'pickup_rejected', 'cancelled'], true)) {
            return $parcel;
        }

        $sc = $this->routeSortingCenter($seller, $order);

        $parcel->fill([
            'tracking_number'           => $parcel->tracking_number ?: Parcel::generateTracking(),
            'pickup_address'            => $this->sellerAddress($seller),
            'dropoff_address'           => $order->fullAddress() ?: ($order->shipping_address ?? '—'),
            'destination_municipality'  => $order->city,
            'destination_province'      => $order->province,
            'receiver_name'             => $order->full_name,
            'receiver_phone'            => $order->phone,
            'weight_kg'                 => $this->sellerWeight($order, $seller),
            'notes'                     => $notes,
            'pickup_scheduled_at'       => $pickupAt,
            'status'                    => 'pending_pickup',
            'failure_reason'            => null,
            'current_sorting_center_id' => $sc?->id,
            'area_id'                   => null,
        ]);
        $parcel->save();

        if ($sc) {
            $this->notify($sc->id, 'New pickup request',
                "Parcel {$parcel->tracking_number} from {$seller->name} is ready for pickup.",
                'info', route('sc.pickup-requests', ['tab' => 'pending']));
        }

        return $parcel;
    }

    /**
     * The sorting center that should handle the pickup: the one covering the
     * seller's municipality, otherwise the one covering the buyer's municipality.
     */
    public function routeSortingCenter(User $seller, Order $order): ?User
    {
        $centers = User::where('role', 'sorting_center')
            ->whereNotNull('assigned_municipality')->get();

        // Origin = the seller's approved seller application (official pickup address).
        // The profile address (users.municipality) is intentionally not used for routing.
        $origin = app(DeliveryRoutingService::class)->sellerOrigin($seller);

        return $this->matchCenter($centers, $origin?->municipality_code, $origin?->municipality)
            ?? $this->matchCenter($centers, $order->municipality_code, $order->city);
    }

    /** Match by PSGC code first; fall back to the normalized name for records without codes. */
    private function matchCenter(Collection $centers, ?string $code, ?string $municipality): ?User
    {
        if ($code) {
            $byCode = $centers->first(fn ($sc) => $sc->assigned_municipality_code === $code);
            if ($byCode) return $byCode;
        }

        if (! $municipality) return null;
        $key = $this->normalizeMunicipality($municipality);

        return $centers->first(fn ($sc) => $this->normalizeMunicipality($sc->assigned_municipality) === $key);
    }

    /** "City of Calamba", "Calamba City" and "calamba" all compare equal. */
    public function normalizeMunicipality(?string $name): string
    {
        $n = strtolower(trim((string) $name));
        $n = preg_replace('/\b(city of|municipality of|city|municipality)\b/', '', $n);
        $n = preg_replace('/[^a-z0-9ñ ]/u', ' ', $n);
        return trim(preg_replace('/\s+/', ' ', $n));
    }

    private function sellerAddress(User $seller): string
    {
        $app = $seller->sellerApplication;
        if ($app && $app->address) return $app->address;

        $parts = array_filter([$seller->house_number, $seller->street, $seller->barangay, $seller->municipality, $seller->province]);
        return $parts ? implode(', ', $parts) : ($seller->address ?? '—');
    }

    private function sellerWeight(Order $order, User $seller): ?float
    {
        $order->loadMissing('items.book');
        $kg = $order->items
            ->filter(fn ($i) => (int) optional($i->book)->seller_id === (int) $seller->id)
            ->sum(fn ($i) => (float) (optional($i->book)->weight_kg ?? 0) * $i->quantity);

        return $kg > 0 ? round($kg, 2) : null;
    }

    // ── Determining the delivery area ─────────────────────────

    /** Does this sorting center's municipality cover the parcel's destination? */
    public function coversDestination(Parcel $parcel, User $sc): bool
    {
        if (! $parcel->destination_municipality || ! $sc->assigned_municipality) {
            return true; // unknown destination — let the SC decide
        }

        return $this->normalizeMunicipality($parcel->destination_municipality)
            === $this->normalizeMunicipality($sc->assigned_municipality);
    }

    /**
     * Read the delivery address and pick the matching barangay (delivery area)
     * from the sorting center's coverage list. Returns null if no match.
     */
    public function suggestArea(Parcel $parcel, Collection $areas): ?DeliveryArea
    {
        $address = ' ' . strtolower(preg_replace('/[^A-Za-z0-9ñÑ ]/u', ' ', (string) $parcel->dropoff_address)) . ' ';

        return $areas
            ->sortByDesc(fn ($a) => strlen($a->name)) // longest name first ("San Isidro Norte" before "San Isidro")
            ->first(function ($area) use ($address) {
                $name = strtolower(preg_replace('/[^A-Za-z0-9ñÑ ]/u', ' ', $area->name));
                $name = trim(preg_replace('/^(barangay|brgy)\s+/', '', trim($name)));
                return $name !== '' && str_contains($address, ' ' . $name . ' ');
            });
    }

    // ── Keeping the order + people in sync ────────────────────

    /** Order status rank — the order only ever moves forward. */
    private const ORDER_RANK = ['Pending' => 0, 'Processing' => 1, 'Shipped' => 2, 'Delivered' => 3];

    /**
     * Mirror the parcel's progress onto the marketplace order the buyer and
     * seller see. "Delivered" is left to the buyer's "Order Received" button.
     */
    public function syncOrder(Parcel $parcel): void
    {
        $order = $parcel->order;
        if (! $order || in_array($order->status, ['Cancelled', 'Delivered'], true)) {
            return;
        }

        $target = match ($parcel->status) {
            'pickup_approved'                                   => 'Processing',
            'picked_up', 'sorted', 'assigned', 'in_transit',
            'delivered', 'failed'                               => 'Shipped',
            default                                             => null,
        };

        if ($target && (self::ORDER_RANK[$target] ?? 0) > (self::ORDER_RANK[$order->status] ?? 0)) {
            $order->update(['status' => $target]);
        }

        // Every parcel of the order came back -> the order can't be fulfilled.
        if ($parcel->status === 'returned'
            && $order->parcels()->where('status', '!=', 'returned')->doesntExist()) {
            $order->update([
                'status'              => 'Cancelled',
                'cancellation_reason' => 'Parcel returned to seller: ' . ($parcel->failure_reason ?: 'delivery failed'),
            ]);
        }
    }

    public function notifyBuyer(Parcel $parcel, string $title, string $body, string $type = 'info'): void
    {
        if ($parcel->order?->user_id) {
            $this->notify($parcel->order->user_id, $title, $body, $type, route('profile.orders'));
        }
    }

    public function notifySeller(Parcel $parcel, string $title, string $body, string $type = 'info'): void
    {
        $link = $parcel->order_id ? route('seller.orders.show', $parcel->order_id) : null;
        $this->notify($parcel->seller_id, $title, $body, $type, $link);
    }

    public function notify(int $userId, string $title, string $body, string $type = 'info', ?string $link = null): void
    {
        UserNotification::create([
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
            'type'    => $type,
            'link'    => $link,
        ]);
    }

    public function orderRef(Parcel $parcel): string
    {
        return $parcel->order_id
            ? 'Order #' . str_pad($parcel->order_id, 6, '0', STR_PAD_LEFT)
            : 'Parcel ' . $parcel->tracking_number;
    }
}
