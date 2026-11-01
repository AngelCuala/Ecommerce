<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\SellerApplication;
use App\Models\User;

/**
 * Decides which ONE delivery route an order uses.
 *
 *   SAME municipality       Seller → covering sorting center's riders → Buyer      (parcels table)
 *                           or, when no company with riders covers it, Seller → Courier → Buyer (deliveries)
 *   DIFFERENT municipality  Seller → Sorting Center → Destination courier → Buyer  (parcels table)
 *
 * Source of truth for the comparison (PSGC municipality codes, never free text):
 *   - Buyer / destination: orders.municipality_code (saved from the checkout dropdown)
 *   - Seller / origin:     the seller's APPROVED seller_applications.municipality_code.
 *     The user profile address (users.municipality) is deliberately NOT used for routing:
 *     the seller application is the verified business/pickup address.
 *
 * Existing routes always win: an order that already has an active courier delivery or an
 * active sorting-center parcel keeps it, so existing orders are never silently re-routed.
 */
class DeliveryRoutingService
{
    public const COURIER        = 'courier';
    public const SORTING_CENTER = 'sorting_center';

    /** The route the order is already using, or null if it has none yet. */
    public function activeRoute(Order $order): ?string
    {
        if (Delivery::where('order_id', $order->id)->whereIn('status', Delivery::OPEN_STATUSES)->exists()) {
            return self::COURIER;
        }
        if (Parcel::where('order_id', $order->id)->whereNotIn('status', Parcel::CLOSED_STATUSES)->exists()) {
            return self::SORTING_CENTER;
        }
        return null;
    }

    /** Both routes active at once (only possible for records created before this rule). */
    public function hasConflictingRoutes(Order $order): bool
    {
        return Delivery::where('order_id', $order->id)->whereIn('status', Delivery::OPEN_STATUSES)->exists()
            && Parcel::where('order_id', $order->id)->whereNotIn('status', Parcel::CLOSED_STATUSES)->exists();
    }

    /** The seller's approved application — the official origin address. */
    public function sellerOrigin(User $seller): ?SellerApplication
    {
        return SellerApplication::where('user_id', $seller->id)
            ->where('status', 'approved')
            ->latest('id')
            ->first();
    }

    /**
     * Decide the route for a new pickup from the stored PSGC codes.
     *
     * @return array{route: ?string, reason: string, origin: ?string, destination: ?string}
     *         route is null when the codes needed to decide are missing (nothing is guessed).
     */
    public function decide(Order $order, User $seller): array
    {
        $origin      = $this->sellerOrigin($seller);
        $originCode  = $origin?->municipality_code;
        $destCode    = $order->municipality_code;
        $originLabel = $origin ? trim($origin->municipality . ', ' . $origin->province, ', ') : null;
        $destLabel   = trim(($order->city ?? '') . ', ' . ($order->province ?? ''), ', ') ?: null;

        $missing = [];
        if (! $origin) {
            $missing[] = 'the seller has no approved seller application (pickup address)';
        } elseif (! $originCode) {
            $missing[] = 'the seller application address has no PSGC municipality code';
        }
        if (! $destCode) {
            $missing[] = "the order's delivery address has no PSGC municipality code";
        }

        if ($missing) {
            return [
                'route'       => null,
                'reason'      => 'Delivery route could not be determined: ' . implode('; ', $missing) . '.',
                'origin'      => $originLabel,
                'destination' => $destLabel,
            ];
        }

        $same = $originCode === $destCode;

        // Same municipality: the logistics company covering it picks up and delivers with its
        // own riders (Ready for Pickup → Assigned → Picked Up → At Hub → Out for Delivery).
        // Only where no company with active riders covers it does a direct courier take it.
        if ($same) {
            $sc = app(ParcelService::class)->routeSortingCenter($seller, $order);
            if ($sc && \App\Models\Rider::where('sorting_center_id', $sc->id)->where('application_status', 'approved')->where('is_active', true)->exists()) {
                return [
                    'route'       => self::SORTING_CENTER,
                    'reason'      => "Same municipality ({$destLabel}): picked up and delivered by {$sc->name}'s riders.",
                    'origin'      => $originLabel,
                    'destination' => $destLabel,
                ];
            }
        }

        return [
            'route'       => $same ? self::COURIER : self::SORTING_CENTER,
            'reason'      => $same
                ? "Same municipality ({$destLabel}): direct courier delivery."
                : "Different municipalities ({$originLabel} → {$destLabel}): routed through the sorting center.",
            'origin'      => $originLabel,
            'destination' => $destLabel,
        ];
    }

    public function label(?string $route): string
    {
        return match ($route) {
            self::COURIER        => 'direct courier',
            self::SORTING_CENTER => 'sorting center',
            default              => 'none',
        };
    }
}
