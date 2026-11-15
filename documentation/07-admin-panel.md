# 7 · The admin panel

Admins are ALVY's own staff. They work at `/admin`. Their job is to decide who may join, check
payments, keep sellers following the rules, and watch over the whole marketplace.

## Deciding who may join

- **Seller applications:** the admin looks at the shop details and documents and approves or
  rejects the store, with a reason. The seller gets an email either way.
- **Sorting center applications:** the same for logistics companies. The admin also sets which
  town a sorting center serves.
- **Courier applications:** usually handled by the sorting center the person applied to, but an
  admin can decide too.
- **New products:** every new listing waits here until an admin approves it. Only then do buyers
  see it.

## Checking GCash payments

When a buyer pays ALVY's GCash and uploads the receipt, the admin checks whether the money really
arrived. One receipt covers the whole checkout, even if it has orders from several stores.

- **Approve:** the money arrived. The orders go to their sellers to prepare.
- **Reject:** no money or the wrong amount. The orders are cancelled, and stock and vouchers go back.
  If some money did arrive, it's listed as a refund to send back.

To save time, an AI reads each receipt picture first and shows hints (is it really a GCash receipt?
what amount and reference number does it show?). The admin still makes the decision. If a payment is
left unchecked for 24 hours, the admins get a reminder and the buyer is told it's still being checked.

## Keeping sellers honest (Compliance)

The Compliance page brings together:

- **Violations:** warnings or actions the admin has given a seller. The seller sees them on their dashboard.
- **Flagged products:** products in a category that doesn't match what the seller registered to sell.
- **Contact strikes:** sellers who shared contact details with buyers in chat. Those with 3 or more
  strikes in 90 days are shown first. The admin also decides on sellers' appeals, with a note the seller sees.

The admin can also remove **reviews** that break the rules.

## Running the marketplace

- **People:** lists of users, buyers, sellers, sorting centers and riders. The admin can suspend,
  restore or deactivate accounts.
- **Orders:** every order. The admin settles **disputes** (returns the buyer escalated after the
  seller rejected them).
- **Vouchers:** ALVY's own vouchers, which ALVY pays for. The admin can also turn off any store's voucher.
- **Banners:** the big slides on the home page, for events and sales.
- **Categories:** the product categories.
- **Settings:** announcements to users, and the policy pages (terms, privacy, returns).
- **Analytics and Reports:** sales and activity across the whole platform.
- **Activity log:** everything anyone changed ([page 2](02-accounts-and-sign-in.md#the-activity-log)).
- **Logistics:** a view of every company's parcels, riders, pickups and deliveries, and a chat with
  the companies.

## What admins can't do

Admins can see every step of every order and parcel, but they **can't move one to another step by
hand**. Steps always happen through the people doing the work: the seller, the logistics company and
the rider. This keeps the tracking history trustworthy.

## Where to find it in the code

Controllers are in [app/Http/Controllers/Admin/](../app/Http/Controllers/Admin), pages in
[resources/views/admin/](../resources/views/admin).

| Page | Address | File |
|---|---|---|
| Dashboard | `/admin` | [DashboardController](../app/Http/Controllers/Admin/DashboardController.php) |
| Seller applications | `/admin/seller-applications` | [SellerApplicationController](../app/Http/Controllers/Admin/SellerApplicationController.php) |
| Sorting center applications | `/admin/sc-applications` | [SortingCenterApplicationController](../app/Http/Controllers/Admin/SortingCenterApplicationController.php) |
| Courier applications | `/admin/couriers` | [CourierController](../app/Http/Controllers/Admin/CourierController.php) |
| Product approvals | `/admin/product-approvals` | [ProductApprovalController](../app/Http/Controllers/Admin/ProductApprovalController.php) |
| GCash payments | `/admin/gcash-payments` | [GcashPaymentController](../app/Http/Controllers/Admin/GcashPaymentController.php), [GcashChecks](../app/Services/GcashChecks.php), [AlvyGcash](../app/Support/AlvyGcash.php) |
| Compliance | `/admin/compliance` | [ComplianceController](../app/Http/Controllers/Admin/ComplianceController.php), [SellerViolation](../app/Models/SellerViolation.php) |
| Reviews | `/admin/reviews` | [ReviewController](../app/Http/Controllers/Admin/ReviewController.php) |
| Users, customers, sellers | `/admin/users`, `/admin/customers`, `/admin/sellers` | [UserController](../app/Http/Controllers/Admin/UserController.php), [CustomerController](../app/Http/Controllers/Admin/CustomerController.php), [SellerController](../app/Http/Controllers/Admin/SellerController.php) |
| Sorting centers, riders | `/admin/sorting-centers`, `/admin/riders` | [SortingCenterDirectoryController](../app/Http/Controllers/Admin/SortingCenterDirectoryController.php), [RiderDirectoryController](../app/Http/Controllers/Admin/RiderDirectoryController.php) |
| Orders and disputes | `/admin/orders` | [OrderController](../app/Http/Controllers/Admin/OrderController.php) |
| Vouchers | `/admin/vouchers` | [VoucherController](../app/Http/Controllers/Admin/VoucherController.php) |
| Banners | `/admin/banners` | [HomeBannerController](../app/Http/Controllers/Admin/HomeBannerController.php), [HomeBanner](../app/Models/HomeBanner.php) |
| Categories | `/admin/categories` | [CategoryController](../app/Http/Controllers/Admin/CategoryController.php) |
| Settings, policies | `/admin/settings` | [SettingsController](../app/Http/Controllers/Admin/SettingsController.php), [PlatformPolicy](../app/Models/PlatformPolicy.php) |
| Analytics, reports | `/admin/analytics`, `/admin/reports` | [AnalyticsController](../app/Http/Controllers/Admin/AnalyticsController.php), [ReportController](../app/Http/Controllers/Admin/ReportController.php) |
| Activity log | `/admin/activity` | [ActivityLogController](../app/Http/Controllers/Admin/ActivityLogController.php) |
| Logistics | `/admin/logistics` | [Admin/Logistics/](../app/Http/Controllers/Admin/Logistics) |
| The "can't change steps" rule | | [AdminCannotChangeShipmentStatus](../app/Http/Middleware/AdminCannotChangeShipmentStatus.php) |
