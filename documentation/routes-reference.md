# Routes reference

Every web page, form action and app API address, grouped by portal. Generated from
`php artisan route:list --json`, so it matches the code at the time it was written.
To refresh it after adding routes, run the same command (see [README](README.md#keeping-these-pages-up-to-date)).

- **Method**: GET opens a page, POST/PUT/PATCH/DELETE is a form or button action.
- **Name**: the route name used in `route('...')` inside controllers and Blade views.
- **Handled by**: the controller and method under `app/Http/Controllers/`.

## Contents
- [Storefront and buyer pages](#storefront-and-buyer-pages)
- [Seller Centre (/seller)](#seller-centre-seller)
- [Sorting center / logistics company (/sc, /sorting-center)](#sorting-center--logistics-company-sc-sorting-center)
- [Rider and courier portal (/courier)](#rider-and-courier-portal-courier)
- [Admin panel (/admin)](#admin-panel-admin)
- [Mobile app API (/api/v1)](#mobile-app-api-apiv1)
- [Address lookups (/api/psgc and others)](#address-lookups-apipsgc-and-others)

## Storefront and buyer pages

104 routes.

Controllers in this section: [AuthenticatedSessionController](../app/Http/Controllers/Auth/AuthenticatedSessionController.php), [GoogleAuthController](../app/Http/Controllers/Auth/GoogleAuthController.php), [NewPasswordController](../app/Http/Controllers/Auth/NewPasswordController.php), [PasswordResetLinkController](../app/Http/Controllers/Auth/PasswordResetLinkController.php), [PortalHandoffController](../app/Http/Controllers/Auth/PortalHandoffController.php), [RegisteredUserController](../app/Http/Controllers/Auth/RegisteredUserController.php), [AddressController](../app/Http/Controllers/Buyer/AddressController.php), [CartController](../app/Http/Controllers/Buyer/CartController.php), [CheckoutController](../app/Http/Controllers/Buyer/CheckoutController.php), [FlashSaleReminderController](../app/Http/Controllers/Buyer/FlashSaleReminderController.php), [GcashResubmitController](../app/Http/Controllers/Buyer/GcashResubmitController.php), [OrderController](../app/Http/Controllers/Buyer/OrderController.php), [PaymentController](../app/Http/Controllers/Buyer/PaymentController.php), [ProfileController](../app/Http/Controllers/Buyer/ProfileController.php), [ReviewController](../app/Http/Controllers/Buyer/ReviewController.php), [VoucherWalletController](../app/Http/Controllers/Buyer/VoucherWalletController.php), [WishlistController](../app/Http/Controllers/Buyer/WishlistController.php), [RegistrationController](../app/Http/Controllers/Courier/RegistrationController.php), [SellerApplicationController](../app/Http/Controllers/Seller/SellerApplicationController.php), [AccountActivationController](../app/Http/Controllers/Shared/AccountActivationController.php), [AssistantController](../app/Http/Controllers/Shared/AssistantController.php), [MessageController](../app/Http/Controllers/Shared/MessageController.php), [PasswordChangeController](../app/Http/Controllers/Shared/PasswordChangeController.php), [ProfilePhotoController](../app/Http/Controllers/Shared/ProfilePhotoController.php), [ProtectedFileController](../app/Http/Controllers/Shared/ProtectedFileController.php), [ShipmentTrackingController](../app/Http/Controllers/Shared/ShipmentTrackingController.php), [AppDownloadController](../app/Http/Controllers/Storefront/AppDownloadController.php), [CategoryPageController](../app/Http/Controllers/Storefront/CategoryPageController.php), [HomeController](../app/Http/Controllers/Storefront/HomeController.php), [PolicyController](../app/Http/Controllers/Storefront/PolicyController.php), [ProductController](../app/Http/Controllers/Storefront/ProductController.php), [ShopController](../app/Http/Controllers/Storefront/ShopController.php), [StoreController](../app/Http/Controllers/Storefront/StoreController.php).

| Method | URL | Name | Handled by |
|---|---|---|---|
| GET | `/` | `home` | Storefront/HomeController → index |
| POST | `/account/password/cancel` | `password.change.cancel` | Shared/PasswordChangeController → cancel |
| POST | `/account/password/resend` | `password.change.resend` | Shared/PasswordChangeController → resend |
| POST | `/account/password/verify` | `password.change.verify` | Shared/PasswordChangeController → verify |
| POST | `/account/photo` | `account.photo` | Shared/ProfilePhotoController → update |
| GET | `/activate/{token}` | `account.activate` | Shared/AccountActivationController → show |
| POST | `/activate/{token}` | `account.activate.store` | Shared/AccountActivationController → store |
| GET | `/app` | `app.page` | Storefront/AppDownloadController → show |
| GET | `/app/download` | `app.download` | Storefront/AppDownloadController → download |
| POST | `/assistant/actions/{token}` | `assistant.confirm` | Shared/AssistantController → confirm |
| POST | `/assistant/ask` | `assistant.ask` | Shared/AssistantController → ask |
| GET | `/assistant/history` | `assistant.history` | Shared/AssistantController → history |
| POST | `/assistant/reset` | `assistant.reset` | Shared/AssistantController → reset |
| GET | `/auth/google` | `auth.google` | Auth/GoogleAuthController → redirect |
| GET | `/auth/google/callback` | `auth.google.callback` | Auth/GoogleAuthController → callback |
| GET | `/become-courier` | `courier.register` | Courier/RegistrationController → create |
| POST | `/become-courier` | `courier.register.store` | Courier/RegistrationController → store |
| GET | `/become-seller` | `seller.apply` | Seller/SellerApplicationController → create |
| POST | `/become-seller` | `seller.apply.store` | Seller/SellerApplicationController → store |
| GET | `/cart` | `cart.index` | Buyer/CartController → index |
| DELETE | `/cart/item/{cartItemId}` | `cart.destroy` | Buyer/CartController → destroy |
| PATCH | `/cart/item/{cartItemId}` | `cart.update` | Buyer/CartController → update |
| POST | `/cart/{bookId}` | `cart.store` | Buyer/CartController → store |
| GET | `/categories` | `categories.page` | Storefront/CategoryPageController → index |
| GET | `/categories/{slug}` | `categories.show` | Storefront/CategoryPageController → show |
| POST | `/chat/send/{type}/{id}` | `chat.send` | Shared/MessageController → widgetSend |
| GET | `/chat/thread/{type}/{id}` | `chat.thread` | Shared/MessageController → widgetThread |
| GET | `/chat/threads` | `chat.threads` | Shared/MessageController → widgetThreads |
| GET | `/checkout` | `checkout.index` | Buyer/CheckoutController → index |
| POST | `/checkout` | `checkout.store` | Buyer/CheckoutController → store |
| GET | `/checkout/confirmation` | `checkout.confirmation` | Buyer/CheckoutController → confirmation |
| POST | `/checkout/preview` | `checkout.preview` | Buyer/CheckoutController → preview |
| POST | `/checkout/quantity` | `checkout.quantity` | Buyer/CheckoutController → quantity |
| GET | `/dbconn` |  | (inline in the routes file) |
| GET | `/files/{path}` | `files.show` | Shared\ProtectedFileController (invokable) |
| POST | `/flash-sales/{flashSale}/remind` | `flash-sales.remind` | Buyer/FlashSaleReminderController → toggle |
| GET | `/following` | `stores.following` | Storefront/StoreController → following |
| GET | `/forgot-password` | `password.request` | Auth/PasswordResetLinkController → create |
| POST | `/forgot-password` | `password.email` | Auth/PasswordResetLinkController → store |
| GET | `/login` | `login` | Auth/AuthenticatedSessionController → create |
| POST | `/login` | `login.attempt` | Auth/AuthenticatedSessionController → store |
| GET | `/login/verify` | `otp.show` | Auth/AuthenticatedSessionController → showOtp |
| POST | `/login/verify` | `otp.verify` | Auth/AuthenticatedSessionController → verifyOtp |
| POST | `/login/verify/resend` | `otp.resend` | Auth/AuthenticatedSessionController → resendOtp |
| GET|POST|PUT|PATCH|DELETE|OPTIONS | `/logistics/login` |  | Illuminate\Routing\RedirectController (invokable) |
| POST | `/logout` | `logout` | Auth/AuthenticatedSessionController → destroy |
| GET | `/messages` | `messages.inbox` | Shared/MessageController → inbox |
| GET | `/messages/direct/{threadKey}` | `messages.direct` | Shared/MessageController → directShow |
| POST | `/messages/direct/{threadKey}` | `messages.direct.store` | Shared/MessageController → directStore |
| POST | `/messages/new` | `messages.new` | Shared/MessageController → directNew |
| GET | `/my-vouchers` | `vouchers.wallet` | Buyer/VoucherWalletController → index |
| POST | `/notifications/read-all` | `notifications.read-all` | Buyer/ProfileController → readAllNotifications |
| GET | `/notifications/{id}/open` | `notifications.open` | Buyer/ProfileController → openNotification |
| POST | `/orders/{id}/cancel` | `orders.cancel` | Buyer/OrderController → cancelByBuyer |
| POST | `/orders/{id}/confirm-delivery` | `orders.confirm-delivery` | Buyer/OrderController → confirmDelivery |
| POST | `/orders/{id}/escalate` | `orders.escalate` | Buyer/OrderController → escalateReturn |
| POST | `/orders/{id}/gcash-receipt` | `orders.gcash-resubmit` | Buyer/GcashResubmitController → store |
| POST | `/orders/{id}/pay` | `orders.pay` | Buyer/PaymentController → pay |
| POST | `/orders/{id}/return` | `orders.return` | Buyer/OrderController → requestReturn |
| GET | `/orders/{order}/payment-proof` | `orders.payment-proof` | Shared/ShipmentTrackingController → paymentProof |
| GET | `/orders/{order}/proof-of-delivery` | `shipments.pod` | Shared/ShipmentTrackingController → proofOfDelivery |
| GET | `/payments/{payment}/return` | `payments.return` | Buyer/PaymentController → back |
| GET | `/payments/{payment}/sandbox` | `payments.sandbox` | Buyer/PaymentController → sandbox |
| POST | `/payments/{payment}/sandbox` | `payments.sandbox.act` | Buyer/PaymentController → sandboxAction |
| GET | `/policies/{key}` | `policies.show` | Storefront/PolicyController → show |
| GET | `/products/{slug}` | `products.show` | Storefront/ProductController → show |
| GET | `/profile` | `profile.show` | Buyer/ProfileController → show |
| GET | `/profile/addresses` | `profile.addresses` | Buyer/ProfileController → addresses |
| POST | `/profile/addresses` | `addresses.store` | Buyer/AddressController → store |
| DELETE | `/profile/addresses/{address}` | `addresses.destroy` | Buyer/AddressController → destroy |
| PATCH | `/profile/addresses/{address}` | `addresses.update` | Buyer/AddressController → update |
| PATCH | `/profile/addresses/{address}/set-default` | `addresses.set-default` | Buyer/AddressController → setDefault |
| GET | `/profile/notifications` | `profile.notifications` | Buyer/ProfileController → notifications |
| GET | `/profile/orders` | `profile.orders` | Buyer/ProfileController → orders |
| GET | `/profile/orders/{id}` | `profile.orders.show` | Buyer/ProfileController → orderShow |
| GET | `/profile/personal-info` | `profile.personal-info` | Buyer/ProfileController → personalInfo |
| PATCH | `/profile/personal-info` | `profile.update` | Buyer/ProfileController → update |
| GET | `/profile/security` | `profile.security` | Buyer/ProfileController → security |
| PATCH | `/profile/security/account` | `profile.account.update` | Buyer/ProfileController → updateAccount |
| POST | `/profile/security/cancel` | `profile.contact.cancel` | Buyer/ProfileController → cancelContactChange |
| POST | `/profile/security/email` | `profile.email.change` | Buyer/ProfileController → changeEmail |
| PATCH | `/profile/security/password` | `profile.change-password` | Buyer/ProfileController → changePassword |
| POST | `/profile/security/password/cancel` | `profile.password.cancel` | Buyer/ProfileController → cancelPasswordChange |
| POST | `/profile/security/password/confirm` | `profile.password.confirm` | Buyer/ProfileController → confirmPasswordChange |
| POST | `/profile/security/password/resend` | `profile.password.resend` | Buyer/ProfileController → resendPasswordCode |
| POST | `/profile/security/phone` | `profile.phone.change` | Buyer/ProfileController → changePhone |
| POST | `/profile/security/resend` | `profile.contact.resend` | Buyer/ProfileController → resendContactCode |
| POST | `/profile/security/verify` | `profile.contact.verify` | Buyer/ProfileController → verifyContact |
| GET | `/profile/settings` | `profile.settings` | Buyer/ProfileController → settings |
| GET | `/register` | `register` | Auth/RegisteredUserController → create |
| POST | `/register` | `register.store` | Auth/RegisteredUserController → store |
| POST | `/reset-password` | `password.store` | Auth/NewPasswordController → store |
| GET | `/reset-password/{token}` | `password.reset` | Auth/NewPasswordController → create |
| POST | `/reviews/item/{item}` | `reviews.store` | Buyer/ReviewController → store |
| PATCH | `/reviews/{review}` | `reviews.update` | Buyer/ReviewController → update |
| GET | `/session/handoff/{token}` | `portal.handoff` | Auth\PortalHandoffController (invokable) |
| GET | `/shop` | `shop.index` | Storefront/ShopController → index |
| GET | `/store/{sellerId}` | `stores.show` | Storefront/StoreController → show |
| POST | `/store/{sellerId}/follow` | `stores.follow` | Storefront/StoreController → toggleFollow |
| GET | `/vouchers` | `vouchers.center` | Buyer/VoucherWalletController → center |
| POST | `/vouchers/claim-all` | `vouchers.claim-all` | Buyer/VoucherWalletController → claimAll |
| POST | `/vouchers/{voucher}/claim` | `vouchers.claim` | Buyer/VoucherWalletController → claim |
| GET | `/wishlist` | `wishlist.index` | Buyer/WishlistController → index |
| POST | `/wishlist/{productId}` | `wishlist.toggle` | Buyer/WishlistController → toggle |

## Seller Centre (/seller)

82 routes.

Controllers in this section: [AuthenticatedSessionController](../app/Http/Controllers/Auth/AuthenticatedSessionController.php), [PortalHandoffController](../app/Http/Controllers/Auth/PortalHandoffController.php), [AccountController](../app/Http/Controllers/Seller/AccountController.php), [BookController](../app/Http/Controllers/Seller/BookController.php), [CommissionController](../app/Http/Controllers/Seller/CommissionController.php), [DashboardController](../app/Http/Controllers/Seller/DashboardController.php), [EarningsController](../app/Http/Controllers/Seller/EarningsController.php), [FlashSaleController](../app/Http/Controllers/Seller/FlashSaleController.php), [OrderController](../app/Http/Controllers/Seller/OrderController.php), [PromotionController](../app/Http/Controllers/Seller/PromotionController.php), [RegistrationController](../app/Http/Controllers/Seller/RegistrationController.php), [ReportController](../app/Http/Controllers/Seller/ReportController.php), [ReviewController](../app/Http/Controllers/Seller/ReviewController.php), [StatusController](../app/Http/Controllers/Seller/StatusController.php), [StoreCategoryController](../app/Http/Controllers/Seller/StoreCategoryController.php), [StoreSettingsController](../app/Http/Controllers/Seller/StoreSettingsController.php), [StrikesController](../app/Http/Controllers/Seller/StrikesController.php), [VoucherController](../app/Http/Controllers/Seller/VoucherController.php), [MessageController](../app/Http/Controllers/Shared/MessageController.php), [ShipmentTrackingController](../app/Http/Controllers/Shared/ShipmentTrackingController.php).

| Method | URL | Name | Handled by |
|---|---|---|---|
| GET | `/seller` | `seller.dashboard` | Seller/DashboardController → index |
| GET | `/seller/account` | `seller.account` | Seller/AccountController → index |
| PATCH | `/seller/account` | `seller.account.update` | Seller/AccountController → update |
| GET | `/seller/account/security` | `seller.account.security` | Seller/AccountController → security |
| PUT | `/seller/account/security` | `seller.account.password` | Seller/AccountController → changePassword |
| POST | `/seller/account/security/password/cancel` | `seller.account.password.cancel` | Seller/AccountController → cancelPasswordChange |
| POST | `/seller/account/security/password/confirm` | `seller.account.password.confirm` | Seller/AccountController → confirmPasswordChange |
| POST | `/seller/account/security/password/resend` | `seller.account.password.resend` | Seller/AccountController → resendPasswordCode |
| GET | `/seller/books` | `seller.books.index` | Seller/BookController → index |
| POST | `/seller/books` | `seller.books.store` | Seller/BookController → store |
| POST | `/seller/books/bulk` | `seller.books.bulk` | Seller/BookController → bulk |
| GET | `/seller/books/create` | `seller.books.create` | Seller/BookController → create |
| DELETE | `/seller/books/{book}` | `seller.books.destroy` | Seller/BookController → destroy |
| PUT | `/seller/books/{book}` | `seller.books.update` | Seller/BookController → update |
| PATCH | `/seller/books/{book}/archive` | `seller.books.archive` | Seller/BookController → archive |
| POST | `/seller/books/{book}/duplicate` | `seller.books.duplicate` | Seller/BookController → duplicate |
| GET | `/seller/books/{book}/edit` | `seller.books.edit` | Seller/BookController → edit |
| PATCH | `/seller/books/{book}/end-sale` | `seller.books.end-sale` | Seller/BookController → endSale |
| PATCH | `/seller/books/{book}/stock` | `seller.books.stock` | Seller/BookController → updateStock |
| PATCH | `/seller/books/{book}/unarchive` | `seller.books.unarchive` | Seller/BookController → unarchive |
| POST | `/seller/chat/send/{type}/{id}` | `seller.chat.send` | Shared/MessageController → widgetSend |
| GET | `/seller/chat/thread/{type}/{id}` | `seller.chat.thread` | Shared/MessageController → widgetThread |
| GET | `/seller/chat/threads` | `seller.chat.threads` | Shared/MessageController → widgetThreads |
| GET | `/seller/commissions` | `seller.commissions` | Seller/CommissionController → index |
| GET | `/seller/earnings` | `seller.earnings` | Seller/EarningsController → index |
| POST | `/seller/earnings/payouts` | `seller.earnings.payout` | Seller/EarningsController → requestPayout |
| GET | `/seller/flash-sales` | `seller.flash-sales.index` | Seller/FlashSaleController → index |
| POST | `/seller/flash-sales` | `seller.flash-sales.store` | Seller/FlashSaleController → store |
| DELETE | `/seller/flash-sales/{flashSale}` | `seller.flash-sales.destroy` | Seller/FlashSaleController → destroy |
| GET | `/seller/login` | `seller.login` | Auth/AuthenticatedSessionController → create |
| POST | `/seller/login` | `seller.login.attempt` | Auth/AuthenticatedSessionController → store |
| POST | `/seller/logout` | `seller.logout` | Auth/AuthenticatedSessionController → destroy |
| GET | `/seller/messages` | `seller.messages.inbox` | Shared/MessageController → inbox |
| GET | `/seller/messages/direct/{threadKey}` | `seller.messages.direct` | Shared/MessageController → directShow |
| POST | `/seller/messages/direct/{threadKey}` | `seller.messages.direct.store` | Shared/MessageController → directStore |
| POST | `/seller/messages/new` | `seller.messages.new` | Shared/MessageController → directNew |
| GET | `/seller/notifications` | `seller.notifications` | Seller/DashboardController → notifications |
| POST | `/seller/notifications/read-all` | `seller.notifications.read-all` | Seller/DashboardController → readAllNotifications |
| GET | `/seller/notifications/{id}/open` | `seller.notifications.open` | Seller/DashboardController → openNotification |
| GET | `/seller/orders` | `seller.orders.index` | Seller/OrderController → index |
| GET | `/seller/orders/{id}` | `seller.orders.show` | Seller/OrderController → show |
| POST | `/seller/orders/{id}/accept` | `seller.orders.accept` | Seller/OrderController → accept |
| POST | `/seller/orders/{id}/cancel` | `seller.orders.cancel` | Seller/OrderController → cancel |
| POST | `/seller/orders/{id}/handed-over` | `seller.orders.handed-over` | Seller/OrderController → markHandedOver |
| POST | `/seller/orders/{id}/handover` | `seller.orders.handover` | Seller/OrderController → handover |
| POST | `/seller/orders/{id}/pack` | `seller.orders.pack` | Seller/OrderController → pack |
| POST | `/seller/orders/{id}/return` | `seller.orders.return` | Seller/OrderController → decideReturn |
| POST | `/seller/orders/{id}/returned-received` | `seller.orders.returned-received` | Seller/OrderController → returnedReceived |
| GET | `/seller/orders/{id}/waybill` | `seller.orders.waybill` | Seller/OrderController → waybill |
| GET | `/seller/orders/{order}/payment-proof` | `seller.orders.payment-proof` | Shared/ShipmentTrackingController → paymentProof |
| GET | `/seller/orders/{order}/proof-of-delivery` | `seller.shipments.pod` | Shared/ShipmentTrackingController → proofOfDelivery |
| GET | `/seller/promotions` | `seller.promotions.index` | Seller/PromotionController → index |
| POST | `/seller/promotions` | `seller.promotions.store` | Seller/PromotionController → store |
| DELETE | `/seller/promotions/{promotion}` | `seller.promotions.destroy` | Seller/PromotionController → destroy |
| PATCH | `/seller/promotions/{promotion}/toggle` | `seller.promotions.toggle` | Seller/PromotionController → toggle |
| GET | `/seller/register` | `seller.register` | Seller/RegistrationController → create |
| POST | `/seller/register` | `seller.register.store` | Seller/RegistrationController → store |
| GET | `/seller/reports` | `seller.reports` | Seller/ReportController → index |
| GET | `/seller/reviews` | `seller.reviews.index` | Seller/ReviewController → index |
| POST | `/seller/reviews/{review}/reply` | `seller.reviews.reply` | Seller/ReviewController → reply |
| PUT | `/seller/reviews/{review}/reply` | `seller.reviews.reply.update` | Seller/ReviewController → updateReply |
| GET | `/seller/session/handoff/{token}` | `seller.portal.handoff` | Auth\PortalHandoffController (invokable) |
| GET | `/seller/status` | `seller.status` | Seller/StatusController → show |
| POST | `/seller/status/resubmit` | `seller.status.resubmit` | Seller/StatusController → resubmit |
| GET | `/seller/store-categories` | `seller.store-categories.index` | Seller/StoreCategoryController → index |
| POST | `/seller/store-categories` | `seller.store-categories.store` | Seller/StoreCategoryController → store |
| POST | `/seller/store-categories/reorder` | `seller.store-categories.reorder` | Seller/StoreCategoryController → reorder |
| DELETE | `/seller/store-categories/{storeCategory}` | `seller.store-categories.destroy` | Seller/StoreCategoryController → destroy |
| PUT | `/seller/store-categories/{storeCategory}` | `seller.store-categories.update` | Seller/StoreCategoryController → update |
| PATCH | `/seller/store-categories/{storeCategory}/toggle` | `seller.store-categories.toggle` | Seller/StoreCategoryController → toggle |
| GET | `/seller/store-settings` | `seller.store-settings` | Seller/StoreSettingsController → edit |
| PATCH | `/seller/store-settings` | `seller.store-settings.update` | Seller/StoreSettingsController → update |
| GET | `/seller/strikes` | `seller.strikes` | Seller/StrikesController → index |
| POST | `/seller/strikes/{strike}/appeal` | `seller.strikes.appeal` | Seller/StrikesController → appeal |
| POST | `/seller/violations/{violation}/acknowledge` | `seller.violations.acknowledge` | Seller/DashboardController → acknowledgeViolation |
| GET | `/seller/vouchers` | `seller.vouchers.index` | Seller/VoucherController → index |
| POST | `/seller/vouchers` | `seller.vouchers.store` | Seller/VoucherController → store |
| GET | `/seller/vouchers/create` | `seller.vouchers.create` | Seller/VoucherController → create |
| DELETE | `/seller/vouchers/{voucher}` | `seller.vouchers.destroy` | Seller/VoucherController → destroy |
| PUT | `/seller/vouchers/{voucher}` | `seller.vouchers.update` | Seller/VoucherController → update |
| GET | `/seller/vouchers/{voucher}/edit` | `seller.vouchers.edit` | Seller/VoucherController → edit |
| PATCH | `/seller/vouchers/{voucher}/toggle` | `seller.vouchers.toggle` | Seller/VoucherController → toggle |

## Sorting center / logistics company (/sc, /sorting-center)

105 routes.

Controllers in this section: [AuthenticatedSessionController](../app/Http/Controllers/Auth/AuthenticatedSessionController.php), [PortalHandoffController](../app/Http/Controllers/Auth/PortalHandoffController.php), [CommissionController](../app/Http/Controllers/SortingCenter/CommissionController.php), [CourierApplicationController](../app/Http/Controllers/SortingCenter/CourierApplicationController.php), [IssuesController](../app/Http/Controllers/SortingCenter/IssuesController.php), [LogisticsController](../app/Http/Controllers/SortingCenter/LogisticsController.php), [RegistrationController](../app/Http/Controllers/SortingCenter/RegistrationController.php), [ReturnsController](../app/Http/Controllers/SortingCenter/ReturnsController.php), [RiderWorkController](../app/Http/Controllers/SortingCenter/RiderWorkController.php), [ShipmentController](../app/Http/Controllers/SortingCenter/ShipmentController.php), [SortingCenterController](../app/Http/Controllers/SortingCenter/SortingCenterController.php), [TripsController](../app/Http/Controllers/SortingCenter/TripsController.php).

| Method | URL | Name | Handled by |
|---|---|---|---|
| GET | `/sc/account` | `sc.account` | SortingCenter/SortingCenterController → account |
| PUT | `/sc/account` | `sc.account.update` | SortingCenter/SortingCenterController → updateAccount |
| GET | `/sc/account/security` | `sc.account.security` | SortingCenter/SortingCenterController → security |
| PUT | `/sc/account/security` | `sc.account.password` | SortingCenter/SortingCenterController → changePassword |
| POST | `/sc/account/security/password/cancel` | `sc.account.password.cancel` | SortingCenter/SortingCenterController → cancelPasswordChange |
| POST | `/sc/account/security/password/confirm` | `sc.account.password.confirm` | SortingCenter/SortingCenterController → confirmPasswordChange |
| POST | `/sc/account/security/password/resend` | `sc.account.password.resend` | SortingCenter/SortingCenterController → resendPasswordCode |
| GET | `/sc/activity` | `sc.activity` | SortingCenter/LogisticsController → activity |
| GET | `/sc/areas` | `sc.areas` | SortingCenter/SortingCenterController → areas |
| POST | `/sc/areas` | `sc.areas.store` | SortingCenter/SortingCenterController → storeArea |
| DELETE | `/sc/areas/{area}` | `sc.areas.destroy` | SortingCenter/SortingCenterController → destroyArea |
| GET | `/sc/chat` | `sc.chat` | SortingCenter/SortingCenterController → chat |
| GET | `/sc/chat/poll` | `sc.chat.poll` | SortingCenter/SortingCenterController → pollMessages |
| POST | `/sc/chat/send` | `sc.chat.send` | SortingCenter/SortingCenterController → sendMessage |
| GET | `/sc/commissions` | `sc.commissions` | SortingCenter/CommissionController → index |
| GET | `/sc/courier-applications` | `sc.courier-applications.index` | SortingCenter/CourierApplicationController → index |
| GET | `/sc/courier-applications/{courier}` | `sc.courier-applications.show` | SortingCenter/CourierApplicationController → show |
| POST | `/sc/courier-applications/{courier}/approve` | `sc.courier-applications.approve` | SortingCenter/CourierApplicationController → approve |
| GET | `/sc/courier-applications/{courier}/documents/{document}` | `sc.courier-applications.document` | SortingCenter/CourierApplicationController → document |
| POST | `/sc/courier-applications/{courier}/reject` | `sc.courier-applications.reject` | SortingCenter/CourierApplicationController → reject |
| GET | `/sc/dashboard` | `sc.dashboard` | SortingCenter/SortingCenterController → dashboard |
| POST | `/sc/deliveries/{delivery}/reschedule` | `sc.deliveries.reschedule` | SortingCenter/SortingCenterController → rescheduleDelivery |
| POST | `/sc/deliveries/{delivery}/return` | `sc.deliveries.return` | SortingCenter/SortingCenterController → returnParcel |
| POST | `/sc/deliveries/{delivery}/status` | `sc.deliveries.update-status` | SortingCenter/SortingCenterController → updateDeliveryStatus |
| GET | `/sc/delivery-assignment` | `sc.delivery-assignment` | SortingCenter/SortingCenterController → deliveryAssignment |
| GET | `/sc/delivery-monitoring` | `sc.delivery-monitoring` | SortingCenter/SortingCenterController → deliveryMonitoring |
| GET | `/sc/dispatch` | `sc.dispatch` | SortingCenter/RiderWorkController → dispatchBoard |
| POST | `/sc/dispatch/auto-all/{kind}` | `sc.dispatch.auto-all` | SortingCenter/RiderWorkController → autoAssignAll |
| POST | `/sc/dispatch/bulk` | `sc.dispatch.bulk` | SortingCenter/LogisticsController → bulkAssign |
| POST | `/sc/dispatch/{parcel}/assign` | `sc.dispatch.assign` | SortingCenter/RiderWorkController → manualAssign |
| POST | `/sc/dispatch/{parcel}/auto` | `sc.dispatch.auto` | SortingCenter/RiderWorkController → autoAssign |
| POST | `/sc/dispatch/{parcel}/reassign` | `sc.dispatch.reassign` | SortingCenter/LogisticsController → reassign |
| GET | `/sc/failed-deliveries` | `sc.failed-deliveries` | SortingCenter/IssuesController → failed |
| GET | `/sc/incoming-parcels` | `sc.incoming-parcels` | (inline in the routes file) |
| GET | `/sc/login` | `sc.login` | Auth/AuthenticatedSessionController → create |
| POST | `/sc/login` | `sc.login.attempt` | Auth/AuthenticatedSessionController → store |
| POST | `/sc/logout` | `sc.logout` | SortingCenter/SortingCenterController → logout |
| GET | `/sc/parcel-sorting` | `sc.parcel-sorting` | SortingCenter/SortingCenterController → parcelSorting |
| POST | `/sc/parcels/scan` | `sc.parcels.scan` | SortingCenter/SortingCenterController → scanParcel |
| POST | `/sc/parcels/{parcel}/advance` | `sc.parcels.advance` | SortingCenter/SortingCenterController → advanceParcel |
| POST | `/sc/parcels/{parcel}/assign` | `sc.parcels.assign` | SortingCenter/SortingCenterController → assignParcel |
| POST | `/sc/parcels/{parcel}/exception` | `sc.parcels.exception` | SortingCenter/LogisticsController → markException |
| POST | `/sc/parcels/{parcel}/sort` | `sc.parcels.sort` | SortingCenter/SortingCenterController → sortParcel |
| POST | `/sc/parcels/{parcel}/sort-transfer` | `sc.parcels.sort-transfer` | SortingCenter/SortingCenterController → sortForTransfer |
| GET | `/sc/parcels/{parcel}/waybill` | `sc.parcels.waybill` | SortingCenter/SortingCenterController → waybill |
| GET | `/sc/pickup-requests` | `sc.pickup-requests` | SortingCenter/SortingCenterController → pickupRequests |
| POST | `/sc/pickup-requests/{parcel}/approve` | `sc.pickup-requests.approve` | SortingCenter/SortingCenterController → approvePickup |
| POST | `/sc/pickup-requests/{parcel}/reject` | `sc.pickup-requests.reject` | SortingCenter/SortingCenterController → rejectPickup |
| GET | `/sc/rates` | `sc.rates` | SortingCenter/LogisticsController → rates |
| POST | `/sc/rates` | `sc.rates.update` | SortingCenter/LogisticsController → updateRate |
| GET | `/sc/register` | `sc.register` | SortingCenter/RegistrationController → create |
| POST | `/sc/register` | `sc.register.store` | SortingCenter/RegistrationController → store |
| GET | `/sc/remittances` | `sc.remittances` | SortingCenter/RiderWorkController → remittances |
| POST | `/sc/remittances/{remittance}/confirm` | `sc.remittances.confirm` | SortingCenter/RiderWorkController → confirmRemittance |
| GET | `/sc/reports` | `sc.reports` | SortingCenter/SortingCenterController → reports |
| GET | `/sc/returns` | `sc.returns` | SortingCenter/ReturnsController → index |
| POST | `/sc/returns/{return}/assign` | `sc.returns.assign` | SortingCenter/ReturnsController → assignPickup |
| POST | `/sc/returns/{return}/receive` | `sc.returns.receive` | SortingCenter/ReturnsController → receive |
| POST | `/sc/returns/{return}/to-seller` | `sc.returns.to-seller` | SortingCenter/ReturnsController → assignSeller |
| GET | `/sc/rider-pay` | `sc.rider-pay` | SortingCenter/RiderWorkController → riderPay |
| POST | `/sc/rider-pay/cashouts/{entry}/paid` | `sc.rider-pay.cashout.paid` | SortingCenter/RiderWorkController → cashoutPaid |
| POST | `/sc/rider-pay/cashouts/{entry}/reject` | `sc.rider-pay.cashout.reject` | SortingCenter/RiderWorkController → cashoutReject |
| POST | `/sc/rider-pay/{rider}/send` | `sc.rider-pay.send` | SortingCenter/RiderWorkController → sendPay |
| GET | `/sc/riders` | `sc.riders` | SortingCenter/SortingCenterController → riders |
| POST | `/sc/riders` | `sc.riders.store` | SortingCenter/SortingCenterController → storeRider |
| GET | `/sc/riders/{rider}` | `sc.riders.show` | SortingCenter/RiderWorkController → showRider |
| POST | `/sc/riders/{rider}/approve` | `sc.riders.approve` | SortingCenter/SortingCenterController → approveRider |
| POST | `/sc/riders/{rider}/areas` | `sc.riders.areas.add` | SortingCenter/SortingCenterController → addRiderArea |
| POST | `/sc/riders/{rider}/areas/{area}/remove` | `sc.riders.areas.remove` | SortingCenter/SortingCenterController → removeRiderArea |
| GET | `/sc/riders/{rider}/documents/{document}` | `sc.riders.document` | SortingCenter/RiderWorkController → riderDocument |
| POST | `/sc/riders/{rider}/reject` | `sc.riders.reject` | SortingCenter/SortingCenterController → rejectRider |
| POST | `/sc/riders/{rider}/toggle` | `sc.riders.toggle` | SortingCenter/SortingCenterController → toggleRider |
| POST | `/sc/riders/{rider}/transport` | `sc.riders.transport` | SortingCenter/ShipmentController → toggleTransport |
| POST | `/sc/riders/{rider}/verify` | `sc.riders.verify` | SortingCenter/RiderWorkController → verifyRider |
| GET | `/sc/rts` | `sc.rts` | SortingCenter/LogisticsController → rtsQueue |
| POST | `/sc/rts/{parcel}/assign` | `sc.rts.assign` | SortingCenter/LogisticsController → assignRts |
| GET | `/sc/session/handoff/{token}` | `sc.portal.handoff` | Auth\PortalHandoffController (invokable) |
| GET | `/sc/shipments` | `sc.shipments` | (inline in the routes file) |
| GET | `/sc/tracking` | `sc.tracking` | SortingCenter/ShipmentController → index |
| GET | `/sc/tracking/{parcel}` | `sc.tracking.show` | SortingCenter/ShipmentController → show |
| POST | `/sc/tracking/{parcel}/receive` | `sc.tracking.receive` | SortingCenter/ShipmentController → receive |
| POST | `/sc/tracking/{parcel}/start-sorting` | `sc.tracking.start-sorting` | SortingCenter/ShipmentController → startSorting |
| GET|POST|PUT|PATCH|DELETE|OPTIONS | `/sc/transfers` | `sc.transfers` | Illuminate\Routing\RedirectController (invokable) |
| POST | `/sc/transfers/initiate` | `sc.transfers.initiate` | SortingCenter/SortingCenterController → initiateTransfer |
| POST | `/sc/transfers/{transfer}/accept` | `sc.transfers.accept` | SortingCenter/SortingCenterController → acceptTransfer |
| POST | `/sc/transfers/{transfer}/cancel` | `sc.transfers.cancel` | SortingCenter/SortingCenterController → cancelTransfer |
| POST | `/sc/transfers/{transfer}/dispatch` | `sc.transfers.dispatch` | SortingCenter/SortingCenterController → dispatchTransfer |
| POST | `/sc/transfers/{transfer}/reject` | `sc.transfers.reject` | SortingCenter/SortingCenterController → rejectTransfer |
| GET | `/sc/trips` | `sc.trips` | SortingCenter/TripsController → index |
| POST | `/sc/trips` | `sc.trips.store` | SortingCenter/TripsController → store |
| POST | `/sc/trips/auto-assign` | `sc.trips.auto-all` | SortingCenter/TripsController → autoAssignAll |
| GET | `/sc/trips/{trip}` | `sc.trips.show` | SortingCenter/TripsController → show |
| POST | `/sc/trips/{trip}/assign` | `sc.trips.assign` | SortingCenter/TripsController → assign |
| POST | `/sc/trips/{trip}/auto` | `sc.trips.auto` | SortingCenter/TripsController → autoAdd |
| POST | `/sc/trips/{trip}/cancel` | `sc.trips.cancel` | SortingCenter/TripsController → cancel |
| POST | `/sc/trips/{trip}/message` | `sc.trips.message` | SortingCenter/TripsController → message |
| POST | `/sc/trips/{trip}/parcels` | `sc.trips.parcels` | SortingCenter/TripsController → addParcels |
| POST | `/sc/trips/{trip}/parcels/{parcel}/remove` | `sc.trips.parcels.remove` | SortingCenter/TripsController → removeParcel |
| POST | `/sc/trips/{trip}/receive` | `sc.trips.receive` | SortingCenter/TripsController → receive |
| POST | `/sc/trips/{trip}/respond` | `sc.trips.respond` | SortingCenter/TripsController → respond |
| POST | `/sc/trips/{trip}/seal` | `sc.trips.seal` | SortingCenter/TripsController → seal |
| GET | `/sc/waybills` | `sc.waybills` | SortingCenter/SortingCenterController → waybills |
| POST | `/sc/zones` | `sc.zones.store` | SortingCenter/LogisticsController → storeZone |
| GET|POST|PUT|PATCH|DELETE|OPTIONS | `/sorting-center` |  | Illuminate\Routing\RedirectController (invokable) |
| GET|POST|PUT|PATCH|DELETE|OPTIONS | `/sorting-center/login` | `sorting-center.login` | Illuminate\Routing\RedirectController (invokable) |

## Rider and courier portal (/courier)

64 routes.

Controllers in this section: [AuthenticatedSessionController](../app/Http/Controllers/Auth/AuthenticatedSessionController.php), [PortalHandoffController](../app/Http/Controllers/Auth/PortalHandoffController.php), [AccountController](../app/Http/Controllers/Courier/AccountController.php), [DashboardController](../app/Http/Controllers/Courier/DashboardController.php), [DeliveryController](../app/Http/Controllers/Courier/DeliveryController.php), [ProfitController](../app/Http/Controllers/Courier/ProfitController.php), [RegistrationController](../app/Http/Controllers/Courier/RegistrationController.php), [RiderController](../app/Http/Controllers/Courier/RiderController.php), [TruckDutyController](../app/Http/Controllers/Courier/TruckDutyController.php), [MessageController](../app/Http/Controllers/Shared/MessageController.php).

| Method | URL | Name | Handled by |
|---|---|---|---|
| GET | `/courier` | `courier.dashboard` | Courier/DashboardController → index |
| GET | `/courier/account` | `courier.account` | Courier/AccountController → index |
| PUT | `/courier/account` | `courier.account.update` | Courier/AccountController → update |
| GET | `/courier/account/security` | `courier.account.security` | Courier/AccountController → security |
| PUT | `/courier/account/security` | `courier.account.password` | Courier/AccountController → updatePassword |
| POST | `/courier/chat/send/{type}/{id}` | `courier.chat.send` | Shared/MessageController → widgetSend |
| GET | `/courier/chat/thread/{type}/{id}` | `courier.chat.thread` | Shared/MessageController → widgetThread |
| GET | `/courier/chat/threads` | `courier.chat.threads` | Shared/MessageController → widgetThreads |
| GET | `/courier/deliveries/{id}` | `courier.deliveries.show` | Courier/DeliveryController → show |
| POST | `/courier/deliveries/{id}/accept` | `courier.deliveries.accept` | Courier/DeliveryController → accept |
| POST | `/courier/deliveries/{id}/complete` | `courier.deliveries.complete` | Courier/DeliveryController → complete |
| POST | `/courier/deliveries/{id}/in-transit` | `courier.deliveries.in-transit` | Courier/DeliveryController → inTransit |
| POST | `/courier/deliveries/{id}/pickup` | `courier.deliveries.pickup` | Courier/DeliveryController → pickup |
| GET | `/courier/history` | `courier.history` | Courier/DeliveryController → history |
| GET | `/courier/login` | `courier.login` | Auth/AuthenticatedSessionController → create |
| POST | `/courier/login` | `courier.login.attempt` | Auth/AuthenticatedSessionController → store |
| POST | `/courier/logout` | `courier.logout` | Auth/AuthenticatedSessionController → destroy |
| GET | `/courier/notifications` | `courier.notifications` | Courier/DashboardController → notifications |
| POST | `/courier/notifications/read-all` | `courier.notifications.read-all` | Courier/DashboardController → readAllNotifications |
| GET | `/courier/notifications/{id}/open` | `courier.notifications.open` | Courier/DashboardController → openNotification |
| GET | `/courier/profit` | `courier.profit` | Courier/ProfitController → index |
| GET | `/courier/rider` | `courier.rider` | Courier/RiderController → index |
| GET | `/courier/rider/cod` | `courier.rider.cod` | Courier/RiderController → cod |
| POST | `/courier/rider/cod/remit` | `courier.rider.cod.remit` | Courier/RiderController → remit |
| GET | `/courier/rider/cod/remittances/{remittance}/receipt` | `courier.rider.cod.receipt` | Courier/RiderController → remittanceReceipt |
| GET | `/courier/rider/deliveries/{delivery}` | `courier.rider.deliveries.show` | Courier/RiderController → delivery |
| POST | `/courier/rider/deliveries/{delivery}/deliver` | `courier.rider.deliveries.deliver` | Courier/RiderController → deliver |
| POST | `/courier/rider/deliveries/{delivery}/fail` | `courier.rider.deliveries.fail` | Courier/RiderController → fail |
| POST | `/courier/rider/deliveries/{delivery}/start` | `courier.rider.deliveries.start` | Courier/RiderController → startDelivery |
| GET | `/courier/rider/earnings` | `courier.rider.earnings` | Courier/RiderController → earnings |
| GET | `/courier/rider/history` | `courier.rider.history` | Courier/RiderController → history |
| POST | `/courier/rider/jobs/{kind}/{job}/acknowledge` | `courier.rider.jobs.acknowledge` | Courier/RiderController → acknowledge |
| POST | `/courier/rider/jobs/{kind}/{job}/decline` | `courier.rider.jobs.decline` | Courier/RiderController → declineJob |
| POST | `/courier/rider/offers/{offer}/accept` | `courier.rider.offers.accept` | Courier/RiderController → accept |
| POST | `/courier/rider/offers/{offer}/decline` | `courier.rider.offers.decline` | Courier/RiderController → decline |
| POST | `/courier/rider/online` | `courier.rider.online` | Courier/RiderController → toggleOnline |
| GET | `/courier/rider/pickups/{pickup}` | `courier.rider.pickups.show` | Courier/RiderController → pickup |
| POST | `/courier/rider/pickups/{pickup}/arriving` | `courier.rider.pickups.arriving` | Courier/RiderController → arriving |
| POST | `/courier/rider/pickups/{pickup}/confirm` | `courier.rider.pickups.confirm` | Courier/RiderController → confirmPickup |
| PUT | `/courier/rider/profile` | `courier.rider.profile.update` | Courier/RiderController → updateProfile |
| GET | `/courier/rider/profile/{rider?}` | `courier.rider.profile` | Courier/RiderController → profile |
| GET | `/courier/rider/return-pickups/{return}` | `courier.rider.return-pickups.show` | Courier/RiderController → returnPickup |
| POST | `/courier/rider/return-pickups/{return}/accept` | `courier.rider.return-pickups.accept` | Courier/RiderController → acceptReturnPickup |
| POST | `/courier/rider/return-pickups/{return}/confirm` | `courier.rider.return-pickups.confirm` | Courier/RiderController → confirmReturnPickup |
| POST | `/courier/rider/return-pickups/{return}/handed` | `courier.rider.return-pickups.handed` | Courier/RiderController → returnHandedToSeller |
| POST | `/courier/rider/returns/{parcel}/returned` | `courier.rider.returns.returned` | Courier/RiderController → returnedToSeller |
| GET | `/courier/rider/wallet` | `courier.rider.wallet` | Courier/RiderController → wallet |
| POST | `/courier/rider/wallet/cashout` | `courier.rider.wallet.cashout` | Courier/RiderController → cashout |
| GET | `/courier/rider/{rider}/documents/{document}` | `courier.rider.document` | Courier/RiderController → document |
| GET | `/courier/session/handoff/{token}` | `courier.portal.handoff` | Auth\PortalHandoffController (invokable) |
| GET | `/courier/status` | `courier.status` | Courier/RegistrationController → status |
| GET | `/courier/trips` | `courier.trips` | Courier/TruckDutyController → index |
| POST | `/courier/trips/duty` | `courier.trips.duty` | Courier/TruckDutyController → duty |
| GET | `/courier/trips/history` | `courier.trips.history` | Courier/TruckDutyController → history |
| GET | `/courier/trips/pay` | `courier.trips.pay` | Courier/TruckDutyController → pay |
| POST | `/courier/trips/ping` | `courier.trips.ping` | Courier/TruckDutyController → ping |
| POST | `/courier/trips/{trip}/accept` | `courier.trips.accept` | Courier/TruckDutyController → accept |
| POST | `/courier/trips/{trip}/arrived` | `courier.trips.arrived` | Courier/TruckDutyController → arrived |
| POST | `/courier/trips/{trip}/decline` | `courier.trips.decline` | Courier/TruckDutyController → decline |
| POST | `/courier/trips/{trip}/delay` | `courier.trips.delay` | Courier/TruckDutyController → delay |
| POST | `/courier/trips/{trip}/depart` | `courier.trips.depart` | Courier/TruckDutyController → depart |
| POST | `/courier/trips/{trip}/loading` | `courier.trips.loading` | Courier/TruckDutyController → startLoading |
| POST | `/courier/trips/{trip}/note` | `courier.trips.note` | Courier/TruckDutyController → note |
| POST | `/courier/trips/{trip}/scan` | `courier.trips.scan` | Courier/TruckDutyController → scan |

## Admin panel (/admin)

125 routes.

Controllers in this section: [ActivityLogController](../app/Http/Controllers/Admin/ActivityLogController.php), [AnalyticsController](../app/Http/Controllers/Admin/AnalyticsController.php), [CategoryController](../app/Http/Controllers/Admin/CategoryController.php), [ComplianceController](../app/Http/Controllers/Admin/ComplianceController.php), [CourierController](../app/Http/Controllers/Admin/CourierController.php), [CustomerController](../app/Http/Controllers/Admin/CustomerController.php), [DashboardController](../app/Http/Controllers/Admin/DashboardController.php), [GcashPaymentController](../app/Http/Controllers/Admin/GcashPaymentController.php), [HomeBannerController](../app/Http/Controllers/Admin/HomeBannerController.php), [ChatController](../app/Http/Controllers/Admin/Logistics/ChatController.php), [DashboardController](../app/Http/Controllers/Admin/Logistics/DashboardController.php), [DeliveryController](../app/Http/Controllers/Admin/Logistics/DeliveryController.php), [ParcelController](../app/Http/Controllers/Admin/Logistics/ParcelController.php), [PickupRequestController](../app/Http/Controllers/Admin/Logistics/PickupRequestController.php), [ReportController](../app/Http/Controllers/Admin/Logistics/ReportController.php), [RiderController](../app/Http/Controllers/Admin/Logistics/RiderController.php), [ShipmentController](../app/Http/Controllers/Admin/Logistics/ShipmentController.php), [NotificationController](../app/Http/Controllers/Admin/NotificationController.php), [OrderController](../app/Http/Controllers/Admin/OrderController.php), [ProductApprovalController](../app/Http/Controllers/Admin/ProductApprovalController.php), [ReportController](../app/Http/Controllers/Admin/ReportController.php), [ReviewController](../app/Http/Controllers/Admin/ReviewController.php), [RiderDirectoryController](../app/Http/Controllers/Admin/RiderDirectoryController.php), [SellerApplicationController](../app/Http/Controllers/Admin/SellerApplicationController.php), [SellerController](../app/Http/Controllers/Admin/SellerController.php), [SettingsController](../app/Http/Controllers/Admin/SettingsController.php), [SortingCenterApplicationController](../app/Http/Controllers/Admin/SortingCenterApplicationController.php), [SortingCenterDirectoryController](../app/Http/Controllers/Admin/SortingCenterDirectoryController.php), [UserController](../app/Http/Controllers/Admin/UserController.php), [VoucherController](../app/Http/Controllers/Admin/VoucherController.php), [AuthenticatedSessionController](../app/Http/Controllers/Auth/AuthenticatedSessionController.php), [PortalHandoffController](../app/Http/Controllers/Auth/PortalHandoffController.php), [MessageController](../app/Http/Controllers/Shared/MessageController.php), [ShipmentTrackingController](../app/Http/Controllers/Shared/ShipmentTrackingController.php).

| Method | URL | Name | Handled by |
|---|---|---|---|
| GET | `/admin` | `admin.dashboard` | Admin/DashboardController → index |
| GET | `/admin/activity` | `admin.activity.index` | Admin/ActivityLogController → index |
| GET | `/admin/analytics` | `admin.analytics.index` | Admin/AnalyticsController → index |
| GET | `/admin/banners` | `admin.banners.index` | Admin/HomeBannerController → index |
| POST | `/admin/banners` | `admin.banners.store` | Admin/HomeBannerController → store |
| GET | `/admin/banners/create` | `admin.banners.create` | Admin/HomeBannerController → create |
| POST | `/admin/banners/settings` | `admin.banners.settings` | Admin/HomeBannerController → settings |
| DELETE | `/admin/banners/{banner}` | `admin.banners.destroy` | Admin/HomeBannerController → destroy |
| PUT | `/admin/banners/{banner}` | `admin.banners.update` | Admin/HomeBannerController → update |
| GET | `/admin/banners/{banner}/edit` | `admin.banners.edit` | Admin/HomeBannerController → edit |
| PATCH | `/admin/banners/{banner}/toggle` | `admin.banners.toggle` | Admin/HomeBannerController → toggle |
| GET | `/admin/categories` | `admin.categories.index` | Admin/CategoryController → index |
| POST | `/admin/categories` | `admin.categories.store` | Admin/CategoryController → store |
| DELETE | `/admin/categories/{id}` | `admin.categories.destroy` | Admin/CategoryController → destroy |
| PUT | `/admin/categories/{id}` | `admin.categories.update` | Admin/CategoryController → update |
| POST | `/admin/chat/send/{type}/{id}` | `admin.chat.send` | Shared/MessageController → widgetSend |
| GET | `/admin/chat/thread/{type}/{id}` | `admin.chat.thread` | Shared/MessageController → widgetThread |
| GET | `/admin/chat/threads` | `admin.chat.threads` | Shared/MessageController → widgetThreads |
| GET | `/admin/compliance` | `admin.compliance.index` | Admin/ComplianceController → index |
| POST | `/admin/compliance` | `admin.compliance.store` | Admin/ComplianceController → store |
| POST | `/admin/compliance/strikes/{strike}/appeal` | `admin.compliance.strike-appeal` | Admin/ComplianceController → decideAppeal |
| GET | `/admin/compliance/{sellerId}` | `admin.compliance.show` | Admin/ComplianceController → show |
| POST | `/admin/compliance/{sellerId}/strikes-reviewed` | `admin.compliance.strikes-reviewed` | Admin/ComplianceController → reviewStrikes |
| GET | `/admin/couriers` | `admin.couriers.index` | Admin/CourierController → index |
| GET | `/admin/couriers/{courier}` | `admin.couriers.show` | Admin/CourierController → show |
| PATCH | `/admin/couriers/{courier}/approve` | `admin.couriers.approve` | Admin/CourierController → approve |
| PATCH | `/admin/couriers/{courier}/reject` | `admin.couriers.reject` | Admin/CourierController → reject |
| PATCH | `/admin/couriers/{courier}/suspend` | `admin.couriers.suspend` | Admin/CourierController → suspend |
| GET | `/admin/customers` | `admin.customers.index` | Admin/CustomerController → index |
| GET | `/admin/customers/{id}` | `admin.customers.show` | Admin/CustomerController → show |
| PATCH | `/admin/customers/{id}/approve` | `admin.customers.approve` | Admin/CustomerController → approve |
| PATCH | `/admin/customers/{id}/reject` | `admin.customers.reject` | Admin/CustomerController → reject |
| PATCH | `/admin/customers/{id}/restore` | `admin.customers.restore` | Admin/CustomerController → restore |
| PATCH | `/admin/customers/{id}/suspend` | `admin.customers.suspend` | Admin/CustomerController → suspend |
| GET | `/admin/customers/{id}/valid-id` | `admin.customers.validId` | Admin/CustomerController → validId |
| GET | `/admin/gcash-payments` | `admin.gcash.index` | Admin/GcashPaymentController → index |
| POST | `/admin/gcash-payments/account` | `admin.gcash.account` | Admin/GcashPaymentController → updateAccount |
| POST | `/admin/gcash-payments/refunds/{order}` | `admin.gcash.refunded` | Admin/GcashPaymentController → refunded |
| POST | `/admin/gcash-payments/{group}/approve` | `admin.gcash.approve` | Admin/GcashPaymentController → approve |
| POST | `/admin/gcash-payments/{group}/reject` | `admin.gcash.reject` | Admin/GcashPaymentController → reject |
| GET | `/admin/login` | `admin.login` | Auth/AuthenticatedSessionController → create |
| POST | `/admin/login` | `admin.login.attempt` | Auth/AuthenticatedSessionController → store |
| GET | `/admin/logistics` | `admin.logistics.dashboard` | Admin/Logistics/DashboardController → index |
| GET | `/admin/logistics/chat` | `admin.logistics.chat.index` | Admin/Logistics/ChatController → index |
| GET | `/admin/logistics/chat/poll` | `admin.logistics.chat.poll` | Admin/Logistics/ChatController → poll |
| POST | `/admin/logistics/chat/send` | `admin.logistics.chat.send` | Admin/Logistics/ChatController → send |
| GET | `/admin/logistics/deliveries/assign` | `admin.logistics.deliveries.assign-index` | Admin/Logistics/DeliveryController → assignmentIndex |
| GET | `/admin/logistics/deliveries/monitor` | `admin.logistics.deliveries.monitor` | Admin/Logistics/DeliveryController → monitor |
| POST | `/admin/logistics/deliveries/{delivery}/status` | `admin.logistics.deliveries.update-status` | Admin/Logistics/DeliveryController → updateStatus |
| POST | `/admin/logistics/deliveries/{parcel}/assign` | `admin.logistics.deliveries.assign` | Admin/Logistics/DeliveryController → assign |
| GET | `/admin/logistics/parcels` | `admin.logistics.parcels.index` | Admin/Logistics/ParcelController → index |
| GET | `/admin/logistics/parcels/sorting` | `admin.logistics.parcels.sorting` | Admin/Logistics/ParcelController → sorting |
| GET | `/admin/logistics/parcels/{parcel}` | `admin.logistics.parcels.show` | Admin/Logistics/ParcelController → show |
| POST | `/admin/logistics/parcels/{parcel}/mark-picked-up` | `admin.logistics.parcels.mark-picked-up` | Admin/Logistics/ParcelController → markPickedUp |
| POST | `/admin/logistics/parcels/{parcel}/sort` | `admin.logistics.parcels.sort` | Admin/Logistics/ParcelController → sort |
| GET | `/admin/logistics/pickup-requests` | `admin.logistics.pickup-requests.index` | Admin/Logistics/PickupRequestController → index |
| POST | `/admin/logistics/pickup-requests/{parcel}/approve` | `admin.logistics.pickup-requests.approve` | Admin/Logistics/PickupRequestController → approve |
| POST | `/admin/logistics/pickup-requests/{parcel}/reject` | `admin.logistics.pickup-requests.reject` | Admin/Logistics/PickupRequestController → reject |
| GET | `/admin/logistics/reports` | `admin.logistics.reports.index` | Admin/Logistics/ReportController → index |
| GET | `/admin/logistics/reports/export` | `admin.logistics.reports.export` | Admin/Logistics/ReportController → export |
| GET | `/admin/logistics/riders` | `admin.logistics.riders.index` | Admin/Logistics/RiderController → index |
| GET | `/admin/logistics/riders/{rider}` | `admin.logistics.riders.show` | Admin/Logistics/RiderController → show |
| POST | `/admin/logistics/riders/{rider}/approve` | `admin.logistics.riders.approve` | Admin/Logistics/RiderController → approve |
| POST | `/admin/logistics/riders/{rider}/disapprove` | `admin.logistics.riders.disapprove` | Admin/Logistics/RiderController → disapprove |
| POST | `/admin/logistics/riders/{rider}/toggle-active` | `admin.logistics.riders.toggle-active` | Admin/Logistics/RiderController → toggleActive |
| POST | `/admin/logistics/riders/{rider}/transport` | `admin.logistics.riders.transport` | Admin/Logistics/ShipmentController → toggleTransport |
| POST | `/admin/logistics/shipment-events/{event}/correct` | `admin.logistics.shipments.events.correct` | Admin/Logistics/ShipmentController → correctEvent |
| POST | `/admin/logistics/shipment-exceptions/{exception}/resolve` | `admin.logistics.shipments.exceptions.resolve` | Admin/Logistics/ShipmentController → resolveException |
| GET | `/admin/logistics/shipments` | `admin.logistics.shipments.index` | Admin/Logistics/ShipmentController → index |
| GET | `/admin/logistics/shipments/{parcel}` | `admin.logistics.shipments.show` | Admin/Logistics/ShipmentController → show |
| POST | `/admin/logout` | `admin.logout` | Auth/AuthenticatedSessionController → destroy |
| GET | `/admin/messages` | `admin.messages.inbox` | Shared/MessageController → inbox |
| GET | `/admin/messages/direct/{threadKey}` | `admin.messages.direct` | Shared/MessageController → directShow |
| POST | `/admin/messages/direct/{threadKey}` | `admin.messages.direct.store` | Shared/MessageController → directStore |
| POST | `/admin/messages/new` | `admin.messages.new` | Shared/MessageController → directNew |
| GET | `/admin/notifications` | `admin.notifications.index` | Admin/NotificationController → index |
| POST | `/admin/notifications/read-all` | `admin.notifications.read-all` | Admin/NotificationController → markAllRead |
| GET | `/admin/orders` | `admin.orders.index` | Admin/OrderController → index |
| GET | `/admin/orders/{id}` | `admin.orders.show` | Admin/OrderController → show |
| PATCH | `/admin/orders/{id}` | `admin.orders.update` | Admin/OrderController → update |
| POST | `/admin/orders/{id}/dispute` | `admin.orders.dispute` | Admin/OrderController → resolveDispute |
| GET | `/admin/orders/{order}/payment-proof` | `admin.orders.payment-proof` | Shared/ShipmentTrackingController → paymentProof |
| GET | `/admin/orders/{order}/proof-of-delivery` | `admin.shipments.pod` | Shared/ShipmentTrackingController → proofOfDelivery |
| GET | `/admin/product-approvals` | `admin.product-approvals.index` | Admin/ProductApprovalController → index |
| PATCH | `/admin/product-approvals/{product}/approve` | `admin.product-approvals.approve` | Admin/ProductApprovalController → approve |
| PATCH | `/admin/product-approvals/{product}/reject` | `admin.product-approvals.reject` | Admin/ProductApprovalController → reject |
| GET | `/admin/reports` | `admin.reports.index` | Admin/ReportController → index |
| GET | `/admin/reviews` | `admin.reviews.index` | Admin/ReviewController → index |
| DELETE | `/admin/reviews/{id}` | `admin.reviews.destroy` | Admin/ReviewController → destroy |
| GET | `/admin/riders` | `admin.riders.index` | Admin/RiderDirectoryController → index |
| GET | `/admin/sc-applications` | `admin.sc-applications.index` | Admin/SortingCenterApplicationController → index |
| GET | `/admin/sc-applications/{application}` | `admin.sc-applications.show` | Admin/SortingCenterApplicationController → show |
| PATCH | `/admin/sc-applications/{application}/approve` | `admin.sc-applications.approve` | Admin/SortingCenterApplicationController → approve |
| GET | `/admin/sc-applications/{application}/document/{type}` | `admin.sc-applications.document` | Admin/SortingCenterApplicationController → document |
| PATCH | `/admin/sc-applications/{application}/reject` | `admin.sc-applications.reject` | Admin/SortingCenterApplicationController → reject |
| GET | `/admin/seller-applications` | `admin.seller-applications.index` | Admin/SellerApplicationController → index |
| GET | `/admin/seller-applications/{sellerApplication}` | `admin.seller-applications.show` | Admin/SellerApplicationController → show |
| PATCH | `/admin/seller-applications/{sellerApplication}/approve` | `admin.seller-applications.approve` | Admin/SellerApplicationController → approve |
| PATCH | `/admin/seller-applications/{sellerApplication}/reject` | `admin.seller-applications.reject` | Admin/SellerApplicationController → reject |
| GET | `/admin/sellers` | `admin.sellers.index` | Admin/SellerController → index |
| GET | `/admin/sellers/{id}` | `admin.sellers.show` | Admin/SellerController → show |
| POST | `/admin/sellers/{id}/activate` | `admin.sellers.activate` | Admin/SellerController → activate |
| POST | `/admin/sellers/{id}/suspend` | `admin.sellers.suspend` | Admin/SellerController → suspend |
| GET | `/admin/session/handoff/{token}` | `admin.portal.handoff` | Auth\PortalHandoffController (invokable) |
| GET | `/admin/settings` | `admin.settings.index` | Admin/SettingsController → index |
| POST | `/admin/settings/announcements` | `admin.settings.announcements.store` | Admin/SettingsController → storeAnnouncement |
| DELETE | `/admin/settings/announcements/{id}` | `admin.settings.announcements.destroy` | Admin/SettingsController → destroyAnnouncement |
| PATCH | `/admin/settings/announcements/{id}/toggle` | `admin.settings.announcements.toggle` | Admin/SettingsController → toggleAnnouncement |
| GET | `/admin/settings/policies/{key}` | `admin.settings.policies.show` | Admin/SettingsController → showPolicy |
| PUT | `/admin/settings/policies/{key}` | `admin.settings.policies.update` | Admin/SettingsController → updatePolicy |
| GET | `/admin/sorting-centers` | `admin.sorting-centers.index` | Admin/SortingCenterDirectoryController → index |
| GET | `/admin/users` | `admin.users.index` | Admin/UserController → index |
| GET | `/admin/users/{id}` | `admin.users.show` | Admin/UserController → show |
| PATCH | `/admin/users/{id}/activate` | `admin.users.activate` | Admin/UserController → activate |
| PATCH | `/admin/users/{id}/assign-municipality` | `admin.users.assign-municipality` | Admin/UserController → assignMunicipality |
| PATCH | `/admin/users/{id}/deactivate` | `admin.users.deactivate` | Admin/UserController → deactivate |
| PATCH | `/admin/users/{id}/restore` | `admin.users.restore` | Admin/UserController → restore |
| PATCH | `/admin/users/{id}/suspend` | `admin.users.suspend` | Admin/UserController → suspend |
| GET | `/admin/vouchers` | `admin.vouchers.index` | Admin/VoucherController → index |
| POST | `/admin/vouchers` | `admin.vouchers.store` | Admin/VoucherController → store |
| GET | `/admin/vouchers/create` | `admin.vouchers.create` | Admin/VoucherController → create |
| DELETE | `/admin/vouchers/{voucher}` | `admin.vouchers.destroy` | Admin/VoucherController → destroy |
| PUT | `/admin/vouchers/{voucher}` | `admin.vouchers.update` | Admin/VoucherController → update |
| GET | `/admin/vouchers/{voucher}/edit` | `admin.vouchers.edit` | Admin/VoucherController → edit |
| PATCH | `/admin/vouchers/{voucher}/toggle` | `admin.vouchers.toggle` | Admin/VoucherController → toggle |

## Mobile app API (/api/v1)

297 routes.

Controllers in this section: [AuthController](../app/Http/Controllers/Api/V1/AuthController.php), [AccountController](../app/Http/Controllers/Api/V1/Buyer/AccountController.php), [AssistantController](../app/Http/Controllers/Api/V1/Buyer/AssistantController.php), [CatalogController](../app/Http/Controllers/Api/V1/Buyer/CatalogController.php), [ShoppingController](../app/Http/Controllers/Api/V1/Buyer/ShoppingController.php), [CourierTripsController](../app/Http/Controllers/Api/V1/Linehaul/CourierTripsController.php), [CompanyController](../app/Http/Controllers/Api/V1/Logistics/CompanyController.php), [HubController](../app/Http/Controllers/Api/V1/Logistics/HubController.php), [IssuesController](../app/Http/Controllers/Api/V1/Logistics/IssuesController.php), [RidersController](../app/Http/Controllers/Api/V1/Logistics/RidersController.php), [StaffController](../app/Http/Controllers/Api/V1/Logistics/StaffController.php), [TrackingController](../app/Http/Controllers/Api/V1/Logistics/TrackingController.php), [TripsController](../app/Http/Controllers/Api/V1/Logistics/TripsController.php), [MessagesController](../app/Http/Controllers/Api/V1/MessagesController.php), [OrderFilesController](../app/Http/Controllers/Api/V1/OrderFilesController.php), [PasswordCodeController](../app/Http/Controllers/Api/V1/PasswordCodeController.php), [PushDeviceController](../app/Http/Controllers/Api/V1/PushDeviceController.php), [CourierController](../app/Http/Controllers/Api/V1/Rider/CourierController.php), [RiderController](../app/Http/Controllers/Api/V1/Rider/RiderController.php), [MarketingController](../app/Http/Controllers/Api/V1/Seller/MarketingController.php), [OrdersController](../app/Http/Controllers/Api/V1/Seller/OrdersController.php), [PanelController](../app/Http/Controllers/Api/V1/Seller/PanelController.php), [ProductsController](../app/Http/Controllers/Api/V1/Seller/ProductsController.php), [AppDownloadController](../app/Http/Controllers/Storefront/AppDownloadController.php).

| Method | URL | Name | Handled by |
|---|---|---|---|
| POST | `/api/v1/account/password/cancel` | `api.v1.password.cancel` | Api/V1/PasswordCodeController → cancel |
| GET | `/api/v1/account/password/code` | `api.v1.password.code` | Api/V1/PasswordCodeController → status |
| POST | `/api/v1/account/password/resend` | `api.v1.password.resend` | Api/V1/PasswordCodeController → resend |
| POST | `/api/v1/account/password/verify` | `api.v1.password.verify` | Api/V1/PasswordCodeController → verify |
| GET | `/api/v1/addresses` | `api.v1.addresses` | Api/V1/Buyer/AccountController → addresses |
| POST | `/api/v1/addresses` | `api.v1.addresses.store` | Api/V1/Buyer/AccountController → addAddress |
| DELETE | `/api/v1/addresses/{address}` | `api.v1.addresses.destroy` | Api/V1/Buyer/AccountController → deleteAddress |
| PATCH | `/api/v1/addresses/{address}` | `api.v1.addresses.update` | Api/V1/Buyer/AccountController → updateAddress |
| PATCH | `/api/v1/addresses/{address}/default` | `api.v1.addresses.default` | Api/V1/Buyer/AccountController → defaultAddress |
| GET | `/api/v1/app/version` | `api.v1.app.version` | Storefront/AppDownloadController → version |
| POST | `/api/v1/assistant/actions/{token}` | `api.v1.assistant.confirm` | Api/V1/Buyer/AssistantController → confirm |
| POST | `/api/v1/assistant/ask` | `api.v1.assistant.ask` | Api/V1/Buyer/AssistantController → ask |
| GET | `/api/v1/assistant/history` | `api.v1.assistant.history` | Api/V1/Buyer/AssistantController → history |
| POST | `/api/v1/assistant/reset` | `api.v1.assistant.reset` | Api/V1/Buyer/AssistantController → reset |
| POST | `/api/v1/auth/forgot-password` | `api.v1.auth.forgot-password` | Api/V1/AuthController → forgotPassword |
| POST | `/api/v1/auth/google/exchange` | `api.v1.auth.google.exchange` | Api/V1/AuthController → googleExchange |
| POST | `/api/v1/auth/login` | `api.v1.auth.login` | Api/V1/AuthController → login |
| POST | `/api/v1/auth/logout` | `api.v1.auth.logout` | Api/V1/AuthController → logout |
| POST | `/api/v1/auth/otp/resend` | `api.v1.auth.otp.resend` | Api/V1/AuthController → resendOtp |
| POST | `/api/v1/auth/otp/verify` | `api.v1.auth.otp.verify` | Api/V1/AuthController → verifyOtp |
| POST | `/api/v1/auth/register` | `api.v1.auth.register` | Api/V1/AuthController → register |
| GET | `/api/v1/auth/register/options` | `api.v1.auth.register.options` | Api/V1/AuthController → registerOptions |
| POST | `/api/v1/become-courier` | `api.v1.become.courier` | Api/V1/Buyer/AccountController → becomeCourier |
| POST | `/api/v1/become-seller` | `api.v1.become.seller` | Api/V1/Buyer/AccountController → becomeSeller |
| GET | `/api/v1/cart` | `api.v1.cart` | Api/V1/Buyer/ShoppingController → cart |
| DELETE | `/api/v1/cart/item/{cartItemId}` | `api.v1.cart.destroy` | Api/V1/Buyer/ShoppingController → removeFromCart |
| PATCH | `/api/v1/cart/item/{cartItemId}` | `api.v1.cart.update` | Api/V1/Buyer/ShoppingController → updateCart |
| POST | `/api/v1/cart/{productId}` | `api.v1.cart.store` | Api/V1/Buyer/ShoppingController → addToCart |
| GET | `/api/v1/categories` | `api.v1.categories` | Api/V1/Buyer/CatalogController → categories |
| GET | `/api/v1/categories/{slug}` | `api.v1.categories.show` | Api/V1/Buyer/CatalogController → category |
| GET | `/api/v1/checkout` | `api.v1.checkout` | Api/V1/Buyer/ShoppingController → checkout |
| POST | `/api/v1/checkout` | `api.v1.checkout.store` | Api/V1/Buyer/ShoppingController → placeOrder |
| POST | `/api/v1/checkout/preview` | `api.v1.checkout.preview` | Api/V1/Buyer/ShoppingController → preview |
| GET | `/api/v1/courier/account` | `api.v1.courier.account` | Api/V1/Rider/CourierController → account |
| POST | `/api/v1/courier/account` | `api.v1.courier.account.update` | Api/V1/Rider/CourierController → updateAccount |
| PUT | `/api/v1/courier/account/password` | `api.v1.courier.account.password` | Api/V1/Rider/CourierController → password |
| GET | `/api/v1/courier/dashboard` | `api.v1.courier.dashboard` | Api/V1/Rider/CourierController → dashboard |
| GET | `/api/v1/courier/deliveries/{id}` | `api.v1.courier.deliveries.show` | Api/V1/Rider/CourierController → show |
| POST | `/api/v1/courier/deliveries/{id}/accept` | `api.v1.courier.deliveries.accept` | Api/V1/Rider/CourierController → accept |
| POST | `/api/v1/courier/deliveries/{id}/complete` | `api.v1.courier.deliveries.complete` | Api/V1/Rider/CourierController → complete |
| POST | `/api/v1/courier/deliveries/{id}/in-transit` | `api.v1.courier.deliveries.in-transit` | Api/V1/Rider/CourierController → inTransit |
| POST | `/api/v1/courier/deliveries/{id}/pickup` | `api.v1.courier.deliveries.pickup` | Api/V1/Rider/CourierController → pickup |
| GET | `/api/v1/courier/history` | `api.v1.courier.history` | Api/V1/Rider/CourierController → history |
| GET | `/api/v1/courier/profit` | `api.v1.courier.profit` | Api/V1/Rider/CourierController → profit |
| GET | `/api/v1/courier/status` | `api.v1.courier.status` | Api/V1/Rider/CourierController → status |
| DELETE | `/api/v1/devices` | `api.v1.devices.destroy` | Api/V1/PushDeviceController → destroy |
| POST | `/api/v1/devices` | `api.v1.devices.store` | Api/V1/PushDeviceController → store |
| POST | `/api/v1/flash-sales/{flashSale}/remind` | `api.v1.flash-sales.remind` | Api/V1/Buyer/AccountController → remindFlashSale |
| GET | `/api/v1/following` | `api.v1.following` | Api/V1/Buyer/AccountController → following |
| GET | `/api/v1/home` | `api.v1.home` | Api/V1/Buyer/CatalogController → home |
| GET | `/api/v1/linehaul/documents/{document}` | `api.v1.linehaul.document` | Api/V1/Linehaul/CourierTripsController → document |
| POST | `/api/v1/linehaul/duty` | `api.v1.linehaul.duty` | Api/V1/Linehaul/CourierTripsController → duty |
| GET | `/api/v1/linehaul/earnings` | `api.v1.linehaul.earnings` | Api/V1/Linehaul/CourierTripsController → earnings |
| GET | `/api/v1/linehaul/history` | `api.v1.linehaul.history` | Api/V1/Linehaul/CourierTripsController → history |
| GET | `/api/v1/linehaul/home` | `api.v1.linehaul.home` | Api/V1/Linehaul/CourierTripsController → home |
| POST | `/api/v1/linehaul/ping` | `api.v1.linehaul.ping` | Api/V1/Linehaul/CourierTripsController → ping |
| GET | `/api/v1/linehaul/profile` | `api.v1.linehaul.profile` | Api/V1/Linehaul/CourierTripsController → profile |
| POST | `/api/v1/linehaul/profile` | `api.v1.linehaul.profile.update` | Api/V1/Linehaul/CourierTripsController → updateProfile |
| GET | `/api/v1/linehaul/trips/{id}` | `api.v1.linehaul.trips.show` | Api/V1/Linehaul/CourierTripsController → trip |
| POST | `/api/v1/linehaul/trips/{id}/accept` | `api.v1.linehaul.trips.accept` | Api/V1/Linehaul/CourierTripsController → accept |
| POST | `/api/v1/linehaul/trips/{id}/arrived` | `api.v1.linehaul.trips.arrived` | Api/V1/Linehaul/CourierTripsController → arrived |
| POST | `/api/v1/linehaul/trips/{id}/decline` | `api.v1.linehaul.trips.decline` | Api/V1/Linehaul/CourierTripsController → decline |
| POST | `/api/v1/linehaul/trips/{id}/delay` | `api.v1.linehaul.trips.delay` | Api/V1/Linehaul/CourierTripsController → delay |
| POST | `/api/v1/linehaul/trips/{id}/depart` | `api.v1.linehaul.trips.depart` | Api/V1/Linehaul/CourierTripsController → depart |
| POST | `/api/v1/linehaul/trips/{id}/loading` | `api.v1.linehaul.trips.loading` | Api/V1/Linehaul/CourierTripsController → loading |
| POST | `/api/v1/linehaul/trips/{id}/note` | `api.v1.linehaul.trips.note` | Api/V1/Linehaul/CourierTripsController → note |
| POST | `/api/v1/linehaul/trips/{id}/scan` | `api.v1.linehaul.trips.scan` | Api/V1/Linehaul/CourierTripsController → scan |
| GET | `/api/v1/logistics/account` | `api.v1.logistics.account` | Api/V1/Logistics/CompanyController → account |
| PUT | `/api/v1/logistics/account` | `api.v1.logistics.account.update` | Api/V1/Logistics/CompanyController → updateAccount |
| GET | `/api/v1/logistics/activity` | `api.v1.logistics.activity` | Api/V1/Logistics/CompanyController → activity |
| GET | `/api/v1/logistics/areas` | `api.v1.logistics.areas` | Api/V1/Logistics/CompanyController → areas |
| POST | `/api/v1/logistics/areas` | `api.v1.logistics.areas.store` | Api/V1/Logistics/CompanyController → storeArea |
| DELETE | `/api/v1/logistics/areas/{area}` | `api.v1.logistics.areas.destroy` | Api/V1/Logistics/CompanyController → destroyArea |
| GET | `/api/v1/logistics/assignment` | `api.v1.logistics.assignment` | Api/V1/Logistics/HubController → assignment |
| POST | `/api/v1/logistics/assignment/{parcel}` | `api.v1.logistics.assignment.assign` | Api/V1/Logistics/HubController → assign |
| GET | `/api/v1/logistics/chat` | `api.v1.logistics.chat` | Api/V1/Logistics/CompanyController → chat |
| POST | `/api/v1/logistics/chat` | `api.v1.logistics.chat.send` | Api/V1/Logistics/CompanyController → send |
| GET | `/api/v1/logistics/chat/poll` | `api.v1.logistics.chat.poll` | Api/V1/Logistics/CompanyController → poll |
| GET | `/api/v1/logistics/commissions` | `api.v1.logistics.commissions` | Api/V1/Logistics/TrackingController → commissions |
| GET | `/api/v1/logistics/commissions/export` | `api.v1.logistics.commissions.export` | Api/V1/Logistics/TrackingController → exportCommissions |
| GET | `/api/v1/logistics/couriers` | `api.v1.logistics.couriers` | Api/V1/Logistics/RidersController → couriers |
| POST | `/api/v1/logistics/couriers` | `api.v1.logistics.couriers.store` | Api/V1/Logistics/StaffController → storeCourier |
| GET | `/api/v1/logistics/couriers/form` | `api.v1.logistics.couriers.form` | Api/V1/Logistics/StaffController → courierForm |
| GET | `/api/v1/logistics/couriers/{courier}` | `api.v1.logistics.couriers.show` | Api/V1/Logistics/RidersController → courier |
| POST | `/api/v1/logistics/couriers/{courier}/approve` | `api.v1.logistics.couriers.approve` | Api/V1/Logistics/RidersController → approveCourier |
| GET | `/api/v1/logistics/couriers/{courier}/documents/{document}` | `api.v1.logistics.couriers.document` | Api/V1/Logistics/RidersController → courierDocument |
| POST | `/api/v1/logistics/couriers/{courier}/reactivate` | `api.v1.logistics.couriers.reactivate` | Api/V1/Logistics/TripsController → reactivate |
| POST | `/api/v1/logistics/couriers/{courier}/reject` | `api.v1.logistics.couriers.reject` | Api/V1/Logistics/RidersController → rejectCourier |
| POST | `/api/v1/logistics/couriers/{courier}/resend-activation` | `api.v1.logistics.couriers.resend` | Api/V1/Logistics/StaffController → resendCourier |
| POST | `/api/v1/logistics/couriers/{courier}/suspend` | `api.v1.logistics.couriers.suspend` | Api/V1/Logistics/TripsController → suspend |
| POST | `/api/v1/logistics/couriers/{courier}/verify` | `api.v1.logistics.couriers.verify` | Api/V1/Logistics/TripsController → verify |
| GET | `/api/v1/logistics/dashboard` | `api.v1.logistics.dashboard` | Api/V1/Logistics/HubController → dashboard |
| POST | `/api/v1/logistics/deliveries/{delivery}/reschedule` | `api.v1.logistics.deliveries.reschedule` | Api/V1/Logistics/HubController → reschedule |
| POST | `/api/v1/logistics/deliveries/{delivery}/return` | `api.v1.logistics.deliveries.return` | Api/V1/Logistics/HubController → returnToSender |
| POST | `/api/v1/logistics/deliveries/{delivery}/status` | `api.v1.logistics.deliveries.status` | Api/V1/Logistics/HubController → overrideStatus |
| GET | `/api/v1/logistics/dispatch` | `api.v1.logistics.dispatch` | Api/V1/Logistics/RidersController → dispatch |
| POST | `/api/v1/logistics/dispatch/auto-all/{kind}` | `api.v1.logistics.dispatch.auto-all` | Api/V1/Logistics/RidersController → autoAssignAll |
| POST | `/api/v1/logistics/dispatch/bulk` | `api.v1.logistics.dispatch.bulk` | Api/V1/Logistics/RidersController → bulkAssign |
| POST | `/api/v1/logistics/dispatch/{parcel}/assign` | `api.v1.logistics.dispatch.assign` | Api/V1/Logistics/RidersController → manualAssign |
| POST | `/api/v1/logistics/dispatch/{parcel}/auto` | `api.v1.logistics.dispatch.auto` | Api/V1/Logistics/RidersController → autoAssign |
| POST | `/api/v1/logistics/dispatch/{parcel}/reassign` | `api.v1.logistics.dispatch.reassign` | Api/V1/Logistics/RidersController → reassign |
| GET | `/api/v1/logistics/failed-deliveries` | `api.v1.logistics.failed-deliveries` | Api/V1/Logistics/IssuesController → failed |
| GET | `/api/v1/logistics/hubs` | `api.v1.logistics.hubs` | Api/V1/Logistics/StaffController → hubs |
| POST | `/api/v1/logistics/hubs` | `api.v1.logistics.hubs.store` | Api/V1/Logistics/StaffController → storeHub |
| PUT | `/api/v1/logistics/hubs/{hub}` | `api.v1.logistics.hubs.update` | Api/V1/Logistics/StaffController → updateHub |
| GET | `/api/v1/logistics/incoming` | `api.v1.logistics.incoming` | Api/V1/Logistics/HubController → incoming |
| POST | `/api/v1/logistics/incoming/scan` | `api.v1.logistics.incoming.scan` | Api/V1/Logistics/HubController → scan |
| POST | `/api/v1/logistics/incoming/{parcel}/receive` | `api.v1.logistics.incoming.receive` | Api/V1/Logistics/HubController → receive |
| GET | `/api/v1/logistics/linehaul-couriers` | `api.v1.logistics.linehaul.couriers` | Api/V1/Logistics/TripsController → couriers |
| GET | `/api/v1/logistics/monitoring` | `api.v1.logistics.monitoring` | Api/V1/Logistics/HubController → monitoring |
| GET | `/api/v1/logistics/parcels/{parcel}` | `api.v1.logistics.parcels.show` | Api/V1/Logistics/HubController → parcel |
| POST | `/api/v1/logistics/parcels/{parcel}/exception` | `api.v1.logistics.parcels.exception` | Api/V1/Logistics/HubController → exception |
| GET | `/api/v1/logistics/pickup-requests` | `api.v1.logistics.pickup-requests` | Api/V1/Logistics/HubController → pickupRequests |
| POST | `/api/v1/logistics/pickup-requests/{parcel}/approve` | `api.v1.logistics.pickup-requests.approve` | Api/V1/Logistics/HubController → approvePickup |
| POST | `/api/v1/logistics/pickup-requests/{parcel}/reject` | `api.v1.logistics.pickup-requests.reject` | Api/V1/Logistics/HubController → rejectPickup |
| GET | `/api/v1/logistics/rates` | `api.v1.logistics.rates` | Api/V1/Logistics/CompanyController → rates |
| POST | `/api/v1/logistics/rates` | `api.v1.logistics.rates.update` | Api/V1/Logistics/CompanyController → updateRate |
| GET | `/api/v1/logistics/remittances` | `api.v1.logistics.remittances` | Api/V1/Logistics/RidersController → remittances |
| POST | `/api/v1/logistics/remittances/{remittance}/confirm` | `api.v1.logistics.remittances.confirm` | Api/V1/Logistics/RidersController → confirmRemittance |
| GET | `/api/v1/logistics/reports` | `api.v1.logistics.reports` | Api/V1/Logistics/CompanyController → reports |
| GET | `/api/v1/logistics/reports/export` | `api.v1.logistics.reports.export` | Api/V1/Logistics/CompanyController → exportReport |
| GET | `/api/v1/logistics/returns` | `api.v1.logistics.returns` | Api/V1/Logistics/IssuesController → returns |
| POST | `/api/v1/logistics/returns/{return}/assign` | `api.v1.logistics.returns.assign` | Api/V1/Logistics/IssuesController → returnAssign |
| POST | `/api/v1/logistics/returns/{return}/receive` | `api.v1.logistics.returns.receive` | Api/V1/Logistics/IssuesController → returnReceive |
| POST | `/api/v1/logistics/returns/{return}/to-seller` | `api.v1.logistics.returns.to-seller` | Api/V1/Logistics/IssuesController → returnToSeller |
| GET | `/api/v1/logistics/riders` | `api.v1.logistics.riders` | Api/V1/Logistics/RidersController → riders |
| POST | `/api/v1/logistics/riders` | `api.v1.logistics.riders.store` | Api/V1/Logistics/RidersController → storeRider |
| POST | `/api/v1/logistics/riders/accounts` | `api.v1.logistics.riders.accounts.store` | Api/V1/Logistics/StaffController → storeRider |
| GET | `/api/v1/logistics/riders/{rider}` | `api.v1.logistics.riders.show` | Api/V1/Logistics/RidersController → rider |
| POST | `/api/v1/logistics/riders/{rider}/approve` | `api.v1.logistics.riders.approve` | Api/V1/Logistics/RidersController → approve |
| GET | `/api/v1/logistics/riders/{rider}/documents/{document}` | `api.v1.logistics.riders.document` | Api/V1/Logistics/RidersController → document |
| POST | `/api/v1/logistics/riders/{rider}/reject` | `api.v1.logistics.riders.reject` | Api/V1/Logistics/RidersController → reject |
| POST | `/api/v1/logistics/riders/{rider}/resend-activation` | `api.v1.logistics.riders.resend` | Api/V1/Logistics/StaffController → resendRider |
| POST | `/api/v1/logistics/riders/{rider}/toggle` | `api.v1.logistics.riders.toggle` | Api/V1/Logistics/RidersController → toggle |
| POST | `/api/v1/logistics/riders/{rider}/transport` | `api.v1.logistics.riders.transport` | Api/V1/Logistics/TrackingController → toggleTransport |
| POST | `/api/v1/logistics/riders/{rider}/verify` | `api.v1.logistics.riders.verify` | Api/V1/Logistics/RidersController → verify |
| GET | `/api/v1/logistics/rts` | `api.v1.logistics.rts` | Api/V1/Logistics/HubController → rts |
| POST | `/api/v1/logistics/rts/{parcel}` | `api.v1.logistics.rts.assign` | Api/V1/Logistics/HubController → assignRts |
| GET | `/api/v1/logistics/shipments` | `api.v1.logistics.shipments` | Api/V1/Logistics/HubController → shipments |
| GET | `/api/v1/logistics/sorting` | `api.v1.logistics.sorting` | Api/V1/Logistics/HubController → sorting |
| POST | `/api/v1/logistics/sorting/{parcel}` | `api.v1.logistics.sorting.sort` | Api/V1/Logistics/HubController → sort |
| POST | `/api/v1/logistics/sorting/{parcel}/transfer` | `api.v1.logistics.sorting.transfer` | Api/V1/Logistics/IssuesController → sortTransfer |
| GET | `/api/v1/logistics/tracking` | `api.v1.logistics.tracking` | Api/V1/Logistics/TrackingController → index |
| GET | `/api/v1/logistics/tracking/{parcel}` | `api.v1.logistics.tracking.show` | Api/V1/Logistics/TrackingController → show |
| POST | `/api/v1/logistics/tracking/{parcel}/receive` | `api.v1.logistics.tracking.receive` | Api/V1/Logistics/TrackingController → receive |
| POST | `/api/v1/logistics/tracking/{parcel}/start-sorting` | `api.v1.logistics.tracking.start-sorting` | Api/V1/Logistics/TrackingController → startSorting |
| GET | `/api/v1/logistics/transfers` | `api.v1.logistics.transfers` | Api/V1/Logistics/HubController → transfers |
| POST | `/api/v1/logistics/transfers` | `api.v1.logistics.transfers.initiate` | Api/V1/Logistics/HubController → initiateTransfer |
| POST | `/api/v1/logistics/transfers/{transfer}/accept` | `api.v1.logistics.transfers.accept` | Api/V1/Logistics/HubController → acceptTransfer |
| POST | `/api/v1/logistics/transfers/{transfer}/cancel` | `api.v1.logistics.transfers.cancel` | Api/V1/Logistics/HubController → cancelTransfer |
| POST | `/api/v1/logistics/transfers/{transfer}/dispatch` | `api.v1.logistics.transfers.dispatch` | Api/V1/Logistics/HubController → dispatchTransfer |
| POST | `/api/v1/logistics/transfers/{transfer}/reject` | `api.v1.logistics.transfers.reject` | Api/V1/Logistics/HubController → rejectTransfer |
| GET | `/api/v1/logistics/trips` | `api.v1.logistics.trips` | Api/V1/Logistics/TripsController → index |
| POST | `/api/v1/logistics/trips` | `api.v1.logistics.trips.store` | Api/V1/Logistics/TripsController → store |
| POST | `/api/v1/logistics/trips/auto-assign` | `api.v1.logistics.trips.auto-all` | Api/V1/Logistics/TripsController → autoAssignAll |
| GET | `/api/v1/logistics/trips/form` | `api.v1.logistics.trips.form` | Api/V1/Logistics/TripsController → form |
| POST | `/api/v1/logistics/trips/receive` | `api.v1.logistics.trips.receive` | Api/V1/Logistics/TripsController → receive |
| GET | `/api/v1/logistics/trips/{trip}` | `api.v1.logistics.trips.show` | Api/V1/Logistics/TripsController → trip |
| POST | `/api/v1/logistics/trips/{trip}/assign` | `api.v1.logistics.trips.assign` | Api/V1/Logistics/TripsController → assign |
| POST | `/api/v1/logistics/trips/{trip}/auto` | `api.v1.logistics.trips.auto` | Api/V1/Logistics/TripsController → autoAdd |
| POST | `/api/v1/logistics/trips/{trip}/cancel` | `api.v1.logistics.trips.cancel` | Api/V1/Logistics/TripsController → cancel |
| POST | `/api/v1/logistics/trips/{trip}/close` | `api.v1.logistics.trips.close` | Api/V1/Logistics/TripsController → close |
| POST | `/api/v1/logistics/trips/{trip}/message` | `api.v1.logistics.trips.message` | Api/V1/Logistics/TripsController → message |
| POST | `/api/v1/logistics/trips/{trip}/parcels` | `api.v1.logistics.trips.parcels` | Api/V1/Logistics/TripsController → addParcels |
| POST | `/api/v1/logistics/trips/{trip}/parcels/{parcel}/damaged` | `api.v1.logistics.trips.parcels.damaged` | Api/V1/Logistics/TripsController → damaged |
| POST | `/api/v1/logistics/trips/{trip}/parcels/{parcel}/remove` | `api.v1.logistics.trips.parcels.remove` | Api/V1/Logistics/TripsController → removeParcel |
| POST | `/api/v1/logistics/trips/{trip}/receive-all` | `api.v1.logistics.trips.receive-all` | Api/V1/Logistics/TripsController → receiveAll |
| POST | `/api/v1/logistics/trips/{trip}/respond` | `api.v1.logistics.trips.respond` | Api/V1/Logistics/TripsController → respond |
| POST | `/api/v1/logistics/trips/{trip}/seal` | `api.v1.logistics.trips.seal` | Api/V1/Logistics/TripsController → seal |
| GET | `/api/v1/logistics/waybills` | `api.v1.logistics.waybills` | Api/V1/Logistics/TrackingController → waybills |
| POST | `/api/v1/logistics/zones` | `api.v1.logistics.zones.store` | Api/V1/Logistics/CompanyController → storeZone |
| GET | `/api/v1/me` | `api.v1.me` | Api/V1/AuthController → me |
| GET | `/api/v1/messages` | `api.v1.messages` | Api/V1/MessagesController → threads |
| POST | `/api/v1/messages/start` | `api.v1.messages.start` | Api/V1/MessagesController → start |
| GET | `/api/v1/messages/{type}/{id}` | `api.v1.messages.thread` | Api/V1/MessagesController → thread |
| POST | `/api/v1/messages/{type}/{id}` | `api.v1.messages.send` | Api/V1/MessagesController → send |
| GET | `/api/v1/notifications` | `api.v1.notifications` | Api/V1/Buyer/AccountController → notifications |
| POST | `/api/v1/notifications/read-all` | `api.v1.notifications.read-all` | Api/V1/Buyer/AccountController → readAllNotifications |
| POST | `/api/v1/notifications/{id}/open` | `api.v1.notifications.open` | Api/V1/Buyer/AccountController → openNotification |
| GET | `/api/v1/orders` | `api.v1.orders` | Api/V1/Buyer/ShoppingController → orders |
| GET | `/api/v1/orders/{id}` | `api.v1.orders.show` | Api/V1/Buyer/ShoppingController → order |
| POST | `/api/v1/orders/{id}/cancel` | `api.v1.orders.cancel` | Api/V1/Buyer/ShoppingController → cancel |
| POST | `/api/v1/orders/{id}/escalate` | `api.v1.orders.escalate` | Api/V1/Buyer/ShoppingController → escalate |
| POST | `/api/v1/orders/{id}/gcash-receipt` | `api.v1.orders.gcash-resubmit` | Api/V1/Buyer/ShoppingController → gcashResubmit |
| POST | `/api/v1/orders/{id}/pay` | `api.v1.orders.pay` | Api/V1/Buyer/ShoppingController → pay |
| GET | `/api/v1/orders/{id}/payment-proof` | `api.v1.orders.payment-proof` | Api/V1/OrderFilesController → paymentProof |
| GET | `/api/v1/orders/{id}/proof-of-delivery` | `api.v1.orders.pod` | Api/V1/OrderFilesController → proofOfDelivery |
| POST | `/api/v1/orders/{id}/received` | `api.v1.orders.received` | Api/V1/Buyer/ShoppingController → confirmReceived |
| POST | `/api/v1/orders/{id}/return` | `api.v1.orders.return` | Api/V1/Buyer/ShoppingController → requestReturn |
| GET | `/api/v1/policies/{key}` | `api.v1.policies.show` | Api/V1/Buyer/CatalogController → policy |
| GET | `/api/v1/products/{slug}` | `api.v1.products.show` | Api/V1/Buyer/CatalogController → product |
| GET | `/api/v1/profile` | `api.v1.profile` | Api/V1/Buyer/AccountController → profile |
| POST | `/api/v1/profile` | `api.v1.profile.update` | Api/V1/Buyer/AccountController → updateProfile |
| PATCH | `/api/v1/profile/account` | `api.v1.profile.account` | Api/V1/Buyer/AccountController → updateAccount |
| POST | `/api/v1/profile/contact/cancel` | `api.v1.profile.contact.cancel` | Api/V1/Buyer/AccountController → cancelContact |
| POST | `/api/v1/profile/contact/resend` | `api.v1.profile.contact.resend` | Api/V1/Buyer/AccountController → resendContact |
| POST | `/api/v1/profile/contact/verify` | `api.v1.profile.contact.verify` | Api/V1/Buyer/AccountController → verifyContact |
| POST | `/api/v1/profile/email` | `api.v1.profile.email` | Api/V1/Buyer/AccountController → changeEmail |
| PATCH | `/api/v1/profile/password` | `api.v1.profile.password` | Api/V1/Buyer/AccountController → changePassword |
| POST | `/api/v1/profile/phone` | `api.v1.profile.phone` | Api/V1/Buyer/AccountController → changePhone |
| POST | `/api/v1/reviews/item/{item}` | `api.v1.reviews.store` | Api/V1/Buyer/ShoppingController → review |
| POST | `/api/v1/reviews/{review}` | `api.v1.reviews.update` | Api/V1/Buyer/ShoppingController → editReview |
| GET | `/api/v1/rider/cod` | `api.v1.rider.cod` | Api/V1/Rider/RiderController → cod |
| POST | `/api/v1/rider/cod/remit` | `api.v1.rider.cod.remit` | Api/V1/Rider/RiderController → remit |
| GET | `/api/v1/rider/cod/remittances/{remittance}` | `api.v1.rider.cod.receipt` | Api/V1/Rider/RiderController → remittanceReceipt |
| GET | `/api/v1/rider/deliveries/{delivery}` | `api.v1.rider.deliveries.show` | Api/V1/Rider/RiderController → delivery |
| POST | `/api/v1/rider/deliveries/{delivery}/deliver` | `api.v1.rider.deliveries.deliver` | Api/V1/Rider/RiderController → deliver |
| POST | `/api/v1/rider/deliveries/{delivery}/fail` | `api.v1.rider.deliveries.fail` | Api/V1/Rider/RiderController → fail |
| POST | `/api/v1/rider/deliveries/{delivery}/start` | `api.v1.rider.deliveries.start` | Api/V1/Rider/RiderController → start |
| GET | `/api/v1/rider/documents/{document}` | `api.v1.rider.document` | Api/V1/Rider/RiderController → document |
| GET | `/api/v1/rider/earnings` | `api.v1.rider.earnings` | Api/V1/Rider/RiderController → earnings |
| GET | `/api/v1/rider/history` | `api.v1.rider.history` | Api/V1/Rider/RiderController → history |
| GET | `/api/v1/rider/jobs` | `api.v1.rider.jobs` | Api/V1/Rider/RiderController → jobs |
| POST | `/api/v1/rider/jobs/{kind}/{job}/acknowledge` | `api.v1.rider.jobs.acknowledge` | Api/V1/Rider/RiderController → acknowledge |
| POST | `/api/v1/rider/jobs/{kind}/{job}/decline` | `api.v1.rider.jobs.decline` | Api/V1/Rider/RiderController → declineJob |
| POST | `/api/v1/rider/offers/{offer}/accept` | `api.v1.rider.offers.accept` | Api/V1/Rider/RiderController → accept |
| POST | `/api/v1/rider/offers/{offer}/decline` | `api.v1.rider.offers.decline` | Api/V1/Rider/RiderController → decline |
| POST | `/api/v1/rider/online` | `api.v1.rider.online` | Api/V1/Rider/RiderController → online |
| GET | `/api/v1/rider/pickups/{pickup}` | `api.v1.rider.pickups.show` | Api/V1/Rider/RiderController → pickup |
| POST | `/api/v1/rider/pickups/{pickup}/arriving` | `api.v1.rider.pickups.arriving` | Api/V1/Rider/RiderController → arriving |
| POST | `/api/v1/rider/pickups/{pickup}/confirm` | `api.v1.rider.pickups.confirm` | Api/V1/Rider/RiderController → confirmPickup |
| GET | `/api/v1/rider/profile` | `api.v1.rider.profile` | Api/V1/Rider/RiderController → profile |
| POST | `/api/v1/rider/profile` | `api.v1.rider.profile.update` | Api/V1/Rider/RiderController → updateProfile |
| GET | `/api/v1/rider/return-jobs/{return}` | `api.v1.rider.return-jobs.show` | Api/V1/Rider/RiderController → returnJobShow |
| POST | `/api/v1/rider/return-jobs/{return}/accept` | `api.v1.rider.return-jobs.accept` | Api/V1/Rider/RiderController → returnJobAccept |
| POST | `/api/v1/rider/return-jobs/{return}/confirm` | `api.v1.rider.return-jobs.confirm` | Api/V1/Rider/RiderController → returnJobConfirm |
| POST | `/api/v1/rider/return-jobs/{return}/handed` | `api.v1.rider.return-jobs.handed` | Api/V1/Rider/RiderController → returnJobHanded |
| POST | `/api/v1/rider/returns/{parcel}/returned` | `api.v1.rider.returns.returned` | Api/V1/Rider/RiderController → returned |
| GET | `/api/v1/seller/account` | `api.v1.seller.account` | Api/V1/Seller/PanelController → account |
| POST | `/api/v1/seller/account` | `api.v1.seller.account.update` | Api/V1/Seller/PanelController → updateAccount |
| PUT | `/api/v1/seller/account/password` | `api.v1.seller.account.password` | Api/V1/Seller/PanelController → password |
| GET | `/api/v1/seller/commissions` | `api.v1.seller.commissions` | Api/V1/Seller/PanelController → commissions |
| GET | `/api/v1/seller/commissions/export` | `api.v1.seller.commissions.export` | Api/V1/Seller/PanelController → exportCommissions |
| GET | `/api/v1/seller/dashboard` | `api.v1.seller.dashboard` | Api/V1/Seller/PanelController → dashboard |
| GET | `/api/v1/seller/earnings` | `api.v1.seller.earnings` | Api/V1/Seller/PanelController → earnings |
| POST | `/api/v1/seller/earnings/payouts` | `api.v1.seller.earnings.payout` | Api/V1/Seller/PanelController → requestPayout |
| GET | `/api/v1/seller/flash-sales` | `api.v1.seller.flash-sales` | Api/V1/Seller/MarketingController → flashSales |
| POST | `/api/v1/seller/flash-sales` | `api.v1.seller.flash-sales.store` | Api/V1/Seller/MarketingController → storeFlashSale |
| DELETE | `/api/v1/seller/flash-sales/{flashSale}` | `api.v1.seller.flash-sales.destroy` | Api/V1/Seller/MarketingController → deleteFlashSale |
| GET | `/api/v1/seller/orders` | `api.v1.seller.orders` | Api/V1/Seller/OrdersController → index |
| GET | `/api/v1/seller/orders/{id}` | `api.v1.seller.orders.show` | Api/V1/Seller/OrdersController → show |
| POST | `/api/v1/seller/orders/{id}/accept` | `api.v1.seller.orders.accept` | Api/V1/Seller/OrdersController → accept |
| POST | `/api/v1/seller/orders/{id}/cancel` | `api.v1.seller.orders.cancel` | Api/V1/Seller/OrdersController → cancel |
| POST | `/api/v1/seller/orders/{id}/handed-over` | `api.v1.seller.orders.handed-over` | Api/V1/Seller/OrdersController → handedOver |
| POST | `/api/v1/seller/orders/{id}/handover` | `api.v1.seller.orders.handover` | Api/V1/Seller/OrdersController → handover |
| POST | `/api/v1/seller/orders/{id}/pack` | `api.v1.seller.orders.pack` | Api/V1/Seller/OrdersController → pack |
| GET | `/api/v1/seller/orders/{id}/payment-proof` | `api.v1.seller.orders.payment-proof` | Api/V1/OrderFilesController → paymentProof |
| GET | `/api/v1/seller/orders/{id}/proof-of-delivery` | `api.v1.seller.orders.pod` | Api/V1/OrderFilesController → proofOfDelivery |
| POST | `/api/v1/seller/orders/{id}/return` | `api.v1.seller.orders.return` | Api/V1/Seller/OrdersController → decideReturn |
| POST | `/api/v1/seller/orders/{id}/returned-received` | `api.v1.seller.orders.returned-received` | Api/V1/Seller/OrdersController → returnedReceived |
| GET | `/api/v1/seller/orders/{id}/waybill` | `api.v1.seller.orders.waybill` | Api/V1/Seller/OrdersController → waybill |
| GET | `/api/v1/seller/products` | `api.v1.seller.products` | Api/V1/Seller/ProductsController → index |
| POST | `/api/v1/seller/products` | `api.v1.seller.products.store` | Api/V1/Seller/ProductsController → store |
| POST | `/api/v1/seller/products/bulk` | `api.v1.seller.products.bulk` | Api/V1/Seller/ProductsController → bulk |
| GET | `/api/v1/seller/products/form` | `api.v1.seller.products.form` | Api/V1/Seller/ProductsController → form |
| DELETE | `/api/v1/seller/products/{product}` | `api.v1.seller.products.destroy` | Api/V1/Seller/ProductsController → destroy |
| GET | `/api/v1/seller/products/{product}` | `api.v1.seller.products.show` | Api/V1/Seller/ProductsController → show |
| POST | `/api/v1/seller/products/{product}` | `api.v1.seller.products.update` | Api/V1/Seller/ProductsController → update |
| PATCH | `/api/v1/seller/products/{product}/archive` | `api.v1.seller.products.archive` | Api/V1/Seller/ProductsController → archive |
| POST | `/api/v1/seller/products/{product}/duplicate` | `api.v1.seller.products.duplicate` | Api/V1/Seller/ProductsController → duplicate |
| PATCH | `/api/v1/seller/products/{product}/end-sale` | `api.v1.seller.products.end-sale` | Api/V1/Seller/ProductsController → endSale |
| PATCH | `/api/v1/seller/products/{product}/stock` | `api.v1.seller.products.stock` | Api/V1/Seller/ProductsController → stock |
| PATCH | `/api/v1/seller/products/{product}/unarchive` | `api.v1.seller.products.unarchive` | Api/V1/Seller/ProductsController → unarchive |
| GET | `/api/v1/seller/promotions` | `api.v1.seller.promotions` | Api/V1/Seller/MarketingController → promotions |
| POST | `/api/v1/seller/promotions` | `api.v1.seller.promotions.store` | Api/V1/Seller/MarketingController → storePromotion |
| DELETE | `/api/v1/seller/promotions/{promotion}` | `api.v1.seller.promotions.destroy` | Api/V1/Seller/MarketingController → deletePromotion |
| PATCH | `/api/v1/seller/promotions/{promotion}/toggle` | `api.v1.seller.promotions.toggle` | Api/V1/Seller/MarketingController → togglePromotion |
| GET | `/api/v1/seller/reports` | `api.v1.seller.reports` | Api/V1/Seller/PanelController → reports |
| GET | `/api/v1/seller/reports/export` | `api.v1.seller.reports.export` | Api/V1/Seller/PanelController → exportReport |
| GET | `/api/v1/seller/reviews` | `api.v1.seller.reviews` | Api/V1/Seller/PanelController → reviews |
| POST | `/api/v1/seller/reviews/{review}/reply` | `api.v1.seller.reviews.reply` | Api/V1/Seller/PanelController → reply |
| GET | `/api/v1/seller/status` | `api.v1.seller.status` | Api/V1/Seller/PanelController → status |
| POST | `/api/v1/seller/status/resubmit` | `api.v1.seller.status.resubmit` | Api/V1/Seller/PanelController → resubmit |
| GET | `/api/v1/seller/store-categories` | `api.v1.seller.store-categories` | Api/V1/Seller/MarketingController → storeCategories |
| POST | `/api/v1/seller/store-categories` | `api.v1.seller.store-categories.store` | Api/V1/Seller/MarketingController → storeCategory |
| POST | `/api/v1/seller/store-categories/reorder` | `api.v1.seller.store-categories.reorder` | Api/V1/Seller/MarketingController → reorderCategories |
| DELETE | `/api/v1/seller/store-categories/{storeCategory}` | `api.v1.seller.store-categories.destroy` | Api/V1/Seller/MarketingController → deleteCategory |
| PUT | `/api/v1/seller/store-categories/{storeCategory}` | `api.v1.seller.store-categories.update` | Api/V1/Seller/MarketingController → updateCategory |
| PATCH | `/api/v1/seller/store-categories/{storeCategory}/toggle` | `api.v1.seller.store-categories.toggle` | Api/V1/Seller/MarketingController → toggleCategory |
| GET | `/api/v1/seller/store-settings` | `api.v1.seller.store-settings` | Api/V1/Seller/PanelController → storeSettings |
| POST | `/api/v1/seller/store-settings` | `api.v1.seller.store-settings.update` | Api/V1/Seller/PanelController → updateStoreSettings |
| POST | `/api/v1/seller/violations/{violation}/acknowledge` | `api.v1.seller.violations.acknowledge` | Api/V1/Seller/PanelController → acknowledgeViolation |
| GET | `/api/v1/seller/vouchers` | `api.v1.seller.vouchers` | Api/V1/Seller/MarketingController → vouchers |
| POST | `/api/v1/seller/vouchers` | `api.v1.seller.vouchers.store` | Api/V1/Seller/MarketingController → storeVoucher |
| GET | `/api/v1/seller/vouchers/form/{voucher?}` | `api.v1.seller.vouchers.form` | Api/V1/Seller/MarketingController → voucherForm |
| DELETE | `/api/v1/seller/vouchers/{voucher}` | `api.v1.seller.vouchers.destroy` | Api/V1/Seller/MarketingController → deleteVoucher |
| PUT | `/api/v1/seller/vouchers/{voucher}` | `api.v1.seller.vouchers.update` | Api/V1/Seller/MarketingController → updateVoucher |
| PATCH | `/api/v1/seller/vouchers/{voucher}/toggle` | `api.v1.seller.vouchers.toggle` | Api/V1/Seller/MarketingController → toggleVoucher |
| GET | `/api/v1/shop` | `api.v1.shop` | Api/V1/Buyer/CatalogController → shop |
| GET | `/api/v1/stores/{sellerId}` | `api.v1.stores.show` | Api/V1/Buyer/CatalogController → store |
| POST | `/api/v1/stores/{sellerId}/follow` | `api.v1.stores.follow` | Api/V1/Buyer/AccountController → toggleFollow |
| GET | `/api/v1/vouchers` | `api.v1.vouchers` | Api/V1/Buyer/AccountController → vouchers |
| GET | `/api/v1/vouchers/center` | `api.v1.vouchers.center` | Api/V1/Buyer/AccountController → voucherCenter |
| POST | `/api/v1/vouchers/claim-all` | `api.v1.vouchers.claim-all` | Api/V1/Buyer/AccountController → claimAllVouchers |
| POST | `/api/v1/vouchers/{voucher}/claim` | `api.v1.vouchers.claim` | Api/V1/Buyer/AccountController → claimVoucher |
| GET | `/api/v1/wishlist` | `api.v1.wishlist` | Api/V1/Buyer/AccountController → wishlist |
| POST | `/api/v1/wishlist/{productId}` | `api.v1.wishlist.toggle` | Api/V1/Buyer/AccountController → toggleWish |

## Address lookups (/api/psgc and others)

8 routes.

Controllers in this section: [GeoController](../app/Http/Controllers/Shared/GeoController.php), [PsgcController](../app/Http/Controllers/Shared/PsgcController.php).

| Method | URL | Name | Handled by |
|---|---|---|---|
| GET | `/api/geo/reverse` | `geo.reverse` | Shared/GeoController → reverse |
| GET | `/api/geo/search` | `geo.search` | Shared/GeoController → search |
| GET | `/api/logistics-providers` |  | (inline in the routes file) |
| GET | `/api/psgc/municipalities/{code}/barangays` |  | Shared/PsgcController → barangays |
| GET | `/api/psgc/provinces` |  | Shared/PsgcController → provinces |
| GET | `/api/psgc/provinces/{code}/municipalities` |  | Shared/PsgcController → municipalities |
| GET | `/api/psgc/regions` |  | Shared/PsgcController → regions |
| GET | `/api/psgc/regions/{code}/provinces` |  | Shared/PsgcController → provincesByRegion |
