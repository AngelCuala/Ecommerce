<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ComplianceController as AdminComplianceController;
use App\Http\Controllers\Admin\CourierController as AdminCourierController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SellerApplicationController as AdminSellerApplicationController;
use App\Http\Controllers\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Courier\AccountController as CourierAccountController;
use App\Http\Controllers\Courier\DashboardController as CourierDashboardController;
use App\Http\Controllers\Courier\DeliveryController as CourierDeliveryController;
use App\Http\Controllers\Courier\ProfitController as CourierProfitController;
use App\Http\Controllers\Courier\RegistrationController as CourierRegistrationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SellerApplicationController;
use App\Http\Controllers\Seller\AccountController as SellerAccountController;
use App\Http\Controllers\Seller\BookController as SellerBookController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Seller\ReportController as SellerReportController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

// ── One login per portal (App\Support\Portal) ─────────────────────
// Each portal has its own sign-in, sign-out and hand-off address, and its own copy of the shared
// pages its accounts use, so those pages read that portal's login.
Route::get('/session/handoff/{token}', \App\Http\Controllers\Auth\PortalHandoffController::class)->middleware('throttle:30,1')->name('portal.handoff');

// Private uploads (IDs, permits, proof of delivery, return photos, …): only through a signed link that expires (App\Support\ProtectedFile).
Route::get('/files/{path}', \App\Http\Controllers\ProtectedFileController::class)->where('path', '.*')
    ->middleware(['signed:relative', 'throttle:120,1'])->name('files.show');

foreach (['seller', 'courier', 'admin', 'sc'] as $portal) {
    Route::prefix($portal)->name($portal . '.')->group(function () use ($portal) {
        Route::get('/session/handoff/{token}', \App\Http\Controllers\Auth\PortalHandoffController::class)->middleware('throttle:30,1')->name('portal.handoff');
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.attempt');
        if ($portal !== 'sc') {   // sorting centers keep their own /sc/logout (SortingCenterController)
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
        }
    });
}
Route::middleware('auth')->group(function () {
    $messages = function () {
        Route::get('/messages',                     [MessageController::class, 'inbox'])->name('messages.inbox');
        Route::get('/messages/direct/{threadKey}',  [MessageController::class, 'directShow'])->name('messages.direct');
        Route::post('/messages/direct/{threadKey}', [MessageController::class, 'directStore'])->name('messages.direct.store');
        Route::post('/messages/new',                [MessageController::class, 'directNew'])->name('messages.new');
    };
    $chat = function () {
        Route::get('/chat/threads',               [MessageController::class, 'widgetThreads'])->name('chat.threads');
        Route::get('/chat/thread/{type}/{id}',    [MessageController::class, 'widgetThread'])->name('chat.thread')->where('id', '.*');
        Route::post('/chat/send/{type}/{id}',     [MessageController::class, 'widgetSend'])->name('chat.send')->where('id', '.*');
    };
    Route::prefix('seller')->name('seller.')->group(function () use ($messages, $chat) {
        $messages();
        $chat();
        Route::get('/orders/{order}/proof-of-delivery', [\App\Http\Controllers\ShipmentTrackingController::class, 'proofOfDelivery'])->whereNumber('order')->name('shipments.pod');
        Route::get('/orders/{order}/payment-proof', [\App\Http\Controllers\ShipmentTrackingController::class, 'paymentProof'])->whereNumber('order')->name('orders.payment-proof');
    });
    Route::prefix('admin')->name('admin.')->group(function () use ($messages, $chat) {
        $messages();
        $chat();
        Route::get('/orders/{order}/proof-of-delivery', [\App\Http\Controllers\ShipmentTrackingController::class, 'proofOfDelivery'])->whereNumber('order')->name('shipments.pod');
        Route::get('/orders/{order}/payment-proof', [\App\Http\Controllers\ShipmentTrackingController::class, 'paymentProof'])->whereNumber('order')->name('orders.payment-proof');
    });
    Route::prefix('courier')->name('courier.')->group(function () use ($chat) {
        $chat();
    });
});

// ── Sorting Center Portal ────────────────────────────────────
// /sc/login (above) is the sorting center portal's own sign-in, so signing out there never lands in a buyer
// account signed in to the shop in the same browser. /login works for logistics too; old entrances lead there.
Route::get('/sc/register',  [\App\Http\Controllers\SortingCenter\RegistrationController::class, 'create'])->name('sc.register')->middleware('guest');
Route::post('/sc/register', [\App\Http\Controllers\SortingCenter\RegistrationController::class, 'store'])->name('sc.register.store')->middleware('guest');

// Old entry points now redirect to the canonical login.
Route::redirect('/sorting-center/login', '/login')->name('sorting-center.login');

Route::middleware(['auth', 'is_sc', 'prevent.back'])->prefix('sc')->name('sc.')->group(function () {
    Route::get('/dashboard',            [\App\Http\Controllers\SortingCenterController::class, 'dashboard'])->name('dashboard');

    // Coverage areas (barangays within the SC municipality)
    Route::get('/areas',                [\App\Http\Controllers\SortingCenterController::class, 'areas'])->name('areas');
    Route::post('/areas',               [\App\Http\Controllers\SortingCenterController::class, 'storeArea'])->name('areas.store');
    Route::delete('/areas/{area}',      [\App\Http\Controllers\SortingCenterController::class, 'destroyArea'])->name('areas.destroy');

    // Riders
    Route::get('/riders',               [\App\Http\Controllers\SortingCenterController::class, 'riders'])->name('riders');
    Route::post('/riders',              [\App\Http\Controllers\SortingCenterController::class, 'storeRider'])->name('riders.store');
    Route::post('/riders/{rider}/approve', [\App\Http\Controllers\SortingCenterController::class, 'approveRider'])->name('riders.approve');
    Route::post('/riders/{rider}/reject',  [\App\Http\Controllers\SortingCenterController::class, 'rejectRider'])->name('riders.reject');
    Route::post('/riders/{rider}/toggle',  [\App\Http\Controllers\SortingCenterController::class, 'toggleRider'])->name('riders.toggle');
    Route::get('/riders/{rider}',          [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'showRider'])->name('riders.show');
    Route::post('/riders/{rider}/areas',   [\App\Http\Controllers\SortingCenterController::class, 'addRiderArea'])->name('riders.areas.add');
    Route::post('/riders/{rider}/areas/{area}/remove', [\App\Http\Controllers\SortingCenterController::class, 'removeRiderArea'])->name('riders.areas.remove');
    Route::get('/riders/{rider}/documents/{document}', [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'riderDocument'])->whereIn('document', ['license', 'or-cr'])->name('riders.document');
    Route::post('/riders/{rider}/verify',  [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'verifyRider'])->name('riders.verify');

    // Rider dispatch (Auto-assign to each barangay's rider, manual assignment) and COD remittances
    Route::get('/dispatch',                  [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'dispatchBoard'])->name('dispatch');
    Route::post('/dispatch/{parcel}/auto',   [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'autoAssign'])->name('dispatch.auto');
    // Auto-assign every waiting pickup or delivery to the rider of its barangay.
    Route::post('/dispatch/auto-all/{kind}', [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'autoAssignAll'])->whereIn('kind', ['pickup', 'delivery'])->name('dispatch.auto-all');
    Route::post('/dispatch/{parcel}/assign', [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'manualAssign'])->name('dispatch.assign');
    Route::get('/remittances',               [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'remittances'])->name('remittances');
    Route::get('/rider-pay',                 [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'riderPay'])->name('rider-pay');
    Route::post('/rider-pay/{rider}/send',   [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'sendPay'])->whereNumber('rider')->middleware('throttle:20,1')->name('rider-pay.send');
    Route::post('/rider-pay/cashouts/{entry}/paid',   [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'cashoutPaid'])->whereNumber('entry')->name('rider-pay.cashout.paid');
    Route::post('/rider-pay/cashouts/{entry}/reject', [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'cashoutReject'])->whereNumber('entry')->name('rider-pay.cashout.reject');
    Route::post('/remittances/{remittance}/confirm', [\App\Http\Controllers\SortingCenter\RiderWorkController::class, 'confirmRemittance'])->name('remittances.confirm');

    // Commission and net earnings: share of the shipping fee, ALVY's commission, rider pay
    Route::get('/commissions',               [\App\Http\Controllers\SortingCenter\CommissionController::class, 'index'])->name('commissions');

    // Logistics workbook (L1–L8): shipment list, reassign / bulk assign, exceptions, Return to Sender, rates and zones
    // Shipments is part of the Shipment Board now (one inventory list); old links keep their search.
    Route::get('/shipments',                  fn (\Illuminate\Http\Request $r) => redirect()->route('sc.tracking', array_filter($r->only('q', 'from', 'to')
        + ['bucket' => isset(\App\Models\Parcel::BUCKETS[$r->input('status')]) ? $r->input('status') : null])))->name('shipments');
    Route::post('/dispatch/bulk',             [\App\Http\Controllers\SortingCenter\LogisticsController::class, 'bulkAssign'])->name('dispatch.bulk');
    Route::post('/dispatch/{parcel}/reassign', [\App\Http\Controllers\SortingCenter\LogisticsController::class, 'reassign'])->name('dispatch.reassign');
    Route::post('/parcels/{parcel}/exception', [\App\Http\Controllers\SortingCenter\LogisticsController::class, 'markException'])->name('parcels.exception');
    // Return to Sender was removed: undeliverable parcels cancel the order and stay at the hub (old links land on Shipments).
    Route::get('/returns',                    [\App\Http\Controllers\SortingCenter\ReturnsController::class, 'index'])->name('returns');
    Route::post('/returns/{return}/assign',   [\App\Http\Controllers\SortingCenter\ReturnsController::class, 'assignPickup'])->whereNumber('return')->name('returns.assign');
    Route::post('/returns/{return}/receive',  [\App\Http\Controllers\SortingCenter\ReturnsController::class, 'receive'])->whereNumber('return')->name('returns.receive');
    Route::post('/returns/{return}/to-seller', [\App\Http\Controllers\SortingCenter\ReturnsController::class, 'assignSeller'])->whereNumber('return')->name('returns.to-seller');
    Route::get('/failed-deliveries',          [\App\Http\Controllers\SortingCenter\IssuesController::class, 'failed'])->name('failed-deliveries');
    Route::get('/rts',                        [\App\Http\Controllers\SortingCenter\LogisticsController::class, 'rtsQueue'])->name('rts');
    Route::post('/rts/{parcel}/assign',     [\App\Http\Controllers\SortingCenter\LogisticsController::class, 'assignRts'])->name('rts.assign');
    Route::get('/rates',                      [\App\Http\Controllers\SortingCenter\LogisticsController::class, 'rates'])->name('rates');
    Route::post('/rates',                     [\App\Http\Controllers\SortingCenter\LogisticsController::class, 'updateRate'])->name('rates.update');
    Route::post('/zones',                     [\App\Http\Controllers\SortingCenter\LogisticsController::class, 'storeZone'])->name('zones.store');
    Route::get('/activity',                   [\App\Http\Controllers\SortingCenter\LogisticsController::class, 'activity'])->name('activity');

    // Courier applications sent to this sorting center
    Route::get('/courier-applications',                        [\App\Http\Controllers\SortingCenter\CourierApplicationController::class, 'index'])->name('courier-applications.index');
    Route::get('/courier-applications/{courier}',              [\App\Http\Controllers\SortingCenter\CourierApplicationController::class, 'show'])->name('courier-applications.show');
    Route::get('/courier-applications/{courier}/documents/{document}', [\App\Http\Controllers\SortingCenter\CourierApplicationController::class, 'document'])
        ->whereIn('document', ['license', 'or-cr'])->name('courier-applications.document');
    Route::post('/courier-applications/{courier}/approve',     [\App\Http\Controllers\SortingCenter\CourierApplicationController::class, 'approve'])->name('courier-applications.approve');
    Route::post('/courier-applications/{courier}/reject',      [\App\Http\Controllers\SortingCenter\CourierApplicationController::class, 'reject'])->name('courier-applications.reject');

    // Pickup requests
    Route::get('/pickup-requests',      [\App\Http\Controllers\SortingCenterController::class, 'pickupRequests'])->name('pickup-requests');
    Route::post('/pickup-requests/{parcel}/approve', [\App\Http\Controllers\SortingCenterController::class, 'approvePickup'])->name('pickup-requests.approve');
    Route::post('/pickup-requests/{parcel}/reject',  [\App\Http\Controllers\SortingCenterController::class, 'rejectPickup'])->name('pickup-requests.reject');

    // Incoming parcels
    // Incoming Parcels was removed from the web portal: parcels are received on the Shipment Board (old links land there).
    // (The logistics app still reads SortingCenterController::incomingParcels through the API.)
    Route::get('/incoming-parcels',     fn () => redirect()->route('sc.tracking'))->name('incoming-parcels');
    // Printable waybills (one parcel, or several: ?ids[]=1&ids[]=2)
    Route::get('/parcels/{parcel}/waybill', [\App\Http\Controllers\SortingCenterController::class, 'waybill'])->name('parcels.waybill');
    Route::get('/waybills',          [\App\Http\Controllers\SortingCenterController::class, 'waybills'])->name('waybills');
    Route::post('/parcels/scan',        [\App\Http\Controllers\SortingCenterController::class, 'scanParcel'])->name('parcels.scan');
    Route::post('/parcels/{parcel}/advance', [\App\Http\Controllers\SortingCenterController::class, 'advanceParcel'])->name('parcels.advance');
    Route::post('/parcels/{parcel}/sort',    [\App\Http\Controllers\SortingCenterController::class, 'sortParcel'])->name('parcels.sort');
    Route::post('/parcels/{parcel}/sort-transfer', [\App\Http\Controllers\SortingCenterController::class, 'sortForTransfer'])->name('parcels.sort-transfer');
    Route::post('/parcels/{parcel}/assign',  [\App\Http\Controllers\SortingCenterController::class, 'assignParcel'])->name('parcels.assign');

    // Parcel sorting
    Route::get('/parcel-sorting',       [\App\Http\Controllers\SortingCenterController::class, 'parcelSorting'])->name('parcel-sorting');

    // Delivery assignment
    Route::get('/delivery-assignment',  [\App\Http\Controllers\SortingCenterController::class, 'deliveryAssignment'])->name('delivery-assignment');

    // Delivery monitoring
    Route::get('/delivery-monitoring',  [\App\Http\Controllers\SortingCenterController::class, 'deliveryMonitoring'])->name('delivery-monitoring');
    Route::post('/deliveries/{delivery}/status', [\App\Http\Controllers\SortingCenterController::class, 'updateDeliveryStatus'])->name('deliveries.update-status');
    Route::post('/deliveries/{delivery}/reschedule', [\App\Http\Controllers\SortingCenterController::class, 'rescheduleDelivery'])->name('deliveries.reschedule');
    Route::post('/deliveries/{delivery}/return',     [\App\Http\Controllers\SortingCenterController::class, 'returnParcel'])->name('deliveries.return');

    // Reports
    Route::get('/reports',              [\App\Http\Controllers\SortingCenterController::class, 'reports'])->name('reports');

    // Chat
    Route::get('/chat',                 [\App\Http\Controllers\SortingCenterController::class, 'chat'])->name('chat');
    Route::post('/chat/send',           [\App\Http\Controllers\SortingCenterController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/poll',            [\App\Http\Controllers\SortingCenterController::class, 'pollMessages'])->name('chat.poll');

    // Account
    Route::get('/account',              [\App\Http\Controllers\SortingCenterController::class, 'account'])->name('account');
    Route::put('/account',              [\App\Http\Controllers\SortingCenterController::class, 'updateAccount'])->name('account.update');
    // Security: change password (step 1 emails a code, step 2 enters it).
    Route::get('/account/security',                   [\App\Http\Controllers\SortingCenterController::class, 'security'])->name('account.security');
    Route::put('/account/security',                   [\App\Http\Controllers\SortingCenterController::class, 'changePassword'])->middleware('throttle:10,1')->name('account.password');
    Route::post('/account/security/password/confirm', [\App\Http\Controllers\SortingCenterController::class, 'confirmPasswordChange'])->middleware('throttle:10,1')->name('account.password.confirm');
    Route::post('/account/security/password/resend',  [\App\Http\Controllers\SortingCenterController::class, 'resendPasswordCode'])->middleware('throttle:3,1')->name('account.password.resend');
    Route::post('/account/security/password/cancel',  [\App\Http\Controllers\SortingCenterController::class, 'cancelPasswordChange'])->name('account.password.cancel');

    // Logout
    Route::post('/logout',              [\App\Http\Controllers\SortingCenterController::class, 'logout'])->name('logout');

    // ── SC-to-SC Transfers: replaced by Linehaul Trips (the old page opens the trips) ──
    Route::redirect('/transfers', '/sc/trips')->name('transfers');
    // Linehaul Trips: hub-to-hub trips of the company's couriers, to its own hubs or another sorting center's hub
    // (which accepts the trip first). Both companies track it, message each other, and the receiver receives the parcels.
    Route::get('/trips',                     [\App\Http\Controllers\SortingCenter\TripsController::class, 'index'])->name('trips');
    Route::post('/trips',                    [\App\Http\Controllers\SortingCenter\TripsController::class, 'store'])->name('trips.store');
    Route::post('/trips/auto-assign',        [\App\Http\Controllers\SortingCenter\TripsController::class, 'autoAssignAll'])->name('trips.auto-all');
    Route::get('/trips/{trip}',              [\App\Http\Controllers\SortingCenter\TripsController::class, 'show'])->whereNumber('trip')->name('trips.show');
    Route::post('/trips/{trip}/parcels',     [\App\Http\Controllers\SortingCenter\TripsController::class, 'addParcels'])->whereNumber('trip')->name('trips.parcels');
    Route::post('/trips/{trip}/auto',        [\App\Http\Controllers\SortingCenter\TripsController::class, 'autoAdd'])->whereNumber('trip')->name('trips.auto');
    Route::post('/trips/{trip}/parcels/{parcel}/remove', [\App\Http\Controllers\SortingCenter\TripsController::class, 'removeParcel'])->whereNumber('trip')->name('trips.parcels.remove');
    Route::post('/trips/{trip}/assign',      [\App\Http\Controllers\SortingCenter\TripsController::class, 'assign'])->whereNumber('trip')->name('trips.assign');
    Route::post('/trips/{trip}/cancel',      [\App\Http\Controllers\SortingCenter\TripsController::class, 'cancel'])->whereNumber('trip')->name('trips.cancel');
    Route::post('/trips/{trip}/respond',     [\App\Http\Controllers\SortingCenter\TripsController::class, 'respond'])->whereNumber('trip')->name('trips.respond');
    Route::post('/trips/{trip}/message',     [\App\Http\Controllers\SortingCenter\TripsController::class, 'message'])->whereNumber('trip')->name('trips.message');
    Route::post('/trips/{trip}/seal',        [\App\Http\Controllers\SortingCenter\TripsController::class, 'seal'])->whereNumber('trip')->name('trips.seal');
    Route::post('/trips/{trip}/receive',     [\App\Http\Controllers\SortingCenter\TripsController::class, 'receive'])->whereNumber('trip')->name('trips.receive');
    Route::post('/transfers/initiate',                          [\App\Http\Controllers\SortingCenterController::class, 'initiateTransfer'])->name('transfers.initiate');
    Route::post('/transfers/{transfer}/accept',                 [\App\Http\Controllers\SortingCenterController::class, 'acceptTransfer'])->name('transfers.accept');
    Route::post('/transfers/{transfer}/reject',                 [\App\Http\Controllers\SortingCenterController::class, 'rejectTransfer'])->name('transfers.reject');
    Route::post('/transfers/{transfer}/cancel',                 [\App\Http\Controllers\SortingCenterController::class, 'cancelTransfer'])->name('transfers.cancel');
    Route::post('/transfers/{transfer}/dispatch',               [\App\Http\Controllers\SortingCenterController::class, 'dispatchTransfer'])->name('transfers.dispatch');

    // Shipment Board: custody, sorting, transfers and exceptions for this center's parcels
    Route::get('/tracking',                         [\App\Http\Controllers\SortingCenter\ShipmentController::class, 'index'])->name('tracking');
    Route::get('/tracking/{parcel}',                [\App\Http\Controllers\SortingCenter\ShipmentController::class, 'show'])->name('tracking.show');
    Route::post('/tracking/{parcel}/receive',       [\App\Http\Controllers\SortingCenter\ShipmentController::class, 'receive'])->name('tracking.receive');
    Route::post('/tracking/{parcel}/start-sorting', [\App\Http\Controllers\SortingCenter\ShipmentController::class, 'startSorting'])->name('tracking.start-sorting');
    Route::post('/riders/{rider}/transport',        [\App\Http\Controllers\SortingCenter\ShipmentController::class, 'toggleTransport'])->name('riders.transport');
});

// Old sorting-center links go to the current login.
Route::redirect('/sorting-center', '/login');

// ── Public ───────────────────────────────────────────────────
// Storefront: guests and buyers only (sellers/admins are sent to their own panels).
Route::get('/', [HomeController::class, 'index'])->middleware('buyer_only')->name('home');

// The admin's logistics panel is opened from the admin panel after the one login.
Route::redirect('/logistics/login', '/login');

Route::middleware('buyer_only')->group(function () {
    Route::get('/categories', [\App\Http\Controllers\CategoryPageController::class, 'index'])->name('categories.page');
    Route::get('/categories/{slug}', [\App\Http\Controllers\CategoryPageController::class, 'show'])->name('categories.show');
    Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
    Route::get('/vouchers', [\App\Http\Controllers\VoucherWalletController::class, 'center'])->name('vouchers.center');
    Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

    // ALVY Assistant (AI help chat on the shop pages; guests and buyers)
    Route::get('/assistant/history',  [\App\Http\Controllers\AssistantController::class, 'history'])->name('assistant.history');
    Route::post('/assistant/ask',     [\App\Http\Controllers\AssistantController::class, 'ask'])->middleware('throttle:10,1')->name('assistant.ask');
    Route::post('/assistant/reset',   [\App\Http\Controllers\AssistantController::class, 'reset'])->name('assistant.reset');
    Route::post('/assistant/actions/{token}', [\App\Http\Controllers\AssistantController::class, 'confirm'])->middleware('throttle:10,1')->name('assistant.confirm');
    Route::get('/store/{sellerId}', [\App\Http\Controllers\StoreController::class, 'show'])->whereNumber('sellerId')->name('stores.show');
});
Route::get('/dbconn', fn() => view('dbconn'));
Route::get('/policies/{key}', [\App\Http\Controllers\PolicyController::class, 'show'])->name('policies.show');

// Get the app: the Android app to download (it uses this same site as its backend).
Route::get('/app',          [\App\Http\Controllers\AppDownloadController::class, 'show'])->name('app.page');
Route::get('/app/download', [\App\Http\Controllers\AppDownloadController::class, 'download'])->name('app.download');

// A rider or courier made by a logistics company sets a password from the emailed link (24 hours).
Route::get('/activate/{token}',  [\App\Http\Controllers\AccountActivationController::class, 'show'])->name('account.activate');
Route::post('/activate/{token}', [\App\Http\Controllers\AccountActivationController::class, 'store'])->middleware('throttle:10,1')->name('account.activate.store');

// ── PSGC Address API proxy (server-side to avoid CORS/SSL issues) ──
// Map pin → address (OpenStreetMap lookup, matched to the PSGC lists)
Route::get('/api/geo/reverse', [\App\Http\Controllers\GeoController::class, 'reverse'])->middleware('throttle:30,1')->name('geo.reverse');
Route::get('/api/geo/search',  [\App\Http\Controllers\GeoController::class, 'search'])->middleware('throttle:30,1')->name('geo.search');
Route::get('/api/psgc/regions',                          [\App\Http\Controllers\PsgcController::class, 'regions']);
Route::get('/api/psgc/regions/{code}/provinces',         [\App\Http\Controllers\PsgcController::class, 'provincesByRegion']);
Route::get('/api/psgc/provinces',                        [\App\Http\Controllers\PsgcController::class, 'provinces']);
Route::get('/api/psgc/provinces/{code}/municipalities',  [\App\Http\Controllers\PsgcController::class, 'municipalities']);
Route::get('/api/psgc/municipalities/{code}/barangays',  [\App\Http\Controllers\PsgcController::class, 'barangays']);
Route::get('/login',    [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login',   [AuthenticatedSessionController::class, 'store'])->name('login.attempt');
Route::middleware('guest')->group(function () {
    Route::get('/login/verify',         [AuthenticatedSessionController::class, 'showOtp'])->name('otp.show');
    Route::post('/login/verify',        [AuthenticatedSessionController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('otp.verify');
    Route::post('/login/verify/resend', [AuthenticatedSessionController::class, 'resendOtp'])->middleware('throttle:5,1')->name('otp.resend');
});
// Sign in with Google
Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->middleware('guest')->name('auth.google');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->middleware('guest')->name('auth.google.callback');
// Forgot password: emailed one-time link (Laravel password broker), then a new password
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password',         [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password',        [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'store'])->middleware('throttle:20,1')->name('password.email');
    Route::get('/reset-password/{token}',  [\App\Http\Controllers\Auth\NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password',         [\App\Http\Controllers\Auth\NewPasswordController::class, 'store'])->middleware('throttle:10,1')->name('password.store');
});
Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
Route::post('/register',[RegisteredUserController::class, 'store'])->middleware('guest')->name('register.store');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

// Profile photo for every role (buyer, seller, courier): saved as soon as it is chosen.
Route::post('/account/photo', [\App\Http\Controllers\ProfilePhotoController::class, 'update'])
    ->middleware(['auth', 'throttle:20,1'])->name('account.photo');

// ── Authenticated (buyer-only) ────────────────────────────────
Route::middleware(['auth', 'buyer_only', 'prevent.back'])->group(function () {

    // Cart
    Route::get('/cart',                      [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/{bookId}',            [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/item/{cartItemId}',  [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/item/{cartItemId}', [CartController::class, 'destroy'])->name('cart.destroy');

    // Checkout
    Route::get('/checkout',              [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout',             [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/confirmation', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

    // Profile
    Route::get('/profile',                    [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/personal-info',      [ProfileController::class, 'personalInfo'])->name('profile.personal-info');
    Route::patch('/profile/personal-info',    [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/orders',             [ProfileController::class, 'orders'])->name('profile.orders');
    Route::get('/profile/notifications',      [ProfileController::class, 'notifications'])->name('profile.notifications');
    Route::get('/profile/settings',           [ProfileController::class, 'settings'])->name('profile.settings');

    // Accounts & Security
    Route::get('/profile/security',           [ProfileController::class, 'security'])->name('profile.security');
    Route::patch('/profile/security/account', [ProfileController::class, 'updateAccount'])->name('profile.account.update');
    Route::patch('/profile/security/password',[ProfileController::class, 'changePassword'])->middleware('throttle:10,1')->name('profile.change-password');
    // The new password only takes effect with the code emailed to the account (App\Services\PasswordChangeCode).
    Route::post('/profile/security/password/confirm', [ProfileController::class, 'confirmPasswordChange'])->middleware('throttle:10,1')->name('profile.password.confirm');
    Route::post('/profile/security/password/resend',  [ProfileController::class, 'resendPasswordCode'])->middleware('throttle:3,1')->name('profile.password.resend');
    Route::post('/profile/security/password/cancel',  [ProfileController::class, 'cancelPasswordChange'])->name('profile.password.cancel');

    Route::get('/profile/addresses',          [ProfileController::class, 'addresses'])->name('profile.addresses');

    // Address CRUD
    Route::post('/profile/addresses',                       [\App\Http\Controllers\AddressController::class, 'store'])->name('addresses.store');
    Route::patch('/profile/addresses/{address}',            [\App\Http\Controllers\AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/profile/addresses/{address}',           [\App\Http\Controllers\AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::patch('/profile/addresses/{address}/set-default',[\App\Http\Controllers\AddressController::class, 'setDefault'])->name('addresses.set-default');

    // Buyer confirm delivery
    Route::post('/orders/{id}/confirm-delivery', [\App\Http\Controllers\OrderController::class, 'confirmDelivery'])->name('orders.confirm-delivery');

    // Buyer cancel order
    Route::post('/orders/{id}/cancel', [\App\Http\Controllers\OrderController::class, 'cancelByBuyer'])->name('orders.cancel');
    Route::post('/orders/{id}/gcash-receipt', [\App\Http\Controllers\GcashResubmitController::class, 'store'])->whereNumber('id')->middleware('throttle:10,1')->name('orders.gcash-resubmit');

    // ── Buyer modules (B1–B14) ──
    Route::get('/profile/orders/{id}',            [ProfileController::class, 'orderShow'])->whereNumber('id')->name('profile.orders.show');
    Route::post('/orders/{id}/return',            [\App\Http\Controllers\OrderController::class, 'requestReturn'])->name('orders.return');
    Route::post('/orders/{id}/escalate',          [\App\Http\Controllers\OrderController::class, 'escalateReturn'])->name('orders.escalate');
    Route::post('/reviews/item/{item}',           [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
    Route::patch('/reviews/{review}',             [\App\Http\Controllers\ReviewController::class, 'update'])->name('reviews.update');

    Route::post('/checkout/preview',              [CheckoutController::class, 'preview'])->name('checkout.preview');
    Route::post('/checkout/quantity',             [CheckoutController::class, 'quantity'])->name('checkout.quantity');

    Route::get('/notifications/{id}/open',        [ProfileController::class, 'openNotification'])->name('notifications.open');
    Route::post('/notifications/read-all',        [ProfileController::class, 'readAllNotifications'])->name('notifications.read-all');

    // Email / phone change, confirmed with a code
    Route::post('/profile/security/email',        [ProfileController::class, 'changeEmail'])->middleware('throttle:5,1')->name('profile.email.change');
    Route::post('/profile/security/phone',        [ProfileController::class, 'changePhone'])->middleware('throttle:5,1')->name('profile.phone.change');
    Route::post('/profile/security/verify',       [ProfileController::class, 'verifyContact'])->middleware('throttle:10,1')->name('profile.contact.verify');
    Route::post('/profile/security/resend',       [ProfileController::class, 'resendContactCode'])->middleware('throttle:3,1')->name('profile.contact.resend');
    Route::post('/profile/security/cancel',       [ProfileController::class, 'cancelContactChange'])->name('profile.contact.cancel');

    Route::get('/wishlist',                       [\App\Http\Controllers\WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{productId}',          [\App\Http\Controllers\WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/store/{sellerId}/follow',       [\App\Http\Controllers\StoreController::class, 'toggleFollow'])->name('stores.follow');
    Route::get('/following',                      [\App\Http\Controllers\StoreController::class, 'following'])->name('stores.following');
    Route::get('/my-vouchers',                    [\App\Http\Controllers\VoucherWalletController::class, 'index'])->name('vouchers.wallet');
    Route::post('/vouchers/claim-all',            [\App\Http\Controllers\VoucherWalletController::class, 'claimAll'])->middleware('throttle:10,1')->name('vouchers.claim-all');
    Route::post('/vouchers/{voucher}/claim',      [\App\Http\Controllers\VoucherWalletController::class, 'claim'])->name('vouchers.claim');

    // Online payment (e-wallet / card) and flash sale reminders
    Route::post('/orders/{id}/pay',               [\App\Http\Controllers\PaymentController::class, 'pay'])->name('orders.pay');
    Route::get('/payments/{payment}/return',      [\App\Http\Controllers\PaymentController::class, 'back'])->name('payments.return');
    Route::get('/payments/{payment}/sandbox',     [\App\Http\Controllers\PaymentController::class, 'sandbox'])->name('payments.sandbox');
    Route::post('/payments/{payment}/sandbox',    [\App\Http\Controllers\PaymentController::class, 'sandboxAction'])->name('payments.sandbox.act');
    Route::post('/flash-sales/{flashSale}/remind', [\App\Http\Controllers\FlashSaleReminderController::class, 'toggle'])->name('flash-sales.remind');
});

// ── Authenticated (all roles) ─────────────────────────────────
Route::middleware('auth')->group(function () {

    // A password change waits for the code emailed to the account (every account type).
    Route::post('/account/password/verify', [\App\Http\Controllers\PasswordChangeController::class, 'verify'])->middleware('throttle:10,1')->name('password.change.verify');
    Route::post('/account/password/resend', [\App\Http\Controllers\PasswordChangeController::class, 'resend'])->middleware('throttle:3,1')->name('password.change.resend');
    Route::post('/account/password/cancel', [\App\Http\Controllers\PasswordChangeController::class, 'cancel'])->name('password.change.cancel');
    // Proof-of-delivery photo (the controller checks: the order's buyer, its seller, or an admin)
    Route::get('/orders/{order}/proof-of-delivery', [\App\Http\Controllers\ShipmentTrackingController::class, 'proofOfDelivery'])->whereNumber('order')->name('shipments.pod');
        Route::get('/orders/{order}/payment-proof', [\App\Http\Controllers\ShipmentTrackingController::class, 'paymentProof'])->whereNumber('order')->name('orders.payment-proof');

    // Seller application — a buyer upgrading to a shop
    Route::get('/become-seller',  [SellerApplicationController::class, 'create'])->middleware('buyer_only')->name('seller.apply');
    Route::post('/become-seller', [SellerApplicationController::class, 'store'])->middleware('buyer_only')->name('seller.apply.store');

    // Messaging
    Route::get('/messages',                        [MessageController::class, 'inbox'])->name('messages.inbox');

    // Floating chat widget (AJAX/JSON)
    Route::get('/chat/threads',                    [MessageController::class, 'widgetThreads'])->name('chat.threads');
    Route::get('/chat/thread/{type}/{id}',         [MessageController::class, 'widgetThread'])->name('chat.thread')->where('id', '.*');
    Route::post('/chat/send/{type}/{id}',          [MessageController::class, 'widgetSend'])->name('chat.send')->where('id', '.*');
    Route::get('/messages/direct/{threadKey}',     [MessageController::class, 'directShow'])->name('messages.direct');
    Route::post('/messages/direct/{threadKey}',    [MessageController::class, 'directStore'])->name('messages.direct.store');
    Route::post('/messages/new',                   [MessageController::class, 'directNew'])->name('messages.new');

    // Sorting center / courier registration
    Route::get('/become-courier',  [CourierRegistrationController::class, 'create'])->name('courier.register');
    Route::post('/become-courier', [CourierRegistrationController::class, 'store'])->name('courier.register.store');
    Route::get('/courier/status',  [CourierRegistrationController::class, 'status'])->name('courier.status');
});

// ── Seller sign-up & application status ─────────────────────
Route::get('/seller/register',  [\App\Http\Controllers\Seller\RegistrationController::class, 'create'])->name('seller.register');
Route::post('/seller/register', [\App\Http\Controllers\Seller\RegistrationController::class, 'store'])->middleware('guest')->name('seller.register.store');
Route::middleware(['auth', 'role:seller'])->group(function () {
    Route::get('/seller/status',           [\App\Http\Controllers\Seller\StatusController::class, 'show'])->name('seller.status');
    Route::post('/seller/status/resubmit', [\App\Http\Controllers\Seller\StatusController::class, 'resubmit'])->name('seller.status.resubmit');
});

// ── Seller panel ─────────────────────────────────────────────
Route::middleware(['auth', 'role:seller,admin', 'seller.approved', 'prevent.back', \App\Http\Middleware\MarkSellerSectionSeen::class])
    ->prefix('seller')->name('seller.')->group(function () {

    Route::get('/',                          [SellerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications',             [SellerDashboardController::class, 'notifications'])->name('notifications');
    Route::post('/violations/{violation}/acknowledge', [SellerDashboardController::class, 'acknowledgeViolation'])->whereNumber('violation')->name('violations.acknowledge');
    Route::get('/notifications/{id}/open',   [SellerDashboardController::class, 'openNotification'])->whereNumber('id')->name('notifications.open');
    Route::post('/notifications/read-all',   [SellerDashboardController::class, 'readAllNotifications'])->name('notifications.read-all');
    Route::get('/books',                     [SellerBookController::class, 'index'])->name('books.index');
    Route::get('/books/create',              [SellerBookController::class, 'create'])->name('books.create');
    Route::post('/books',                    [SellerBookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit',         [SellerBookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}',              [SellerBookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}',           [SellerBookController::class, 'destroy'])->name('books.destroy');
    Route::patch('/books/{book}/archive',    [SellerBookController::class, 'archive'])->name('books.archive');
    Route::patch('/books/{book}/unarchive',  [SellerBookController::class, 'unarchive'])->name('books.unarchive');
    Route::patch('/books/{book}/stock',      [SellerBookController::class, 'updateStock'])->name('books.stock');
    Route::post('/books/bulk',               [SellerBookController::class, 'bulk'])->name('books.bulk');
    Route::post('/books/{book}/duplicate',   [SellerBookController::class, 'duplicate'])->name('books.duplicate');
    Route::patch('/books/{book}/end-sale',   [SellerBookController::class, 'endSale'])->name('books.end-sale');

    // Store settings (profile, pickup address, vacation mode) and store categories
    Route::get('/store-settings',            [\App\Http\Controllers\Seller\StoreSettingsController::class, 'edit'])->name('store-settings');
    Route::patch('/store-settings',          [\App\Http\Controllers\Seller\StoreSettingsController::class, 'update'])->name('store-settings.update');
    Route::get('/store-categories',          [\App\Http\Controllers\Seller\StoreCategoryController::class, 'index'])->name('store-categories.index');
    Route::post('/store-categories',         [\App\Http\Controllers\Seller\StoreCategoryController::class, 'store'])->name('store-categories.store');
    Route::post('/store-categories/reorder', [\App\Http\Controllers\Seller\StoreCategoryController::class, 'reorder'])->name('store-categories.reorder');
    Route::put('/store-categories/{storeCategory}',          [\App\Http\Controllers\Seller\StoreCategoryController::class, 'update'])->name('store-categories.update');
    Route::patch('/store-categories/{storeCategory}/toggle', [\App\Http\Controllers\Seller\StoreCategoryController::class, 'toggle'])->name('store-categories.toggle');
    Route::delete('/store-categories/{storeCategory}',       [\App\Http\Controllers\Seller\StoreCategoryController::class, 'destroy'])->name('store-categories.destroy');

    // Bundle deals and buy-X-get-Y promotions
    Route::get('/promotions',                [\App\Http\Controllers\Seller\PromotionController::class, 'index'])->name('promotions.index');
    Route::post('/promotions',               [\App\Http\Controllers\Seller\PromotionController::class, 'store'])->name('promotions.store');
    Route::patch('/promotions/{promotion}/toggle', [\App\Http\Controllers\Seller\PromotionController::class, 'toggle'])->name('promotions.toggle');
    Route::delete('/promotions/{promotion}', [\App\Http\Controllers\Seller\PromotionController::class, 'destroy'])->name('promotions.destroy');

    // Reviews of the seller's products (reply once, edit the reply; no delete)
    Route::get('/reviews',                   [\App\Http\Controllers\Seller\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/reply',   [\App\Http\Controllers\Seller\ReviewController::class, 'reply'])->name('reviews.reply');
    Route::put('/reviews/{review}/reply',    [\App\Http\Controllers\Seller\ReviewController::class, 'updateReply'])->name('reviews.reply.update');

    // Earnings and payouts
    Route::get('/earnings',                  [\App\Http\Controllers\Seller\EarningsController::class, 'index'])->name('earnings');
    Route::post('/earnings/payouts',         [\App\Http\Controllers\Seller\EarningsController::class, 'requestPayout'])->name('earnings.payout');

    Route::get('/orders',                    [SellerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}',               [SellerOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{id}/accept',       [SellerOrderController::class, 'accept'])->name('orders.accept');
    Route::post('/orders/{id}/pack',         [SellerOrderController::class, 'pack'])->name('orders.pack');
    Route::post('/orders/{id}/cancel',       [SellerOrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{id}/waybill',       [SellerOrderController::class, 'waybill'])->name('orders.waybill');
    Route::post('/orders/{id}/returned-received', [SellerOrderController::class, 'returnedReceived'])->name('orders.returned-received');
    Route::post('/orders/{id}/handover',     [SellerOrderController::class, 'handover'])->name('orders.handover');
    Route::post('/orders/{id}/handed-over',  [SellerOrderController::class, 'markHandedOver'])->name('orders.handed-over');
    Route::post('/orders/{id}/return',       [SellerOrderController::class, 'decideReturn'])->name('orders.return');
    Route::get('/flash-sales',               [\App\Http\Controllers\Seller\FlashSaleController::class, 'index'])->name('flash-sales.index');
    Route::post('/flash-sales',              [\App\Http\Controllers\Seller\FlashSaleController::class, 'store'])->name('flash-sales.store');
    Route::delete('/flash-sales/{flashSale}', [\App\Http\Controllers\Seller\FlashSaleController::class, 'destroy'])->name('flash-sales.destroy');

    // Reports
    Route::get('/reports',                   [SellerReportController::class, 'index'])->name('reports');
    Route::get('/commissions',               [\App\Http\Controllers\Seller\CommissionController::class, 'index'])->name('commissions');

    // Store vouchers (only for this seller's products)
    Route::get('/vouchers',                    [\App\Http\Controllers\Seller\VoucherController::class, 'index'])->name('vouchers.index');
    Route::get('/vouchers/create',             [\App\Http\Controllers\Seller\VoucherController::class, 'create'])->name('vouchers.create');
    Route::post('/vouchers',                   [\App\Http\Controllers\Seller\VoucherController::class, 'store'])->name('vouchers.store');
    Route::get('/vouchers/{voucher}/edit',     [\App\Http\Controllers\Seller\VoucherController::class, 'edit'])->name('vouchers.edit');
    Route::put('/vouchers/{voucher}',          [\App\Http\Controllers\Seller\VoucherController::class, 'update'])->name('vouchers.update');
    Route::patch('/vouchers/{voucher}/toggle', [\App\Http\Controllers\Seller\VoucherController::class, 'toggle'])->name('vouchers.toggle');
    Route::delete('/vouchers/{voucher}',       [\App\Http\Controllers\Seller\VoucherController::class, 'destroy'])->name('vouchers.destroy');

    // Account management
    Route::get('/account',                   [SellerAccountController::class, 'index'])->name('account');
    Route::patch('/account',                 [SellerAccountController::class, 'update'])->name('account.update');
    Route::get('/account/security',          [SellerAccountController::class, 'security'])->name('account.security');
    // Password change: step 1 emails a code, step 2 enters it.
    Route::put('/account/security',                  [SellerAccountController::class, 'changePassword'])->middleware('throttle:10,1')->name('account.password');
    Route::post('/account/security/password/confirm', [SellerAccountController::class, 'confirmPasswordChange'])->middleware('throttle:10,1')->name('account.password.confirm');
    Route::post('/account/security/password/resend',  [SellerAccountController::class, 'resendPasswordCode'])->middleware('throttle:3,1')->name('account.password.resend');
    Route::post('/account/security/password/cancel',  [SellerAccountController::class, 'cancelPasswordChange'])->name('account.password.cancel');
});

// ── Admin panel ──────────────────────────────────────────────
Route::middleware(['auth', 'role:admin', 'prevent.back'])
    ->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // ALVY (platform) vouchers; admins can also turn store vouchers off
    Route::get('/vouchers',                    [\App\Http\Controllers\Admin\VoucherController::class, 'index'])->name('vouchers.index');
    Route::get('/vouchers/create',             [\App\Http\Controllers\Admin\VoucherController::class, 'create'])->name('vouchers.create');
    Route::post('/vouchers',                   [\App\Http\Controllers\Admin\VoucherController::class, 'store'])->name('vouchers.store');
    Route::get('/vouchers/{voucher}/edit',     [\App\Http\Controllers\Admin\VoucherController::class, 'edit'])->name('vouchers.edit');
    Route::put('/vouchers/{voucher}',          [\App\Http\Controllers\Admin\VoucherController::class, 'update'])->name('vouchers.update');
    Route::patch('/vouchers/{voucher}/toggle', [\App\Http\Controllers\Admin\VoucherController::class, 'toggle'])->name('vouchers.toggle');
    Route::delete('/vouchers/{voucher}',       [\App\Http\Controllers\Admin\VoucherController::class, 'destroy'])->name('vouchers.destroy');

    // Home page banner: the admin's slides for events and sales (website and app)
    Route::get('/banners',                   [\App\Http\Controllers\Admin\HomeBannerController::class, 'index'])->name('banners.index');
    Route::get('/banners/create',            [\App\Http\Controllers\Admin\HomeBannerController::class, 'create'])->name('banners.create');
    Route::post('/banners',                  [\App\Http\Controllers\Admin\HomeBannerController::class, 'store'])->name('banners.store');
    Route::post('/banners/settings',         [\App\Http\Controllers\Admin\HomeBannerController::class, 'settings'])->name('banners.settings');
    Route::get('/banners/{banner}/edit',     [\App\Http\Controllers\Admin\HomeBannerController::class, 'edit'])->name('banners.edit');
    Route::put('/banners/{banner}',          [\App\Http\Controllers\Admin\HomeBannerController::class, 'update'])->name('banners.update');
    Route::patch('/banners/{banner}/toggle', [\App\Http\Controllers\Admin\HomeBannerController::class, 'toggle'])->name('banners.toggle');
    Route::delete('/banners/{banner}',       [\App\Http\Controllers\Admin\HomeBannerController::class, 'destroy'])->name('banners.destroy');

    // New seller products waiting for approval
    Route::get('/product-approvals',                      [\App\Http\Controllers\Admin\ProductApprovalController::class, 'index'])->name('product-approvals.index');
    Route::patch('/product-approvals/{product}/approve',  [\App\Http\Controllers\Admin\ProductApprovalController::class, 'approve'])->name('product-approvals.approve');
    Route::patch('/product-approvals/{product}/reject',   [\App\Http\Controllers\Admin\ProductApprovalController::class, 'reject'])->name('product-approvals.reject');


    Route::get('/categories',            [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories',           [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{id}',       [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{id}',    [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    // GCash paid to ALVY: check receipts, send refunds, set ALVY's QR
    Route::get('/gcash-payments',                      [\App\Http\Controllers\Admin\GcashPaymentController::class, 'index'])->name('gcash.index');
    Route::post('/gcash-payments/{group}/approve',     [\App\Http\Controllers\Admin\GcashPaymentController::class, 'approve'])->whereUuid('group')->name('gcash.approve');
    Route::post('/gcash-payments/{group}/reject',      [\App\Http\Controllers\Admin\GcashPaymentController::class, 'reject'])->whereUuid('group')->name('gcash.reject');
    Route::post('/gcash-payments/refunds/{order}',     [\App\Http\Controllers\Admin\GcashPaymentController::class, 'refunded'])->whereNumber('order')->name('gcash.refunded');
    Route::post('/gcash-payments/account',             [\App\Http\Controllers\Admin\GcashPaymentController::class, 'updateAccount'])->name('gcash.account');

    Route::get('/orders',                [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}',           [AdminOrderController::class, 'show'])->name('orders.show');
    // Statuses follow seller → logistics → rider: these admin routes only answer "not allowed" (AdminCannotChangeShipmentStatus).
    Route::patch('/orders/{id}',         [AdminOrderController::class, 'update'])->name('orders.update')->middleware(\App\Http\Middleware\AdminCannotChangeShipmentStatus::class);
    Route::post('/orders/{id}/dispute',  [AdminOrderController::class, 'resolveDispute'])->name('orders.dispute');

    // Buyer management
    Route::get('/customers',                        [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/riders',                           [\App\Http\Controllers\Admin\RiderDirectoryController::class, 'index'])->name('riders.index');
    Route::get('/sorting-centers',                  [\App\Http\Controllers\Admin\SortingCenterDirectoryController::class, 'index'])->name('sorting-centers.index');
    Route::get('/customers/{id}',                   [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{id}/valid-id',          [AdminCustomerController::class, 'validId'])->name('customers.validId');
    Route::patch('/customers/{id}/approve',         [AdminCustomerController::class, 'approve'])->name('customers.approve');
    Route::patch('/customers/{id}/reject',          [AdminCustomerController::class, 'reject'])->name('customers.reject');
    Route::patch('/customers/{id}/suspend',         [AdminCustomerController::class, 'suspend'])->name('customers.suspend');
    Route::patch('/customers/{id}/restore',         [AdminCustomerController::class, 'restore'])->name('customers.restore');


    // Buyer management
    Route::get('/customers',                        [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{id}',                   [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{id}/valid-id',          [AdminCustomerController::class, 'validId'])->name('customers.validId');
    Route::patch('/customers/{id}/approve',         [AdminCustomerController::class, 'approve'])->name('customers.approve');
    Route::patch('/customers/{id}/reject',          [AdminCustomerController::class, 'reject'])->name('customers.reject');
    Route::patch('/customers/{id}/suspend',         [AdminCustomerController::class, 'suspend'])->name('customers.suspend');
    Route::patch('/customers/{id}/restore',         [AdminCustomerController::class, 'restore'])->name('customers.restore');

    Route::get('/users',                      [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{id}',                 [AdminUserController::class, 'show'])->name('users.show');
    Route::patch('/users/{id}/activate',      [AdminUserController::class, 'activate'])->name('users.activate');
    Route::patch('/users/{id}/suspend',       [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::patch('/users/{id}/restore',       [AdminUserController::class, 'restore'])->name('users.restore');
    Route::patch('/users/{id}/deactivate',    [AdminUserController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('/users/{id}/assign-municipality', [AdminUserController::class, 'assignMunicipality'])->name('users.assign-municipality');

    // Seller management (platform view — no seller orders)
    Route::get('/sellers',                    [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::get('/sellers/{id}',               [AdminSellerController::class, 'show'])->name('sellers.show');
    Route::post('/sellers/{id}/suspend',      [AdminSellerController::class, 'suspend'])->name('sellers.suspend');
    Route::post('/sellers/{id}/activate',     [AdminSellerController::class, 'activate'])->name('sellers.activate');

    // Analytics & Reports (platform-level)
    Route::get('/analytics',                  [AdminAnalyticsController::class, 'index'])->name('analytics.index');

    // Activity logs
    Route::get('/activity',                   [AdminActivityLogController::class, 'index'])->name('activity.index');

    // Admin notifications
    Route::get('/notifications',              [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all',    [AdminNotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/seller-applications',                               [AdminSellerApplicationController::class, 'index'])->name('seller-applications.index');
    Route::get('/seller-applications/{sellerApplication}',           [AdminSellerApplicationController::class, 'show'])->name('seller-applications.show');
    Route::patch('/seller-applications/{sellerApplication}/approve', [AdminSellerApplicationController::class, 'approve'])->name('seller-applications.approve');
    Route::patch('/seller-applications/{sellerApplication}/reject',  [AdminSellerApplicationController::class, 'reject'])->name('seller-applications.reject');

    // Sorting Center registrations
    Route::get('/sc-applications',                                  [\App\Http\Controllers\Admin\SortingCenterApplicationController::class, 'index'])->name('sc-applications.index');
    Route::get('/sc-applications/{application}',                    [\App\Http\Controllers\Admin\SortingCenterApplicationController::class, 'show'])->name('sc-applications.show');
    Route::get('/sc-applications/{application}/document/{type}',    [\App\Http\Controllers\Admin\SortingCenterApplicationController::class, 'document'])
        ->whereIn('type', ['id', 'permit'])->name('sc-applications.document');
    Route::patch('/sc-applications/{application}/approve',          [\App\Http\Controllers\Admin\SortingCenterApplicationController::class, 'approve'])->name('sc-applications.approve');
    Route::patch('/sc-applications/{application}/reject',           [\App\Http\Controllers\Admin\SortingCenterApplicationController::class, 'reject'])->name('sc-applications.reject');

    Route::get('/reviews',               [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::delete('/reviews/{id}',       [AdminReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::get('/reports',               [AdminReportController::class, 'index'])->name('reports.index');

    // Seller compliance
    Route::get('/compliance',                    [AdminComplianceController::class, 'index'])->name('compliance.index');
    Route::get('/compliance/{sellerId}',         [AdminComplianceController::class, 'show'])->name('compliance.show');
    Route::post('/compliance',                   [AdminComplianceController::class, 'store'])->name('compliance.store');

    // Platform settings
    Route::get('/settings',                              [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/announcements',               [AdminSettingsController::class, 'storeAnnouncement'])->name('settings.announcements.store');
    Route::patch('/settings/announcements/{id}/toggle', [AdminSettingsController::class, 'toggleAnnouncement'])->name('settings.announcements.toggle');
    Route::delete('/settings/announcements/{id}',       [AdminSettingsController::class, 'destroyAnnouncement'])->name('settings.announcements.destroy');
    Route::get('/settings/policies/{key}',              [AdminSettingsController::class, 'showPolicy'])->name('settings.policies.show');
    Route::put('/settings/policies/{key}',              [AdminSettingsController::class, 'updatePolicy'])->name('settings.policies.update');

    Route::put('/settings/policies/{key}',              [AdminSettingsController::class, 'updatePolicy'])->name('settings.policies.update');

    // Sorting center / courier management
    Route::get('/couriers',                     [AdminCourierController::class, 'index'])->name('couriers.index');
    Route::get('/couriers/{courier}',           [AdminCourierController::class, 'show'])->name('couriers.show');
    Route::patch('/couriers/{courier}/approve', [AdminCourierController::class, 'approve'])->name('couriers.approve');
    Route::patch('/couriers/{courier}/reject',  [AdminCourierController::class, 'reject'])->name('couriers.reject');
    Route::patch('/couriers/{courier}/suspend', [AdminCourierController::class, 'suspend'])->name('couriers.suspend');

    // ── Logistics panel routes (under admin.* prefix) ─────────
    Route::prefix('logistics')->name('logistics.')->group(function () {
        Route::get('/',                                  [\App\Http\Controllers\Admin\Logistics\DashboardController::class, 'index'])->name('dashboard');
        // Shipments across all sorting centers: history, delays, exceptions, corrections
        Route::get('/shipments',                                 [\App\Http\Controllers\Admin\Logistics\ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/{parcel}',                        [\App\Http\Controllers\Admin\Logistics\ShipmentController::class, 'show'])->name('shipments.show');
        Route::post('/shipment-exceptions/{exception}/resolve',  [\App\Http\Controllers\Admin\Logistics\ShipmentController::class, 'resolveException'])->name('shipments.exceptions.resolve');
        Route::post('/shipment-events/{event}/correct',          [\App\Http\Controllers\Admin\Logistics\ShipmentController::class, 'correctEvent'])->name('shipments.events.correct');
        Route::post('/riders/{rider}/transport',                 [\App\Http\Controllers\Admin\Logistics\ShipmentController::class, 'toggleTransport'])->name('riders.transport');
        
        Route::get('/riders',                            [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'index'])->name('riders.index');
        Route::get('/riders/{rider}',                    [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'show'])->name('riders.show');
        Route::post('/riders/{rider}/approve',           [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'approve'])->name('riders.approve');
        Route::post('/riders/{rider}/disapprove',        [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'disapprove'])->name('riders.disapprove');
        Route::post('/riders/{rider}/toggle-active',     [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'toggleActive'])->name('riders.toggle-active');

        Route::get('/pickup-requests',                   [\App\Http\Controllers\Admin\Logistics\PickupRequestController::class, 'index'])->name('pickup-requests.index');
        Route::post('/pickup-requests/{parcel}/approve', [\App\Http\Controllers\Admin\Logistics\PickupRequestController::class, 'approve'])->name('pickup-requests.approve')->middleware(\App\Http\Middleware\AdminCannotChangeShipmentStatus::class);
        Route::post('/pickup-requests/{parcel}/reject',  [\App\Http\Controllers\Admin\Logistics\PickupRequestController::class, 'reject'])->name('pickup-requests.reject')->middleware(\App\Http\Middleware\AdminCannotChangeShipmentStatus::class);

        Route::get('/parcels',                           [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'index'])->name('parcels.index');
        // Must come before /parcels/{parcel}, or "sorting" is treated as a parcel ID.
        Route::get('/parcels/sorting',                   [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'sorting'])->name('parcels.sorting');
        Route::get('/parcels/{parcel}',                 [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'show'])->name('parcels.show');
        Route::post('/parcels/{parcel}/mark-picked-up',  [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'markPickedUp'])->name('parcels.mark-picked-up')->middleware(\App\Http\Middleware\AdminCannotChangeShipmentStatus::class);
        Route::post('/parcels/{parcel}/sort',            [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'sort'])->name('parcels.sort')->middleware(\App\Http\Middleware\AdminCannotChangeShipmentStatus::class);

        Route::get('/deliveries/assign',                 [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'assignmentIndex'])->name('deliveries.assign-index');
        Route::post('/deliveries/{parcel}/assign',       [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'assign'])->name('deliveries.assign')->middleware(\App\Http\Middleware\AdminCannotChangeShipmentStatus::class);
        Route::get('/deliveries/monitor',                [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'monitor'])->name('deliveries.monitor');
        Route::post('/deliveries/{delivery}/status',     [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'updateStatus'])->name('deliveries.update-status')->middleware(\App\Http\Middleware\AdminCannotChangeShipmentStatus::class);

        Route::get('/reports',                           [\App\Http\Controllers\Admin\Logistics\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export',                    [\App\Http\Controllers\Admin\Logistics\ReportController::class, 'export'])->name('reports.export');

        Route::get('/chat',                              [\App\Http\Controllers\Admin\Logistics\ChatController::class, 'index'])->name('chat.index');
        Route::post('/chat/send',                        [\App\Http\Controllers\Admin\Logistics\ChatController::class, 'send'])->name('chat.send');
        Route::get('/chat/poll',                         [\App\Http\Controllers\Admin\Logistics\ChatController::class, 'poll'])->name('chat.poll');
    });
});

// ── Courier / Sorting Center panel ──────────────────────────
Route::middleware(['auth', 'role:courier,admin', 'prevent.back'])
    ->prefix('courier')->name('courier.')->group(function () {

    Route::get('/',                            [CourierDashboardController::class, 'index'])->name('dashboard');

    // Hub Trips: a logistics company's truck courier goes On Duty here (or in the app) to be assigned hub-to-hub trips
    Route::get('/trips',                       [\App\Http\Controllers\Courier\TruckDutyController::class, 'index'])->name('trips');
    Route::get('/trips/history',               [\App\Http\Controllers\Courier\TruckDutyController::class, 'history'])->name('trips.history');
    Route::get('/trips/pay',                   [\App\Http\Controllers\Courier\TruckDutyController::class, 'pay'])->name('trips.pay');
    Route::post('/trips/duty',                 [\App\Http\Controllers\Courier\TruckDutyController::class, 'duty'])->middleware('throttle:20,1')->name('trips.duty');
    Route::post('/trips/ping',                 [\App\Http\Controllers\Courier\TruckDutyController::class, 'ping'])->middleware('throttle:10,1')->name('trips.ping');
    Route::post('/trips/{trip}/accept',        [\App\Http\Controllers\Courier\TruckDutyController::class, 'accept'])->whereNumber('trip')->name('trips.accept');
    Route::post('/trips/{trip}/decline',       [\App\Http\Controllers\Courier\TruckDutyController::class, 'decline'])->whereNumber('trip')->name('trips.decline');
    Route::post('/trips/{trip}/loading',        [\App\Http\Controllers\Courier\TruckDutyController::class, 'startLoading'])->whereNumber('trip')->name('trips.loading');
    Route::post('/trips/{trip}/scan',           [\App\Http\Controllers\Courier\TruckDutyController::class, 'scan'])->whereNumber('trip')->middleware('throttle:60,1')->name('trips.scan');
    Route::post('/trips/{trip}/depart',         [\App\Http\Controllers\Courier\TruckDutyController::class, 'depart'])->whereNumber('trip')->name('trips.depart');
    Route::post('/trips/{trip}/delay',          [\App\Http\Controllers\Courier\TruckDutyController::class, 'delay'])->whereNumber('trip')->name('trips.delay');
    Route::post('/trips/{trip}/arrived',        [\App\Http\Controllers\Courier\TruckDutyController::class, 'arrived'])->whereNumber('trip')->name('trips.arrived');
    Route::post('/trips/{trip}/note',           [\App\Http\Controllers\Courier\TruckDutyController::class, 'note'])->whereNumber('trip')->name('trips.note');
    Route::get('/notifications',               [CourierDashboardController::class, 'notifications'])->name('notifications');
    Route::get('/notifications/{id}/open',     [CourierDashboardController::class, 'openNotification'])->whereNumber('id')->name('notifications.open');
    Route::post('/notifications/read-all',     [CourierDashboardController::class, 'readAllNotifications'])->name('notifications.read-all');
    Route::get('/history',                     [CourierDeliveryController::class, 'history'])->name('history');
    Route::get('/profit',                      [CourierProfitController::class, 'index'])->name('profit');
    Route::get('/deliveries/{id}',             [CourierDeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries/{id}/accept',     [CourierDeliveryController::class, 'accept'])->name('deliveries.accept');
    Route::post('/deliveries/{id}/pickup',     [CourierDeliveryController::class, 'pickup'])->name('deliveries.pickup');
    Route::post('/deliveries/{id}/in-transit', [CourierDeliveryController::class, 'inTransit'])->name('deliveries.in-transit');
    Route::post('/deliveries/{id}/complete',   [CourierDeliveryController::class, 'complete'])->name('deliveries.complete');

    // Rider work for a logistics company (from the mobile app): offers, pickups, deliveries, pay, COD
    Route::get('/rider',                           [\App\Http\Controllers\Courier\RiderController::class, 'index'])->name('rider');
    Route::post('/rider/online',                   [\App\Http\Controllers\Courier\RiderController::class, 'toggleOnline'])->name('rider.online');
    Route::post('/rider/offers/{offer}/accept',    [\App\Http\Controllers\Courier\RiderController::class, 'accept'])->whereNumber('offer')->name('rider.offers.accept');
    Route::post('/rider/offers/{offer}/decline',   [\App\Http\Controllers\Courier\RiderController::class, 'decline'])->whereNumber('offer')->name('rider.offers.decline');
    Route::post('/rider/jobs/{kind}/{job}/decline', [\App\Http\Controllers\Courier\RiderController::class, 'declineJob'])->whereIn('kind', ['pickup', 'delivery'])->whereNumber('job')->name('rider.jobs.decline');
    Route::post('/rider/pickups/{pickup}/arriving', [\App\Http\Controllers\Courier\RiderController::class, 'arriving'])->whereNumber('pickup')->name('rider.pickups.arriving');
    Route::post('/rider/jobs/{kind}/{job}/acknowledge', [\App\Http\Controllers\Courier\RiderController::class, 'acknowledge'])->whereIn('kind', ['pickup', 'delivery'])->whereNumber('job')->name('rider.jobs.acknowledge');
    Route::get('/rider/pickups/{pickup}',          [\App\Http\Controllers\Courier\RiderController::class, 'pickup'])->whereNumber('pickup')->name('rider.pickups.show');
    Route::post('/rider/pickups/{pickup}/confirm', [\App\Http\Controllers\Courier\RiderController::class, 'confirmPickup'])->whereNumber('pickup')->name('rider.pickups.confirm');
    Route::get('/rider/deliveries/{delivery}',          [\App\Http\Controllers\Courier\RiderController::class, 'delivery'])->whereNumber('delivery')->name('rider.deliveries.show');
    Route::post('/rider/deliveries/{delivery}/start',   [\App\Http\Controllers\Courier\RiderController::class, 'startDelivery'])->whereNumber('delivery')->name('rider.deliveries.start');
    Route::post('/rider/deliveries/{delivery}/deliver', [\App\Http\Controllers\Courier\RiderController::class, 'deliver'])->whereNumber('delivery')->name('rider.deliveries.deliver');
    Route::get('/rider/return-pickups/{return}',          [\App\Http\Controllers\Courier\RiderController::class, 'returnPickup'])->whereNumber('return')->name('rider.return-pickups.show');
    Route::post('/rider/return-pickups/{return}/accept',  [\App\Http\Controllers\Courier\RiderController::class, 'acceptReturnPickup'])->whereNumber('return')->name('rider.return-pickups.accept');
    Route::post('/rider/return-pickups/{return}/confirm', [\App\Http\Controllers\Courier\RiderController::class, 'confirmReturnPickup'])->whereNumber('return')->name('rider.return-pickups.confirm');
    Route::post('/rider/return-pickups/{return}/handed',  [\App\Http\Controllers\Courier\RiderController::class, 'returnHandedToSeller'])->whereNumber('return')->name('rider.return-pickups.handed');
    Route::post('/rider/returns/{parcel}/returned', [\App\Http\Controllers\Courier\RiderController::class, 'returnedToSeller'])->whereNumber('parcel')->name('rider.returns.returned');
    Route::post('/rider/deliveries/{delivery}/fail',    [\App\Http\Controllers\Courier\RiderController::class, 'fail'])->whereNumber('delivery')->name('rider.deliveries.fail');
    Route::get('/rider/profile/{rider?}',          [\App\Http\Controllers\Courier\RiderController::class, 'profile'])->whereNumber('rider')->name('rider.profile');
    Route::put('/rider/profile',                   [\App\Http\Controllers\Courier\RiderController::class, 'updateProfile'])->name('rider.profile.update');
    Route::get('/rider/{rider}/documents/{document}', [\App\Http\Controllers\Courier\RiderController::class, 'document'])->whereNumber('rider')->whereIn('document', ['license', 'or-cr'])->name('rider.document');
    Route::get('/rider/history',                   [\App\Http\Controllers\Courier\RiderController::class, 'history'])->name('rider.history');
    Route::get('/rider/earnings',                  [\App\Http\Controllers\Courier\RiderController::class, 'earnings'])->name('rider.earnings');
    Route::get('/rider/wallet',                    [\App\Http\Controllers\Courier\RiderController::class, 'wallet'])->name('rider.wallet');
    Route::post('/rider/wallet/cashout',           [\App\Http\Controllers\Courier\RiderController::class, 'cashout'])->middleware('throttle:10,1')->name('rider.wallet.cashout');
    Route::get('/rider/cod',                       [\App\Http\Controllers\Courier\RiderController::class, 'cod'])->name('rider.cod');
    Route::post('/rider/cod/remit',                [\App\Http\Controllers\Courier\RiderController::class, 'remit'])->name('rider.cod.remit');
    Route::get('/rider/cod/remittances/{remittance}/receipt', [\App\Http\Controllers\Courier\RiderController::class, 'remittanceReceipt'])->whereNumber('remittance')->name('rider.cod.receipt');

    // Account + security
    Route::get('/account',           [CourierAccountController::class, 'index'])->name('account');
    Route::put('/account',           [CourierAccountController::class, 'update'])->name('account.update');
    Route::get('/account/security',  [CourierAccountController::class, 'security'])->name('account.security');
    Route::put('/account/security',  [CourierAccountController::class, 'updatePassword'])->name('account.password');
});

