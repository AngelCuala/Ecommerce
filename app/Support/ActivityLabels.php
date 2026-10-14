<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Readable names for the actions recorded in the activity log, by route name. */
class ActivityLabels
{
    private const LABELS = [
        // Sign in / out / sign up
        'login.attempt'             => 'Signed in',
        'logout'                    => 'Signed out',
        'register.store'            => 'Created an account',
        'otp.verify'                => 'Entered email code',
        'otp.resend'                => 'Requested a new email code',
        'seller.register.store'     => 'Signed up as a seller',
        'sc.register.store'         => 'Signed up as a sorting center',
        'sc.login.store'            => 'Signed in (sorting center)',
        'sc.logout'                 => 'Signed out (sorting center)',
        'logistics.login.store'     => 'Signed in (logistics)',
        'admin.logistics.logout'    => 'Signed out (logistics)',
        'courier.register.store'    => 'Applied as a courier',
        'seller.apply.store'        => 'Applied to become a seller',

        // Buyer
        'cart.store'                => 'Added to cart',
        'cart.update'               => 'Changed cart quantity',
        'cart.destroy'              => 'Removed from cart',
        'checkout.store'            => 'Placed an order',
        'orders.cancel'             => 'Cancelled an order',
        'orders.confirm-delivery'   => 'Confirmed order received',
        'addresses.store'           => 'Added an address',
        'addresses.update'          => 'Edited an address',
        'addresses.destroy'         => 'Deleted an address',
        'addresses.set-default'     => 'Set default address',
        'profile.update'            => 'Updated profile',
        'profile.account.update'    => 'Updated account details',
        'profile.change-password'   => 'Changed password',
        'messages.new'              => 'Started a conversation',
        'messages.direct.store'     => 'Sent a message',
        'chat.send'                 => 'Sent a chat message',

        // Seller
        'seller.books.store'        => 'Added a product',
        'seller.books.update'       => 'Edited a product',
        'seller.books.destroy'      => 'Deleted a product',
        'seller.books.archive'      => 'Archived a product',
        'seller.books.unarchive'    => 'Restored a product',
        'seller.books.stock'        => 'Updated stock',
        'seller.orders.update'      => 'Updated an order',
        'seller.orders.handover'    => 'Requested courier pickup',
        'seller.orders.handed-over' => 'Handed order to courier',
        'seller.status.resubmit'    => 'Resubmitted seller documents',
        'seller.account.update'     => 'Updated shop account',
        'seller.account.password'   => 'Changed password',
        'seller.vouchers.store'     => 'Created a store voucher',
        'seller.vouchers.update'    => 'Edited a store voucher',
        'seller.vouchers.destroy'   => 'Deleted a store voucher',
        'seller.vouchers.toggle'    => 'Turned a store voucher on/off',

        // Courier
        'courier.deliveries.accept'     => 'Accepted a delivery',
        'courier.deliveries.pickup'     => 'Picked up a parcel',
        'courier.deliveries.in-transit' => 'Delivery in transit',
        'courier.deliveries.complete'   => 'Completed a delivery',
        'courier.account.update'        => 'Updated courier account',
        'courier.account.password'      => 'Changed password',

        // Sorting center
        'sc.courier-applications.approve' => 'Approved a courier',
        'sc.courier-applications.reject'  => 'Rejected a courier',
        'sc.riders.store'           => 'Added a rider',
        'sc.riders.approve'         => 'Approved a rider',
        'sc.riders.reject'          => 'Rejected a rider',
        'sc.riders.toggle'          => 'Turned a rider on/off',
        'sc.areas.store'            => 'Added a coverage area',
        'sc.areas.destroy'          => 'Removed a coverage area',
        'sc.pickup-requests.approve'=> 'Approved a pickup',
        'sc.pickup-requests.reject' => 'Rejected a pickup',
        'sc.parcels.scan'           => 'Scanned a parcel',
        'sc.parcels.advance'        => 'Moved a parcel forward',
        'sc.parcels.sort'           => 'Sorted a parcel',
        'sc.parcels.assign'         => 'Assigned a parcel',
        'sc.deliveries.update-status' => 'Updated a delivery',
        'sc.deliveries.reschedule'  => 'Rescheduled a delivery',
        'sc.deliveries.return'      => 'Returned a parcel',
        'sc.transfers.initiate'     => 'Started a transfer',
        'sc.transfers.accept'       => 'Accepted a transfer',
        'sc.transfers.reject'       => 'Rejected a transfer',
        'sc.transfers.cancel'       => 'Cancelled a transfer',
        'sc.chat.send'              => 'Sent a message',
        'sc.account.update'         => 'Updated sorting center account',

        // Admin
        'admin.products.store'      => 'Added a product (admin)',
        'admin.products.update'     => 'Edited a product (admin)',
        'admin.products.destroy'    => 'Deleted a product (admin)',
        'admin.product-approvals.approve' => 'Approved a product',
        'admin.product-approvals.reject'  => 'Rejected a product',
        'admin.vouchers.store'      => 'Created an ALVY voucher',
        'admin.vouchers.update'     => 'Edited an ALVY voucher',
        'admin.vouchers.destroy'    => 'Deleted an ALVY voucher',
        'admin.vouchers.toggle'     => 'Turned a voucher on/off',
        'admin.orders.update'       => 'Updated an order (admin)',
        'admin.couriers.approve'    => 'Approved a courier (admin)',
        'admin.couriers.reject'     => 'Rejected a courier (admin)',
        'admin.couriers.suspend'    => 'Suspended a courier',
        'admin.customers.approve'   => 'Approved a buyer',
        'admin.customers.reject'    => 'Rejected a buyer',
        'admin.customers.suspend'   => 'Suspended a buyer',
        'admin.customers.restore'   => 'Restored a buyer',
        'admin.categories.store'    => 'Added a category',
        'admin.categories.update'   => 'Edited a category',
        'admin.categories.destroy'  => 'Deleted a category',
        'admin.reviews.destroy'     => 'Deleted a review',
        'admin.users.assign-municipality' => 'Assigned a municipality',
        'admin.settings.announcements.store'   => 'Posted an announcement',
        'admin.settings.announcements.destroy' => 'Deleted an announcement',
        'admin.settings.announcements.toggle'  => 'Turned an announcement on/off',
        'admin.notifications.read-all'         => 'Marked notifications read',
        'admin.compliance.store'    => 'Filed a compliance report',
    ];

    public static function for(?string $routeName, string $method, string $path): string
    {
        if ($routeName && isset(self::LABELS[$routeName])) {
            return self::LABELS[$routeName];
        }

        // Fallback: "seller.orders.update" → "Seller · Orders update"
        if ($routeName) {
            $parts = explode('.', $routeName);
            $area  = count($parts) > 1 ? Str::headline(array_shift($parts)) . ' · ' : '';

            return $area . Str::ucfirst(str_replace(['-', '_'], ' ', implode(' ', $parts)));
        }

        return $method . ' /' . ltrim($path, '/');
    }
}
