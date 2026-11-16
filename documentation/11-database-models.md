# 11 · The database

## How data is stored

ALVY keeps its data in a database of **tables**, like spreadsheets: one table for orders, one for
products, one for riders, and so on. Each table has a **model** in `app/Models/`, a PHP class used
to read and save its rows and to ask questions like "can this order still be cancelled?".

The tables are created by the **migrations** in `database/migrations/`. Each migration file makes
one change, and running `php artisan migrate` applies the ones not yet applied. On your computer the
database is a single SQLite file, `database/database.sqlite`.

## What is stored, by area

**People and accounts**
- [User](../app/Models/User.php): every account, with its role.
- [UserAddress](../app/Models/UserAddress.php): a buyer's saved addresses.
- [SellerApplication](../app/Models/SellerApplication.php), [SortingCenterApplication](../app/Models/SortingCenterApplication.php): store and logistics applications.
- [Rider](../app/Models/Rider.php): a company's rider, with vehicle, plate, licence and barangays.
- [Courier](../app/Models/Courier.php): the truck record kept in step with each truck rider ([page 6](06-riders-and-hub-to-hub-trips.md#which-rider-does-what)); also old freelance couriers.
- [AccountActivation](../app/Models/AccountActivation.php): set-password links for new riders.
- [UserNotification](../app/Models/UserNotification.php), [Announcement](../app/Models/Announcement.php), [ActivityLog](../app/Models/ActivityLog.php), [PushDevice](../app/Models/PushDevice.php).

**Products and the shop**
- [Product](../app/Models/Product.php), [ProductVariation](../app/Models/ProductVariation.php), [ProductImage](../app/Models/ProductImage.php), [Category](../app/Models/Category.php), [StoreCategory](../app/Models/StoreCategory.php).
- [FlashSale](../app/Models/FlashSale.php), [Promotion](../app/Models/Promotion.php), [Voucher](../app/Models/Voucher.php), [VoucherClaim](../app/Models/VoucherClaim.php), [VoucherRedemption](../app/Models/VoucherRedemption.php).
- [Cart](../app/Models/Cart.php), [CartItem](../app/Models/CartItem.php), [Wishlist](../app/Models/Wishlist.php), [WishlistItem](../app/Models/WishlistItem.php), [StoreFollow](../app/Models/StoreFollow.php), [Review](../app/Models/Review.php).
- [HomeBanner](../app/Models/HomeBanner.php), [PlatformPolicy](../app/Models/PlatformPolicy.php).

**Orders and money**
- [Order](../app/Models/Order.php): one store's part of a checkout. [OrderItem](../app/Models/OrderItem.php): one line, with the commission saved at purchase.
- [OrderStatusEvent](../app/Models/OrderStatusEvent.php): every step of an order, for its timeline.
- [Payment](../app/Models/Payment.php), [ReturnRequest](../app/Models/ReturnRequest.php), [SellerPayout](../app/Models/SellerPayout.php).
- [Message](../app/Models/Message.php): chat. [ContactStrike](../app/Models/ContactStrike.php), [SellerViolation](../app/Models/SellerViolation.php): warnings.

**Deliveries**
- [Parcel](../app/Models/Parcel.php): the delivery side of an order. It knows its step, which hub holds it, its waybill number and its barangay.
- [ParcelPickup](../app/Models/ParcelPickup.php) (rider collecting from the seller), [ParcelDelivery](../app/Models/ParcelDelivery.php) (one delivery try).
- [LinehaulTrip](../app/Models/LinehaulTrip.php) and the `LinehaulTrip…` models: truck trips, their parcels, history and messages.
- [ReturnShipment](../app/Models/ReturnShipment.php): a buyer's return on its way back.
- [ShipmentEvent](../app/Models/ShipmentEvent.php): the tracking history. Rows are never edited or deleted.
- [ShipmentException](../app/Models/ShipmentException.php): a lost or damaged parcel waiting for a decision.
- [LogisticsHub](../app/Models/LogisticsHub.php), [DeliveryArea](../app/Models/DeliveryArea.php) (a covered barangay), [ShippingRate](../app/Models/ShippingRate.php), [ShippingZone](../app/Models/ShippingZone.php).
- [RiderRemittance](../app/Models/RiderRemittance.php) (cash handed in), [RiderWalletEntry](../app/Models/RiderWalletEntry.php) (pay and cash-outs).
- Older pieces still kept for old records: [ParcelTransfer](../app/Models/ParcelTransfer.php), [RiderJobOffer](../app/Models/RiderJobOffer.php), [Delivery](../app/Models/Delivery.php).

## The steps of an order

An order always moves forward through these steps. The list is written at the top of
[Order](../app/Models/Order.php).

```
Awaiting Payment ──► Pending ──► Processing ──► Shipped ──► Delivered ──► Completed
 (online orders      (seller     (seller        (rider has   (buyer has     (done; money
  being paid)         sees it)    accepted)      it)          it)            is final)

Pending or Processing ──► Cancelled
Delivered ──► Return Requested ──► Refunded
                     └──► back to Delivered (seller rejected) ──► In Dispute (buyer escalated)
```

## The steps of a parcel

A parcel has more steps, because it physically moves. Each parcel may only move to certain next
steps; this rule list is `Parcel::TRANSITIONS` in [Parcel](../app/Models/Parcel.php). Anything else
is refused.

| Step (in the code) | What it means |
|---|---|
| `pending_pickup` | The seller asked for a pickup |
| `pickup_approved` | The logistics company accepted the request |
| `in_transit_to_hub` | A rider collected it and is bringing it in |
| `picked_up` | **At the sorting center** (the name is older than its meaning) |
| `sorting`, `sorted` | Being sorted / sorted to a barangay or to another sorting center |
| `linehaul` | On a truck to another hub |
| `assigned` | A delivery rider has it |
| `in_transit` | Out for delivery |
| `delivered` | Delivered (final) |
| `failed` | A delivery try failed; it can be tried again |
| `return_to_sender`, `returned` | Going back to the seller / back with the seller |
| `lost`, `damaged`, `cancelled` | Something went wrong, or the order was cancelled |

The labels shown on screen ("At Sorting Center", "Out for Delivery") are in
`Parcel::STATUS_LABELS`.
