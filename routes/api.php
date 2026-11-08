<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Buyer;
use App\Http\Controllers\Api\V1\Seller;
use App\Http\Controllers\Api\V1\Rider;
use App\Http\Controllers\Api\V1\Logistics;
use App\Http\Controllers\Api\V1\Linehaul;
use App\Support\SortingCenters;
use Illuminate\Support\Facades\Route;

// Sorting centers (hubs) a new courier can choose on the sign-up form.
Route::get('/logistics-providers', fn () => response()->json(SortingCenters::forCouriers()));

/*
| Mobile app API (Ecommerce.app). Same rules as the web pages; the app signs in with a token
| (Authorization: Bearer …). Address pickers use the web's /api/psgc/* endpoints.
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/auth/login',        [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');
    Route::post('/auth/otp/verify',   [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('auth.otp.verify');
    Route::post('/auth/otp/resend',   [AuthController::class, 'resendOtp'])->middleware('throttle:5,1')->name('auth.otp.resend');
    Route::post('/auth/register',     [AuthController::class, 'register'])->middleware('throttle:10,1')->name('auth.register');
    Route::post('/auth/google/exchange', [AuthController::class, 'googleExchange'])->middleware('throttle:10,1')->name('auth.google.exchange');
    Route::get('/auth/register/options', [AuthController::class, 'registerOptions'])->name('auth.register.options');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:20,1')->name('auth.forgot-password');

    // The newest published app build (no sign-in: checked as the app opens).
    Route::get('/app/version', [\App\Http\Controllers\AppDownloadController::class, 'version'])->middleware('throttle:120,1')->name('app.version');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',           [AuthController::class, 'me'])->name('me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        // Push notifications: the phone's Firebase token (every role).
        Route::post('/devices',     [\App\Http\Controllers\Api\V1\PushDeviceController::class, 'store'])->middleware('throttle:30,1')->name('devices.store');
        Route::delete('/devices',   [\App\Http\Controllers\Api\V1\PushDeviceController::class, 'destroy'])->name('devices.destroy');
    });

    // Chat, for every role (the web decides who may message whom).
    Route::middleware(['auth:sanctum', 'api.session'])->group(function () {
        Route::get('/messages',                    [\App\Http\Controllers\Api\V1\MessagesController::class, 'threads'])->name('messages');
        Route::post('/messages/start',             [\App\Http\Controllers\Api\V1\MessagesController::class, 'start'])->name('messages.start');
        Route::get('/messages/{type}/{id}',        [\App\Http\Controllers\Api\V1\MessagesController::class, 'thread'])->whereIn('type', ['order', 'direct'])->name('messages.thread');
        Route::post('/messages/{type}/{id}',       [\App\Http\Controllers\Api\V1\MessagesController::class, 'send'])->whereIn('type', ['order', 'direct'])->middleware('throttle:30,1')->name('messages.send');

        // Notifications, for every role.
        Route::get('/notifications',               [Buyer\AccountController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{id}/open',    [Buyer\AccountController::class, 'openNotification'])->name('notifications.open');
        Route::post('/notifications/read-all',     [Buyer\AccountController::class, 'readAllNotifications'])->name('notifications.read-all');

        // A password change is confirmed with a code emailed to the account, for every role.
        Route::get('/account/password/code',       [\App\Http\Controllers\Api\V1\PasswordCodeController::class, 'status'])->name('password.code');
        Route::post('/account/password/verify',    [\App\Http\Controllers\Api\V1\PasswordCodeController::class, 'verify'])->middleware('throttle:10,1')->name('password.verify');
        Route::post('/account/password/resend',    [\App\Http\Controllers\Api\V1\PasswordCodeController::class, 'resend'])->middleware('throttle:3,1')->name('password.resend');
        Route::post('/account/password/cancel',    [\App\Http\Controllers\Api\V1\PasswordCodeController::class, 'cancel'])->name('password.cancel');
    });

    // ── Phase 3: Seller ────────────────────────────────────────
    // The application status page (any seller), then the seller panel (role:seller + seller.approved).
    Route::middleware(['auth:sanctum', 'api.session'])->prefix('seller')->name('seller.')->group(function () {
        Route::get('/status',            [Seller\PanelController::class, 'status'])->name('status');
        Route::post('/status/resubmit',  [Seller\PanelController::class, 'resubmit'])->name('status.resubmit');
    });
    Route::middleware(['auth:sanctum', 'api.seller', 'api.session'])->prefix('seller')->name('seller.')->group(function () {
        Route::get('/dashboard',                     [Seller\PanelController::class, 'dashboard'])->name('dashboard');
        Route::post('/violations/{violation}/acknowledge', [Seller\PanelController::class, 'acknowledgeViolation'])->whereNumber('violation')->name('violations.acknowledge');
        Route::get('/reports',                       [Seller\PanelController::class, 'reports'])->name('reports');
        Route::get('/reports/export',                [Seller\PanelController::class, 'exportReport'])->name('reports.export');
        Route::get('/commissions',                   [Seller\PanelController::class, 'commissions'])->name('commissions');
        Route::get('/commissions/export',            [Seller\PanelController::class, 'exportCommissions'])->name('commissions.export');
        Route::get('/earnings',                      [Seller\PanelController::class, 'earnings'])->name('earnings');
        Route::post('/earnings/payouts',             [Seller\PanelController::class, 'requestPayout'])->name('earnings.payout');
        Route::get('/reviews',                       [Seller\PanelController::class, 'reviews'])->name('reviews');
        Route::post('/reviews/{review}/reply',       [Seller\PanelController::class, 'reply'])->name('reviews.reply');
        Route::get('/account',                       [Seller\PanelController::class, 'account'])->name('account');
        Route::post('/account',                      [Seller\PanelController::class, 'updateAccount'])->name('account.update');   // POST: multipart avatar
        Route::put('/account/password',              [Seller\PanelController::class, 'password'])->name('account.password');
        Route::get('/store-settings',                [Seller\PanelController::class, 'storeSettings'])->name('store-settings');
        Route::post('/store-settings',               [Seller\PanelController::class, 'updateStoreSettings'])->name('store-settings.update'); // POST: multipart logo / banner

        Route::get('/products',                      [Seller\ProductsController::class, 'index'])->name('products');
        Route::get('/products/form',                 [Seller\ProductsController::class, 'form'])->name('products.form');
        Route::post('/products',                     [Seller\ProductsController::class, 'store'])->name('products.store');
        Route::post('/products/bulk',                [Seller\ProductsController::class, 'bulk'])->name('products.bulk');
        Route::get('/products/{product}',            [Seller\ProductsController::class, 'show'])->whereNumber('product')->name('products.show');
        Route::post('/products/{product}',           [Seller\ProductsController::class, 'update'])->whereNumber('product')->name('products.update');   // POST: multipart photos
        Route::delete('/products/{product}',         [Seller\ProductsController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/duplicate', [Seller\ProductsController::class, 'duplicate'])->name('products.duplicate');
        Route::patch('/products/{product}/end-sale', [Seller\ProductsController::class, 'endSale'])->name('products.end-sale');
        Route::patch('/products/{product}/archive',  [Seller\ProductsController::class, 'archive'])->name('products.archive');
        Route::patch('/products/{product}/unarchive', [Seller\ProductsController::class, 'unarchive'])->name('products.unarchive');
        Route::patch('/products/{product}/stock',    [Seller\ProductsController::class, 'stock'])->name('products.stock');

        Route::get('/orders',                        [Seller\OrdersController::class, 'index'])->name('orders');
        Route::get('/orders/{id}',                   [Seller\OrdersController::class, 'show'])->whereNumber('id')->name('orders.show');
        Route::post('/orders/{id}/accept',           [Seller\OrdersController::class, 'accept'])->name('orders.accept');
        Route::post('/orders/{id}/pack',             [Seller\OrdersController::class, 'pack'])->name('orders.pack');
        Route::post('/orders/{id}/cancel',           [Seller\OrdersController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{id}/handover',         [Seller\OrdersController::class, 'handover'])->name('orders.handover');
        Route::post('/orders/{id}/handed-over',      [Seller\OrdersController::class, 'handedOver'])->name('orders.handed-over');
        Route::post('/orders/{id}/return',           [Seller\OrdersController::class, 'decideReturn'])->name('orders.return');
        Route::post('/orders/{id}/returned-received', [Seller\OrdersController::class, 'returnedReceived'])->name('orders.returned-received');
        Route::get('/orders/{id}/waybill',           [Seller\OrdersController::class, 'waybill'])->name('orders.waybill');
        Route::get('/orders/{id}/payment-proof',     [\App\Http\Controllers\Api\V1\OrderFilesController::class, 'paymentProof'])->whereNumber('id')->name('orders.payment-proof');
        // The rider's proof-of-delivery photo, like the website's seller order page (same buyer / seller / admin check).
        Route::get('/orders/{id}/proof-of-delivery', [\App\Http\Controllers\Api\V1\OrderFilesController::class, 'proofOfDelivery'])->whereNumber('id')->name('orders.pod');

        Route::get('/vouchers',                      [Seller\MarketingController::class, 'vouchers'])->name('vouchers');
        Route::get('/vouchers/form/{voucher?}',      [Seller\MarketingController::class, 'voucherForm'])->name('vouchers.form');
        Route::post('/vouchers',                     [Seller\MarketingController::class, 'storeVoucher'])->name('vouchers.store');
        Route::put('/vouchers/{voucher}',            [Seller\MarketingController::class, 'updateVoucher'])->name('vouchers.update');
        Route::patch('/vouchers/{voucher}/toggle',   [Seller\MarketingController::class, 'toggleVoucher'])->name('vouchers.toggle');
        Route::delete('/vouchers/{voucher}',         [Seller\MarketingController::class, 'deleteVoucher'])->name('vouchers.destroy');
        Route::get('/flash-sales',                   [Seller\MarketingController::class, 'flashSales'])->name('flash-sales');
        Route::post('/flash-sales',                  [Seller\MarketingController::class, 'storeFlashSale'])->name('flash-sales.store');
        Route::delete('/flash-sales/{flashSale}',    [Seller\MarketingController::class, 'deleteFlashSale'])->name('flash-sales.destroy');
        Route::get('/promotions',                    [Seller\MarketingController::class, 'promotions'])->name('promotions');
        Route::post('/promotions',                   [Seller\MarketingController::class, 'storePromotion'])->name('promotions.store');
        Route::patch('/promotions/{promotion}/toggle', [Seller\MarketingController::class, 'togglePromotion'])->name('promotions.toggle');
        Route::delete('/promotions/{promotion}',     [Seller\MarketingController::class, 'deletePromotion'])->name('promotions.destroy');
        Route::get('/store-categories',              [Seller\MarketingController::class, 'storeCategories'])->name('store-categories');
        Route::post('/store-categories',             [Seller\MarketingController::class, 'storeCategory'])->name('store-categories.store');
        Route::post('/store-categories/reorder',     [Seller\MarketingController::class, 'reorderCategories'])->name('store-categories.reorder');
        Route::put('/store-categories/{storeCategory}', [Seller\MarketingController::class, 'updateCategory'])->name('store-categories.update');
        Route::patch('/store-categories/{storeCategory}/toggle', [Seller\MarketingController::class, 'toggleCategory'])->name('store-categories.toggle');
        Route::delete('/store-categories/{storeCategory}', [Seller\MarketingController::class, 'deleteCategory'])->name('store-categories.destroy');
    });

    // ── Phase 4: Rider / courier ──────────────────────────────────
    // The courier application status (also for an applicant still waiting), then the courier portal (role:courier).
    Route::middleware(['auth:sanctum', 'api.session'])->prefix('courier')->name('courier.')->group(function () {
        Route::get('/status', [Rider\CourierController::class, 'status'])->name('status');
    });
    // A courier of a logistics company: duty, hub-to-hub trips, history, earnings and profile.
    Route::middleware(['auth:sanctum', 'api.courier', 'api.session'])->prefix('linehaul')->name('linehaul.')->group(function () {
        Route::get('/home',                    [Linehaul\CourierTripsController::class, 'home'])->name('home');
        Route::post('/duty',                   [Linehaul\CourierTripsController::class, 'duty'])->name('duty');
        Route::post('/ping',                   [Linehaul\CourierTripsController::class, 'ping'])->name('ping');
        Route::get('/trips/{id}',              [Linehaul\CourierTripsController::class, 'trip'])->whereNumber('id')->name('trips.show');
        Route::post('/trips/{id}/accept',      [Linehaul\CourierTripsController::class, 'accept'])->whereNumber('id')->name('trips.accept');
        Route::post('/trips/{id}/decline',     [Linehaul\CourierTripsController::class, 'decline'])->whereNumber('id')->name('trips.decline');
        Route::post('/trips/{id}/loading',     [Linehaul\CourierTripsController::class, 'loading'])->whereNumber('id')->name('trips.loading');
        Route::post('/trips/{id}/scan',        [Linehaul\CourierTripsController::class, 'scan'])->whereNumber('id')->name('trips.scan');
        Route::post('/trips/{id}/depart',      [Linehaul\CourierTripsController::class, 'depart'])->whereNumber('id')->name('trips.depart');
        Route::post('/trips/{id}/delay',       [Linehaul\CourierTripsController::class, 'delay'])->whereNumber('id')->name('trips.delay');   // POST: multipart photo
        Route::post('/trips/{id}/arrived',     [Linehaul\CourierTripsController::class, 'arrived'])->whereNumber('id')->name('trips.arrived');
        Route::post('/trips/{id}/note',        [Linehaul\CourierTripsController::class, 'note'])->whereNumber('id')->name('trips.note');
        Route::get('/history',                 [Linehaul\CourierTripsController::class, 'history'])->name('history');
        Route::get('/earnings',                [Linehaul\CourierTripsController::class, 'earnings'])->name('earnings');
        Route::get('/profile',                 [Linehaul\CourierTripsController::class, 'profile'])->name('profile');
        Route::post('/profile',                [Linehaul\CourierTripsController::class, 'updateProfile'])->name('profile.update');   // POST: multipart files
        Route::get('/documents/{document}',    [Linehaul\CourierTripsController::class, 'document'])->whereIn('document', ['license', 'or-cr', 'nbi'])->name('document');
    });

    Route::middleware(['auth:sanctum', 'api.courier', 'api.session'])->group(function () {
        // A rider of a logistics company (Hub Jobs).
        Route::prefix('rider')->name('rider.')->group(function () {
            Route::get('/jobs',                            [Rider\RiderController::class, 'jobs'])->name('jobs');
            Route::post('/online',                         [Rider\RiderController::class, 'online'])->name('online');
            Route::post('/offers/{offer}/accept',          [Rider\RiderController::class, 'accept'])->whereNumber('offer')->name('offers.accept');
            Route::post('/offers/{offer}/decline',         [Rider\RiderController::class, 'decline'])->whereNumber('offer')->name('offers.decline');
            Route::get('/pickups/{pickup}',                [Rider\RiderController::class, 'pickup'])->whereNumber('pickup')->name('pickups.show');
            Route::post('/pickups/{pickup}/confirm',       [Rider\RiderController::class, 'confirmPickup'])->whereNumber('pickup')->name('pickups.confirm');
            Route::get('/deliveries/{delivery}',           [Rider\RiderController::class, 'delivery'])->whereNumber('delivery')->name('deliveries.show');
            Route::post('/deliveries/{delivery}/start',    [Rider\RiderController::class, 'start'])->whereNumber('delivery')->name('deliveries.start');
            Route::post('/deliveries/{delivery}/deliver',  [Rider\RiderController::class, 'deliver'])->whereNumber('delivery')->name('deliveries.deliver');
            Route::post('/deliveries/{delivery}/fail',     [Rider\RiderController::class, 'fail'])->whereNumber('delivery')->name('deliveries.fail');
            Route::post('/returns/{parcel}/returned',      [Rider\RiderController::class, 'returned'])->whereNumber('parcel')->name('returns.returned');
            Route::get('/profile',                         [Rider\RiderController::class, 'profile'])->name('profile');
            Route::post('/profile',                        [Rider\RiderController::class, 'updateProfile'])->name('profile.update');   // POST: multipart files
            Route::get('/documents/{document}',            [Rider\RiderController::class, 'document'])->whereIn('document', ['license', 'or-cr'])->name('document');
            Route::get('/history',                         [Rider\RiderController::class, 'history'])->name('history');
            Route::get('/earnings',                        [Rider\RiderController::class, 'earnings'])->name('earnings');
            Route::get('/cod',                             [Rider\RiderController::class, 'cod'])->name('cod');
            Route::post('/cod/remit',                      [Rider\RiderController::class, 'remit'])->name('cod.remit');
            Route::get('/cod/remittances/{remittance}',    [Rider\RiderController::class, 'remittanceReceipt'])->whereNumber('remittance')->name('cod.receipt');
            Route::post('/jobs/{kind}/{job}/acknowledge',  [Rider\RiderController::class, 'acknowledge'])->whereIn('kind', ['pickup', 'delivery'])->whereNumber('job')->name('jobs.acknowledge');
            Route::post('/jobs/{kind}/{job}/decline',      [Rider\RiderController::class, 'declineJob'])->whereIn('kind', ['pickup', 'delivery'])->whereNumber('job')->name('jobs.decline');
            Route::post('/pickups/{pickup}/arriving',      [Rider\RiderController::class, 'arriving'])->whereNumber('pickup')->name('pickups.arriving');
        });

        // A direct (freelance) courier.
        Route::prefix('courier')->name('courier.')->group(function () {
            Route::get('/dashboard',                  [Rider\CourierController::class, 'dashboard'])->name('dashboard');
            Route::get('/deliveries/{id}',            [Rider\CourierController::class, 'show'])->whereNumber('id')->name('deliveries.show');
            Route::post('/deliveries/{id}/accept',    [Rider\CourierController::class, 'accept'])->whereNumber('id')->name('deliveries.accept');
            Route::post('/deliveries/{id}/pickup',    [Rider\CourierController::class, 'pickup'])->whereNumber('id')->name('deliveries.pickup');
            Route::post('/deliveries/{id}/in-transit', [Rider\CourierController::class, 'inTransit'])->whereNumber('id')->name('deliveries.in-transit');
            Route::post('/deliveries/{id}/complete',  [Rider\CourierController::class, 'complete'])->whereNumber('id')->name('deliveries.complete');
            Route::get('/history',                    [Rider\CourierController::class, 'history'])->name('history');
            Route::get('/profit',                     [Rider\CourierController::class, 'profit'])->name('profit');
            Route::get('/account',                    [Rider\CourierController::class, 'account'])->name('account');
            Route::post('/account',                   [Rider\CourierController::class, 'updateAccount'])->name('account.update');   // POST: multipart avatar
            Route::put('/account/password',           [Rider\CourierController::class, 'password'])->name('account.password');
        });
    });

    // ── Phase 5: Logistics (sorting center) ─────────────────────
    Route::middleware(['auth:sanctum', 'api.logistics', 'api.session'])->prefix('logistics')->name('logistics.')->group(function () {
        // Parcels through the hub
        Route::get('/dashboard',                         [Logistics\HubController::class, 'dashboard'])->name('dashboard');
        Route::get('/shipments',                         [Logistics\HubController::class, 'shipments'])->name('shipments');
        Route::get('/parcels/{parcel}',                  [Logistics\HubController::class, 'parcel'])->whereNumber('parcel')->name('parcels.show');
        Route::post('/parcels/{parcel}/exception',       [Logistics\HubController::class, 'exception'])->whereNumber('parcel')->name('parcels.exception');   // multipart photo
        Route::get('/pickup-requests',                   [Logistics\HubController::class, 'pickupRequests'])->name('pickup-requests');
        Route::post('/pickup-requests/{parcel}/approve', [Logistics\HubController::class, 'approvePickup'])->whereNumber('parcel')->name('pickup-requests.approve');
        Route::post('/pickup-requests/{parcel}/reject',  [Logistics\HubController::class, 'rejectPickup'])->whereNumber('parcel')->name('pickup-requests.reject');
        Route::get('/incoming',                          [Logistics\HubController::class, 'incoming'])->name('incoming');
        Route::post('/incoming/scan',                    [Logistics\HubController::class, 'scan'])->name('incoming.scan');
        // The website's Shipment Tracking board, Commissions and printable waybills.
        Route::get('/tracking',                          [Logistics\TrackingController::class, 'index'])->name('tracking');
        Route::get('/tracking/{parcel}',                 [Logistics\TrackingController::class, 'show'])->whereNumber('parcel')->name('tracking.show');
        Route::post('/tracking/{parcel}/receive',        [Logistics\TrackingController::class, 'receive'])->whereNumber('parcel')->name('tracking.receive');
        Route::post('/tracking/{parcel}/start-sorting',  [Logistics\TrackingController::class, 'startSorting'])->whereNumber('parcel')->name('tracking.start-sorting');
        Route::post('/tracking/{parcel}/exception',      [Logistics\TrackingController::class, 'exception'])->whereNumber('parcel')->name('tracking.exception');
        Route::post('/riders/{rider}/transport',         [Logistics\TrackingController::class, 'toggleTransport'])->name('riders.transport');
        Route::get('/commissions',                       [Logistics\TrackingController::class, 'commissions'])->name('commissions');
        Route::get('/commissions/export',                [Logistics\TrackingController::class, 'exportCommissions'])->name('commissions.export');
        Route::get('/waybills',                          [Logistics\TrackingController::class, 'waybills'])->name('waybills');
        Route::post('/incoming/{parcel}/receive',        [Logistics\HubController::class, 'receive'])->whereNumber('parcel')->name('incoming.receive');
        Route::get('/sorting',                           [Logistics\HubController::class, 'sorting'])->name('sorting');
        Route::post('/sorting/{parcel}',                 [Logistics\HubController::class, 'sort'])->whereNumber('parcel')->name('sorting.sort');
        Route::get('/assignment',                        [Logistics\HubController::class, 'assignment'])->name('assignment');
        Route::post('/assignment/{parcel}',              [Logistics\HubController::class, 'assign'])->whereNumber('parcel')->name('assignment.assign');
        Route::get('/monitoring',                        [Logistics\HubController::class, 'monitoring'])->name('monitoring');
        Route::post('/deliveries/{delivery}/status',     [Logistics\HubController::class, 'overrideStatus'])->whereNumber('delivery')->name('deliveries.status');
        Route::post('/deliveries/{delivery}/reschedule', [Logistics\HubController::class, 'reschedule'])->whereNumber('delivery')->name('deliveries.reschedule');
        Route::post('/deliveries/{delivery}/return',     [Logistics\HubController::class, 'returnToSender'])->whereNumber('delivery')->name('deliveries.return');
        Route::get('/rts',                               [Logistics\HubController::class, 'rts'])->name('rts');
        Route::post('/rts/{parcel}',                     [Logistics\HubController::class, 'assignRts'])->whereNumber('parcel')->name('rts.assign');
        Route::get('/transfers',                         [Logistics\HubController::class, 'transfers'])->name('transfers');
        Route::post('/transfers',                        [Logistics\HubController::class, 'initiateTransfer'])->name('transfers.initiate');
        Route::post('/transfers/{transfer}/accept',      [Logistics\HubController::class, 'acceptTransfer'])->name('transfers.accept');
        Route::post('/transfers/{transfer}/reject',      [Logistics\HubController::class, 'rejectTransfer'])->name('transfers.reject');
        Route::post('/transfers/{transfer}/cancel',      [Logistics\HubController::class, 'cancelTransfer'])->name('transfers.cancel');
        Route::post('/transfers/{transfer}/dispatch',    [Logistics\HubController::class, 'dispatchTransfer'])->name('transfers.dispatch');

        // Riders, dispatch, COD, courier applications
        Route::get('/dispatch',                          [Logistics\RidersController::class, 'dispatch'])->name('dispatch');
        Route::post('/dispatch/bulk',                    [Logistics\RidersController::class, 'bulkAssign'])->name('dispatch.bulk');
        Route::post('/dispatch/auto-all/{kind}',         [Logistics\RidersController::class, 'autoAssignAll'])->whereIn('kind', ['pickup', 'delivery'])->name('dispatch.auto-all');
        Route::post('/dispatch/{parcel}/auto',           [Logistics\RidersController::class, 'autoAssign'])->whereNumber('parcel')->name('dispatch.auto');
        Route::post('/dispatch/{parcel}/assign',         [Logistics\RidersController::class, 'manualAssign'])->whereNumber('parcel')->name('dispatch.assign');
        Route::post('/dispatch/{parcel}/reassign',       [Logistics\RidersController::class, 'reassign'])->whereNumber('parcel')->name('dispatch.reassign');
        Route::get('/riders',                            [Logistics\RidersController::class, 'riders'])->name('riders');
        Route::post('/riders',                           [Logistics\RidersController::class, 'storeRider'])->name('riders.store');
        Route::get('/riders/{rider}',                    [Logistics\RidersController::class, 'rider'])->name('riders.show');
        Route::post('/riders/{rider}/approve',           [Logistics\RidersController::class, 'approve'])->name('riders.approve');
        Route::post('/riders/{rider}/reject',            [Logistics\RidersController::class, 'reject'])->name('riders.reject');
        Route::post('/riders/{rider}/toggle',            [Logistics\RidersController::class, 'toggle'])->name('riders.toggle');
        Route::post('/riders/{rider}/verify',            [Logistics\RidersController::class, 'verify'])->name('riders.verify');
        Route::get('/riders/{rider}/documents/{document}', [Logistics\RidersController::class, 'document'])->whereIn('document', ['license', 'or-cr'])->name('riders.document');
        Route::get('/remittances',                       [Logistics\RidersController::class, 'remittances'])->name('remittances');
        Route::post('/remittances/{remittance}/confirm', [Logistics\RidersController::class, 'confirmRemittance'])->name('remittances.confirm');
        Route::get('/couriers',                          [Logistics\RidersController::class, 'couriers'])->name('couriers');
        Route::get('/couriers/{courier}',                [Logistics\RidersController::class, 'courier'])->whereNumber('courier')->name('couriers.show');
        Route::post('/couriers/{courier}/approve',       [Logistics\RidersController::class, 'approveCourier'])->name('couriers.approve');
        Route::post('/couriers/{courier}/reject',        [Logistics\RidersController::class, 'rejectCourier'])->name('couriers.reject');
        Route::get('/couriers/{courier}/documents/{document}', [Logistics\RidersController::class, 'courierDocument'])->whereIn('document', ['license', 'or-cr', 'nbi'])->name('couriers.document');
        // Riders and couriers with a login of their own (Pending Activation until they set a password), and the hubs.
        Route::post('/riders/accounts',                  [Logistics\StaffController::class, 'storeRider'])->name('riders.accounts.store');
        Route::post('/riders/{rider}/resend-activation', [Logistics\StaffController::class, 'resendRider'])->name('riders.resend');
        Route::get('/couriers/form',                     [Logistics\StaffController::class, 'courierForm'])->name('couriers.form');
        Route::post('/couriers',                         [Logistics\StaffController::class, 'storeCourier'])->name('couriers.store');
        Route::post('/couriers/{courier}/resend-activation', [Logistics\StaffController::class, 'resendCourier'])->name('couriers.resend');
        Route::get('/hubs',                              [Logistics\StaffController::class, 'hubs'])->name('hubs');
        Route::post('/hubs',                             [Logistics\StaffController::class, 'storeHub'])->name('hubs.store');
        Route::put('/hubs/{hub}',                        [Logistics\StaffController::class, 'updateHub'])->name('hubs.update');
        // Hub-to-hub trips and the couriers' duty board.
        Route::get('/trips',                             [Logistics\TripsController::class, 'index'])->name('trips');
        Route::get('/trips/form',                        [Logistics\TripsController::class, 'form'])->name('trips.form');
        Route::post('/trips',                            [Logistics\TripsController::class, 'store'])->name('trips.store');
        Route::post('/trips/receive',                    [Logistics\TripsController::class, 'receive'])->name('trips.receive');
        Route::post('/trips/auto-assign',                [Logistics\TripsController::class, 'autoAssignAll'])->name('trips.auto-all');
        Route::get('/trips/{trip}',                      [Logistics\TripsController::class, 'trip'])->whereNumber('trip')->name('trips.show');
        Route::post('/trips/{trip}/assign',              [Logistics\TripsController::class, 'assign'])->whereNumber('trip')->name('trips.assign');
        Route::post('/trips/{trip}/parcels',             [Logistics\TripsController::class, 'addParcels'])->whereNumber('trip')->name('trips.parcels');
        Route::post('/trips/{trip}/auto',                [Logistics\TripsController::class, 'autoAdd'])->whereNumber('trip')->name('trips.auto');
        Route::post('/trips/{trip}/respond',             [Logistics\TripsController::class, 'respond'])->whereNumber('trip')->name('trips.respond');
        Route::post('/trips/{trip}/message',             [Logistics\TripsController::class, 'message'])->whereNumber('trip')->name('trips.message');
        Route::post('/trips/{trip}/receive-all',         [Logistics\TripsController::class, 'receiveAll'])->whereNumber('trip')->name('trips.receive-all');
        Route::post('/trips/{trip}/cancel',              [Logistics\TripsController::class, 'cancel'])->whereNumber('trip')->name('trips.cancel');
        Route::post('/trips/{trip}/seal',                [Logistics\TripsController::class, 'seal'])->whereNumber('trip')->name('trips.seal');
        Route::post('/trips/{trip}/close',               [Logistics\TripsController::class, 'close'])->whereNumber('trip')->name('trips.close');
        Route::post('/trips/{trip}/parcels/{parcel}/remove',  [Logistics\TripsController::class, 'removeParcel'])->whereNumber('trip')->name('trips.parcels.remove');
        Route::post('/trips/{trip}/parcels/{parcel}/damaged', [Logistics\TripsController::class, 'damaged'])->whereNumber('trip')->name('trips.parcels.damaged');   // POST: multipart photo
        Route::get('/linehaul-couriers',                 [Logistics\TripsController::class, 'couriers'])->name('linehaul.couriers');
        Route::post('/couriers/{courier}/suspend',       [Logistics\TripsController::class, 'suspend'])->name('couriers.suspend');
        Route::post('/couriers/{courier}/reactivate',    [Logistics\TripsController::class, 'reactivate'])->name('couriers.reactivate');
        Route::post('/couriers/{courier}/verify',        [Logistics\TripsController::class, 'verify'])->name('couriers.verify');

        // The company
        Route::get('/areas',                             [Logistics\CompanyController::class, 'areas'])->name('areas');
        Route::post('/areas',                            [Logistics\CompanyController::class, 'storeArea'])->name('areas.store');
        Route::delete('/areas/{area}',                   [Logistics\CompanyController::class, 'destroyArea'])->name('areas.destroy');
        Route::get('/rates',                             [Logistics\CompanyController::class, 'rates'])->name('rates');
        Route::post('/rates',                            [Logistics\CompanyController::class, 'updateRate'])->name('rates.update');
        Route::post('/zones',                            [Logistics\CompanyController::class, 'storeZone'])->name('zones.store');
        Route::get('/reports',                           [Logistics\CompanyController::class, 'reports'])->name('reports');
        Route::get('/reports/export',                    [Logistics\CompanyController::class, 'exportReport'])->name('reports.export');
        Route::get('/activity',                          [Logistics\CompanyController::class, 'activity'])->name('activity');
        Route::get('/chat',                              [Logistics\CompanyController::class, 'chat'])->name('chat');
        Route::post('/chat',                             [Logistics\CompanyController::class, 'send'])->middleware('throttle:30,1')->name('chat.send');
        Route::get('/chat/poll',                         [Logistics\CompanyController::class, 'poll'])->name('chat.poll');
        Route::get('/account',                           [Logistics\CompanyController::class, 'account'])->name('account');
        Route::put('/account',                           [Logistics\CompanyController::class, 'updateAccount'])->name('account.update');
    });

    // ── Phase 2: Buyer ─────────────────────────────────────────
    // Browsing: anyone; a token makes it personal (wishlist hearts, claimed vouchers).
    Route::middleware('api.optional-auth')->group(function () {
        Route::get('/home',                 [Buyer\CatalogController::class, 'home'])->name('home');
        Route::get('/shop',                 [Buyer\CatalogController::class, 'shop'])->name('shop');
        Route::get('/categories',           [Buyer\CatalogController::class, 'categories'])->name('categories');
        Route::get('/categories/{slug}',    [Buyer\CatalogController::class, 'category'])->name('categories.show');
        Route::get('/products/{slug}',      [Buyer\CatalogController::class, 'product'])->name('products.show');
        Route::get('/stores/{sellerId}',    [Buyer\CatalogController::class, 'store'])->whereNumber('sellerId')->name('stores.show');
        Route::get('/policies/{key}',       [Buyer\CatalogController::class, 'policy'])->name('policies.show');
    });

    // ALVY Assistant (guests and buyers; the chat is kept in the token's session).
    Route::middleware(['api.optional-auth', 'api.session'])->prefix('assistant')->name('assistant.')->group(function () {
        Route::get('/history',           [Buyer\AssistantController::class, 'history'])->name('history');
        Route::post('/ask',              [Buyer\AssistantController::class, 'ask'])->middleware('throttle:10,1')->name('ask');
        Route::post('/reset',            [Buyer\AssistantController::class, 'reset'])->name('reset');
        Route::post('/actions/{token}',  [Buyer\AssistantController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
    });

    // The web's buyer pages (auth + buyer_only), with a web session for the sign-in.
    Route::middleware(['auth:sanctum', 'api.buyer', 'api.session'])->group(function () {
        Route::get('/cart',                       [Buyer\ShoppingController::class, 'cart'])->name('cart');
        Route::post('/cart/{productId}',          [Buyer\ShoppingController::class, 'addToCart'])->whereNumber('productId')->name('cart.store');
        Route::patch('/cart/item/{cartItemId}',   [Buyer\ShoppingController::class, 'updateCart'])->name('cart.update');
        Route::delete('/cart/item/{cartItemId}',  [Buyer\ShoppingController::class, 'removeFromCart'])->name('cart.destroy');

        Route::get('/checkout',                   [Buyer\ShoppingController::class, 'checkout'])->name('checkout');
        Route::post('/checkout/preview',          [Buyer\ShoppingController::class, 'preview'])->name('checkout.preview');
        Route::post('/checkout',                  [Buyer\ShoppingController::class, 'placeOrder'])->name('checkout.store');

        Route::get('/orders',                     [Buyer\ShoppingController::class, 'orders'])->name('orders');
        Route::get('/orders/{id}',                [Buyer\ShoppingController::class, 'order'])->whereNumber('id')->name('orders.show');
        Route::get('/orders/{id}/payment-proof',  [\App\Http\Controllers\Api\V1\OrderFilesController::class, 'paymentProof'])->whereNumber('id')->name('orders.payment-proof');
        Route::get('/orders/{id}/proof-of-delivery', [\App\Http\Controllers\Api\V1\OrderFilesController::class, 'proofOfDelivery'])->whereNumber('id')->name('orders.pod');
        Route::post('/orders/{id}/cancel',        [Buyer\ShoppingController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{id}/gcash-receipt', [Buyer\ShoppingController::class, 'gcashResubmit'])->whereNumber('id')->middleware('throttle:10,1')->name('orders.gcash-resubmit');
        Route::post('/orders/{id}/received',      [Buyer\ShoppingController::class, 'confirmReceived'])->name('orders.received');
        Route::post('/orders/{id}/return',        [Buyer\ShoppingController::class, 'requestReturn'])->name('orders.return');
        Route::post('/orders/{id}/escalate',      [Buyer\ShoppingController::class, 'escalate'])->name('orders.escalate');
        Route::post('/orders/{id}/pay',           [Buyer\ShoppingController::class, 'pay'])->name('orders.pay');
        Route::post('/reviews/item/{item}',       [Buyer\ShoppingController::class, 'review'])->name('reviews.store');
        Route::post('/reviews/{review}',          [Buyer\ShoppingController::class, 'editReview'])->name('reviews.update');   // POST: multipart photos

        Route::get('/profile',                    [Buyer\AccountController::class, 'profile'])->name('profile');
        Route::post('/profile',                   [Buyer\AccountController::class, 'updateProfile'])->name('profile.update');  // POST: multipart avatar
        Route::patch('/profile/account',          [Buyer\AccountController::class, 'updateAccount'])->name('profile.account');
        Route::patch('/profile/password',         [Buyer\AccountController::class, 'changePassword'])->name('profile.password');
        Route::post('/profile/email',             [Buyer\AccountController::class, 'changeEmail'])->middleware('throttle:5,1')->name('profile.email');
        Route::post('/profile/phone',             [Buyer\AccountController::class, 'changePhone'])->middleware('throttle:5,1')->name('profile.phone');
        Route::post('/profile/contact/verify',    [Buyer\AccountController::class, 'verifyContact'])->middleware('throttle:10,1')->name('profile.contact.verify');
        Route::post('/profile/contact/resend',    [Buyer\AccountController::class, 'resendContact'])->middleware('throttle:3,1')->name('profile.contact.resend');
        Route::post('/profile/contact/cancel',    [Buyer\AccountController::class, 'cancelContact'])->name('profile.contact.cancel');

        Route::get('/addresses',                  [Buyer\AccountController::class, 'addresses'])->name('addresses');
        Route::post('/addresses',                 [Buyer\AccountController::class, 'addAddress'])->name('addresses.store');
        Route::patch('/addresses/{address}',      [Buyer\AccountController::class, 'updateAddress'])->name('addresses.update');
        Route::delete('/addresses/{address}',     [Buyer\AccountController::class, 'deleteAddress'])->name('addresses.destroy');
        Route::patch('/addresses/{address}/default', [Buyer\AccountController::class, 'defaultAddress'])->name('addresses.default');


        Route::get('/wishlist',                   [Buyer\AccountController::class, 'wishlist'])->name('wishlist');
        Route::post('/wishlist/{productId}',      [Buyer\AccountController::class, 'toggleWish'])->name('wishlist.toggle');
        Route::get('/vouchers',                   [Buyer\AccountController::class, 'vouchers'])->name('vouchers');
        Route::get('/vouchers/center',            [Buyer\AccountController::class, 'voucherCenter'])->name('vouchers.center');
        Route::post('/vouchers/claim-all',        [Buyer\AccountController::class, 'claimAllVouchers'])->middleware('throttle:10,1')->name('vouchers.claim-all');
        // A buyer applying to sell or to deliver (the website's "Become a seller / courier"; same rules).
        Route::post('/become-seller',             [Buyer\AccountController::class, 'becomeSeller'])->middleware('throttle:10,1')->name('become.seller');
        Route::post('/become-courier',            [Buyer\AccountController::class, 'becomeCourier'])->middleware('throttle:10,1')->name('become.courier');
        Route::post('/vouchers/{voucher}/claim',  [Buyer\AccountController::class, 'claimVoucher'])->name('vouchers.claim');
        Route::get('/following',                  [Buyer\AccountController::class, 'following'])->name('following');
        Route::post('/stores/{sellerId}/follow',  [Buyer\AccountController::class, 'toggleFollow'])->name('stores.follow');
        Route::post('/flash-sales/{flashSale}/remind', [Buyer\AccountController::class, 'remindFlashSale'])->name('flash-sales.remind');
    });
});
