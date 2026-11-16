# 4 · The Seller Centre

The Seller Centre (`/seller`) is where a seller runs their store: products, orders, discounts and
money.

## Opening a store

A person can open a store in two ways: sign up as a seller directly, or, if they already shop on
ALVY, press **Become a Seller** on their account. Either way they fill in an application: shop
name, line of business (what they sell), IDs and permits, the pickup address where riders will
collect parcels, and GCash details.

An admin then approves or rejects it. While waiting, the seller only sees a status page, though
they may already prepare products as drafts. If rejected, they see the reason and can upload
corrected documents.

The approved pickup address matters later: ALVY uses it to decide how far a parcel travels and
which logistics companies can collect it.

## Adding products

In **Inventory** the seller adds and edits products:

- **Category:** only categories matching the seller's line of business. Each category asks for its
  own details (a phone asks for storage size, clothing for material, and so on).
- **Variations:** up to three kinds of options, like Colour and Size. Every combination (Red / M)
  can have its own price, stock, SKU and photo.
- **Photos and a video,** with size limits.
- **No contact details** in the name or description (no phone numbers or links), so deals stay on ALVY.

A **new product waits for an admin's approval** before buyers can see it. Later edits don't need
approval again.

The seller can also duplicate a product, turn many off at once, end a sale early, archive products
and update stock quickly. A product with unfinished orders can never be deleted.

When stock drops to the product's low-stock level, the seller gets one alert, and another when it
sells out.

## Handling an order

When a buyer orders, the seller sees it under **New**. The seller then takes it through these steps,
in this order (none can be skipped):

1. **Accept** the order.
2. **Pack** it.
3. **Print the waybill**, the label stuck on the parcel. It has a barcode and a QR code holding the
   waybill number, the same label the sorting center prints.
4. **Ready for Pickup:** the seller chooses which logistics company should collect the parcel.
   Companies that serve the seller's town are listed first. This sends a pickup request to that
   company ([page 5](05-sorting-center.md#step-1-the-seller-asks-for-a-pickup)).
5. When the rider collects the parcel (with a photo), the order becomes **Shipped**. From here the
   logistics company and its riders take over.

The seller can **cancel** with a reason until the parcel is picked up. The stock and the buyer's
vouchers go back, an online payment is refunded, and the buyer is told why.

**Returns:** if a buyer asks to return an item, the seller approves (the buyer is refunded, possibly
for just some items) or rejects with a reason. If the seller doesn't answer within 3 days, it's
approved automatically.

## Discounts the seller can run

- **Store vouchers:** codes for the seller's own products. The seller pays for the discount.
- **Flash sales:** one product at a special price for a set time and a limited number of units.
  It must start in the future. Buyers who pressed *Remind me* get a notification before it starts.
- **Bundles and promos:** "any 3 of these products, 15% off", or "buy 2, get 1 free". These come off
  automatically at checkout.

## The seller's money

- On every item sold, ALVY keeps a **commission** (10% by default). The rate is saved on the order
  at the moment of purchase, so changing the rate later never changes old orders.
- **Earnings** are *Pending* until the order is completed, then *Available*. Available money can be
  requested as a payout.
- Cancelled, refunded and unpaid orders don't count as sales anywhere, so the dashboard, sales
  reports and commission pages always show the same numbers.
- Reports can be downloaded as CSV, Excel or PDF.

## Warnings for sharing contact details

If a seller types a phone number, email, link or social media name in a chat with a buyer, it's
hidden from the buyer and the seller gets a **strike**:

- 1st strike: a warning.
- 2nd strike: a final warning.
- 3rd strike: an admin reviews the store. Nothing happens to the account automatically, because a
  number could be shared by mistake; the admin decides.

The seller can appeal each strike. Strikes are deleted after a year.

## The rest of the Seller Centre

- **Dashboard:** today's numbers, low stock and notices. The red numbers on the sidebar are unread
  notifications for each section; opening the section clears them.
- **Reviews:** the seller can reply once to each review (and edit the reply), but can't delete it.
- **Store Settings:** the store's name, logo, banner, contact number, pickup address, vacation mode
  and GCash QR.
- **Messages** with buyers, and **My Account** for the seller's name, photo and password.

## Where to find it in the code

All seller controllers are in [app/Http/Controllers/Seller/](../app/Http/Controllers/Seller) and the
pages in [resources/views/seller/](../resources/views/seller).

| What | File |
|---|---|
| Application and status page | [RegistrationController](../app/Http/Controllers/Seller/RegistrationController.php), [SellerApplicationController](../app/Http/Controllers/Seller/SellerApplicationController.php), [StatusController](../app/Http/Controllers/Seller/StatusController.php), [SellerApplication](../app/Models/SellerApplication.php) |
| Blocking unapproved sellers | [EnsureSellerApproved](../app/Http/Middleware/EnsureSellerApproved.php) |
| Products (Inventory) | [BookController](../app/Http/Controllers/Seller/BookController.php), [Product](../app/Models/Product.php), [ProductVariation](../app/Models/ProductVariation.php) |
| Low-stock alerts | [LowStockAlert](../app/Services/LowStockAlert.php) |
| Orders, waybills, returns | [OrderController](../app/Http/Controllers/Seller/OrderController.php) |
| Creating the parcel on Ready for Pickup | [ParcelService](../app/Services/ParcelService.php) `createForSeller` |
| Waybill label | [partials/waybill](../resources/views/partials/waybill), [Barcode](../app/Support/Barcode.php), [QrCode](../app/Support/QrCode.php) |
| Vouchers, flash sales, promotions | [VoucherController](../app/Http/Controllers/Seller/VoucherController.php), [FlashSaleController](../app/Http/Controllers/Seller/FlashSaleController.php), [PromotionController](../app/Http/Controllers/Seller/PromotionController.php) |
| Commission and sales numbers | [SellerCommission](../app/Services/SellerCommission.php), [SellerSales](../app/Services/SellerSales.php) |
| Earnings and payouts | [EarningsController](../app/Http/Controllers/Seller/EarningsController.php) |
| Strikes | [StrikesController](../app/Http/Controllers/Seller/StrikesController.php), [ContactStrikes](../app/Services/ContactStrikes.php) |
| Dashboard and sidebar numbers | [DashboardController](../app/Http/Controllers/Seller/DashboardController.php), [SellerBadges](../app/Support/SellerBadges.php) |
| Reviews, store settings, store categories | [ReviewController](../app/Http/Controllers/Seller/ReviewController.php), [StoreSettingsController](../app/Http/Controllers/Seller/StoreSettingsController.php), [StoreCategoryController](../app/Http/Controllers/Seller/StoreCategoryController.php) |
| Report downloads | [TableExport](../app/Support/TableExport.php) |
