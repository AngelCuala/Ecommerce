<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ComplianceController as AdminComplianceController;
use App\Http\Controllers\Admin\CourierController as AdminCourierController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SellerApplicationController as AdminSellerApplicationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
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

// ── Sorting Center Portal ────────────────────────────────────
Route::get('/sc/login',  [\App\Http\Controllers\SortingCenter\LoginController::class, 'create'])->name('sc.login')->middleware('guest');
Route::post('/sc/login', [\App\Http\Controllers\SortingCenter\LoginController::class, 'store'])->name('sc.login.store')->middleware('guest');

Route::middleware(['auth', 'is_sc'])->prefix('sc')->name('sc.')->group(function () {
    Route::get('/dashboard',            [\App\Http\Controllers\SortingCenterController::class, 'dashboard'])->name('dashboard');

    // Riders
    Route::get('/riders',               [\App\Http\Controllers\SortingCenterController::class, 'riders'])->name('riders');
    Route::post('/riders/{rider}/approve', [\App\Http\Controllers\SortingCenterController::class, 'approveRider'])->name('riders.approve');
    Route::post('/riders/{rider}/reject',  [\App\Http\Controllers\SortingCenterController::class, 'rejectRider'])->name('riders.reject');
    Route::post('/riders/{rider}/toggle',  [\App\Http\Controllers\SortingCenterController::class, 'toggleRider'])->name('riders.toggle');

    // Pickup requests
    Route::get('/pickup-requests',      [\App\Http\Controllers\SortingCenterController::class, 'pickupRequests'])->name('pickup-requests');
    Route::post('/pickup-requests/{parcel}/approve', [\App\Http\Controllers\SortingCenterController::class, 'approvePickup'])->name('pickup-requests.approve');
    Route::post('/pickup-requests/{parcel}/reject',  [\App\Http\Controllers\SortingCenterController::class, 'rejectPickup'])->name('pickup-requests.reject');

    // Incoming parcels
    Route::get('/incoming-parcels',     [\App\Http\Controllers\SortingCenterController::class, 'incomingParcels'])->name('incoming-parcels');
    Route::post('/parcels/{parcel}/advance', [\App\Http\Controllers\SortingCenterController::class, 'advanceParcel'])->name('parcels.advance');
    Route::post('/parcels/{parcel}/sort',    [\App\Http\Controllers\SortingCenterController::class, 'sortParcel'])->name('parcels.sort');
    Route::post('/parcels/{parcel}/assign',  [\App\Http\Controllers\SortingCenterController::class, 'assignParcel'])->name('parcels.assign');

    // Parcel sorting
    Route::get('/parcel-sorting',       [\App\Http\Controllers\SortingCenterController::class, 'parcelSorting'])->name('parcel-sorting');

    // Delivery assignment
    Route::get('/delivery-assignment',  [\App\Http\Controllers\SortingCenterController::class, 'deliveryAssignment'])->name('delivery-assignment');

    // Delivery monitoring
    Route::get('/delivery-monitoring',  [\App\Http\Controllers\SortingCenterController::class, 'deliveryMonitoring'])->name('delivery-monitoring');
    Route::post('/deliveries/{delivery}/status', [\App\Http\Controllers\SortingCenterController::class, 'updateDeliveryStatus'])->name('deliveries.update-status');

    // Reports
    Route::get('/reports',              [\App\Http\Controllers\SortingCenterController::class, 'reports'])->name('reports');

    // Chat
    Route::get('/chat',                 [\App\Http\Controllers\SortingCenterController::class, 'chat'])->name('chat');
    Route::post('/chat/send',           [\App\Http\Controllers\SortingCenterController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/poll',            [\App\Http\Controllers\SortingCenterController::class, 'pollMessages'])->name('chat.poll');

    // Account
    Route::get('/account',              [\App\Http\Controllers\SortingCenterController::class, 'account'])->name('account');
    Route::put('/account',              [\App\Http\Controllers\SortingCenterController::class, 'updateAccount'])->name('account.update');

    // Logout
    Route::post('/logout',              [\App\Http\Controllers\SortingCenterController::class, 'logout'])->name('logout');
});

// ── Sorting Center Portal ────────────────────────────────────
Route::get('/sorting-center/login',  [\App\Http\Controllers\SortingCenter\LoginController::class, 'create'])->name('sorting-center.login')->middleware('guest');
Route::post('/sorting-center/login', [\App\Http\Controllers\SortingCenter\LoginController::class, 'store'])->name('sorting-center.login.store')->middleware('guest');
Route::get('/sorting-center',        [\App\Http\Controllers\SortingCenter\LoginController::class, 'hub'])->name('sorting-center.hub')->middleware(['auth', 'is_admin']);

// ── Public ───────────────────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/categories', [\App\Http\Controllers\CategoryPageController::class, 'index'])->name('categories.page');
Route::get('/categories/{slug}', [\App\Http\Controllers\CategoryPageController::class, 'show'])->name('categories.show');
Route::get('/categories/{slug}', [\App\Http\Controllers\CategoryPageController::class, 'show'])->name('categories.show');
Route::get('/dbconn', fn() => view('dbconn'));
Route::get('/policies/{key}', [\App\Http\Controllers\PolicyController::class, 'show'])->name('policies.show');

// ── PSGC Address API proxy (server-side to avoid CORS/SSL issues) ──
Route::get('/api/psgc/regions',                          [\App\Http\Controllers\PsgcController::class, 'regions']);
Route::get('/api/psgc/regions/{code}/provinces',         [\App\Http\Controllers\PsgcController::class, 'provincesByRegion']);
Route::get('/api/psgc/provinces',                        [\App\Http\Controllers\PsgcController::class, 'provinces']);
Route::get('/api/psgc/provinces/{code}/municipalities',  [\App\Http\Controllers\PsgcController::class, 'municipalities']);
Route::get('/api/psgc/municipalities/{code}/barangays',  [\App\Http\Controllers\PsgcController::class, 'barangays']);
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('/login',    [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login',   [AuthenticatedSessionController::class, 'store']);
Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
Route::post('/register',[RegisteredUserController::class, 'store']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

// ── Authenticated (buyer-only) ────────────────────────────────
Route::middleware(['auth', 'buyer_only'])->group(function () {

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
    Route::get('/profile/settings',           [ProfileController::class, 'settings'])->name('profile.settings');
    Route::patch('/profile/settings/password',[ProfileController::class, 'changePassword'])->name('profile.change-password');
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
});

// ── Authenticated (all roles) ─────────────────────────────────
Route::middleware('auth')->group(function () {

    // Seller application (buyers only, but keeping under auth for now)
    Route::get('/become-seller',  [SellerApplicationController::class, 'create'])->name('seller.apply');
    Route::post('/become-seller', [SellerApplicationController::class, 'store'])->name('seller.apply.store');

    // Messaging
    Route::get('/messages',                        [MessageController::class, 'inbox'])->name('messages.inbox');
    Route::get('/messages/order/{orderId}',        [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/order/{orderId}',       [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/direct/{threadKey}',     [MessageController::class, 'directShow'])->name('messages.direct');
    Route::post('/messages/direct/{threadKey}',    [MessageController::class, 'directStore'])->name('messages.direct.store');
    Route::post('/messages/new',                   [MessageController::class, 'directNew'])->name('messages.new');

    // Sorting center / courier registration
    Route::get('/become-courier',  [CourierRegistrationController::class, 'create'])->name('courier.register');
    Route::post('/become-courier', [CourierRegistrationController::class, 'store'])->name('courier.register.store');
    Route::get('/courier/status',  [CourierRegistrationController::class, 'status'])->name('courier.status');
});

// ── Seller panel ─────────────────────────────────────────────
Route::middleware(['auth', 'role:seller,admin'])
    ->prefix('seller')->name('seller.')->group(function () {

    Route::get('/',                          [SellerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/books',                     [SellerBookController::class, 'index'])->name('books.index');
    Route::get('/books/create',              [SellerBookController::class, 'create'])->name('books.create');
    Route::post('/books',                    [SellerBookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit',         [SellerBookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}',              [SellerBookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}',           [SellerBookController::class, 'destroy'])->name('books.destroy');
    Route::patch('/books/{book}/archive',    [SellerBookController::class, 'archive'])->name('books.archive');
    Route::patch('/books/{book}/unarchive',  [SellerBookController::class, 'unarchive'])->name('books.unarchive');
    Route::patch('/books/{book}/stock',      [SellerBookController::class, 'updateStock'])->name('books.stock');

    Route::get('/orders',                    [SellerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}',               [SellerOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{id}',             [SellerOrderController::class, 'update'])->name('orders.update');
    Route::post('/orders/{id}/handover',     [SellerOrderController::class, 'handover'])->name('orders.handover');
    Route::post('/orders/{id}/handed-over',  [SellerOrderController::class, 'markHandedOver'])->name('orders.handed-over');

    // Reports
    Route::get('/reports',                   [SellerReportController::class, 'index'])->name('reports');

    // Account management
    Route::get('/account',                   [SellerAccountController::class, 'index'])->name('account');
    Route::patch('/account',                 [SellerAccountController::class, 'update'])->name('account.update');
});

// ── Admin panel ──────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/products',              [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/create',       [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products',             [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{id}/edit',    [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{id}',         [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{id}',      [AdminProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/categories',            [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories',           [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{id}',       [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{id}',    [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/orders',                [AdminOrderController::class, 'index'])->name('orders.index');

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

    Route::get('/seller-applications',                               [AdminSellerApplicationController::class, 'index'])->name('seller-applications.index');
    Route::get('/seller-applications/{sellerApplication}',           [AdminSellerApplicationController::class, 'show'])->name('seller-applications.show');
    Route::patch('/seller-applications/{sellerApplication}/approve', [AdminSellerApplicationController::class, 'approve'])->name('seller-applications.approve');
    Route::patch('/seller-applications/{sellerApplication}/reject',  [AdminSellerApplicationController::class, 'reject'])->name('seller-applications.reject');

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

    // Sorting center / courier management
    Route::get('/couriers',                     [AdminCourierController::class, 'index'])->name('couriers.index');
    Route::get('/couriers/{courier}',           [AdminCourierController::class, 'show'])->name('couriers.show');
    Route::patch('/couriers/{courier}/approve', [AdminCourierController::class, 'approve'])->name('couriers.approve');
    Route::patch('/couriers/{courier}/reject',  [AdminCourierController::class, 'reject'])->name('couriers.reject');
    Route::patch('/couriers/{courier}/suspend', [AdminCourierController::class, 'suspend'])->name('couriers.suspend');

    // ── Logistics panel routes (under admin.* prefix) ─────────
    Route::get('/logistics-dashboard',               [\App\Http\Controllers\Admin\Logistics\DashboardController::class, 'index'])->name('logistics-dashboard');
    Route::get('/riders',                            [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'index'])->name('riders.index');
    Route::get('/riders/{rider}',                    [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'show'])->name('riders.show');
    Route::post('/riders/{rider}/approve',           [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'approve'])->name('riders.approve');
    Route::post('/riders/{rider}/disapprove',        [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'disapprove'])->name('riders.disapprove');
    Route::post('/riders/{rider}/toggle-active',     [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'toggleActive'])->name('riders.toggle-active');

    Route::get('/pickup-requests',                   [\App\Http\Controllers\Admin\Logistics\PickupRequestController::class, 'index'])->name('pickup-requests.index');
    Route::post('/pickup-requests/{parcel}/approve', [\App\Http\Controllers\Admin\Logistics\PickupRequestController::class, 'approve'])->name('pickup-requests.approve');
    Route::post('/pickup-requests/{parcel}/reject',  [\App\Http\Controllers\Admin\Logistics\PickupRequestController::class, 'reject'])->name('pickup-requests.reject');

    Route::get('/parcels',                           [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'index'])->name('parcels.index');
    Route::get('/parcels/{parcel}',                  [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'show'])->name('parcels.show');
    Route::post('/parcels/{parcel}/mark-picked-up',  [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'markPickedUp'])->name('parcels.mark-picked-up');
    Route::post('/parcels/{parcel}/sort',            [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'sort'])->name('parcels.sort');

    Route::get('/deliveries/assign',                 [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'assignmentIndex'])->name('deliveries.assign-index');
    Route::post('/deliveries/{parcel}/assign',       [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'assign'])->name('deliveries.assign');
    Route::get('/deliveries/monitor',                [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'monitor'])->name('deliveries.monitor');
    Route::post('/deliveries/{delivery}/status',     [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'updateStatus'])->name('deliveries.update-status');

    Route::get('/parcels-reports',                   [\App\Http\Controllers\Admin\Logistics\ReportController::class, 'index'])->name('parcels-reports.index');
    Route::get('/parcels-reports/export',            [\App\Http\Controllers\Admin\Logistics\ReportController::class, 'export'])->name('parcels-reports.export');
    Route::get('/parcels-sorting',                   [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'sorting'])->name('parcels.sorting');

    Route::get('/chat',                              [\App\Http\Controllers\Admin\Logistics\ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/send',                        [\App\Http\Controllers\Admin\Logistics\ChatController::class, 'send'])->name('chat.send');
    Route::get('/chat/poll',                         [\App\Http\Controllers\Admin\Logistics\ChatController::class, 'poll'])->name('chat.poll');

    Route::get('/account',                           [\App\Http\Controllers\Admin\Logistics\AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account',                           [\App\Http\Controllers\Admin\Logistics\AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password',                  [\App\Http\Controllers\Admin\Logistics\AccountController::class, 'updatePassword'])->name('account.password');
});

// ── Courier / Sorting Center panel ──────────────────────────
Route::middleware(['auth', 'role:courier,admin'])
    ->prefix('courier')->name('courier.')->group(function () {

    Route::get('/',                            [CourierDashboardController::class, 'index'])->name('dashboard');
    Route::get('/history',                     [CourierDeliveryController::class, 'history'])->name('history');
    Route::get('/profit',                      [CourierProfitController::class, 'index'])->name('profit');
    Route::get('/deliveries/{id}',             [CourierDeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries/{id}/accept',     [CourierDeliveryController::class, 'accept'])->name('deliveries.accept');
    Route::post('/deliveries/{id}/pickup',     [CourierDeliveryController::class, 'pickup'])->name('deliveries.pickup');
    Route::post('/deliveries/{id}/in-transit', [CourierDeliveryController::class, 'inTransit'])->name('deliveries.in-transit');
    Route::post('/deliveries/{id}/complete',   [CourierDeliveryController::class, 'complete'])->name('deliveries.complete');
});

// ── Logistics / Sorting Center admin panel ───────────────────
Route::prefix('logistics')->name('logistics.')->middleware(['auth', 'is_admin'])->group(function () {

    Route::get('/',  [\App\Http\Controllers\Admin\Logistics\DashboardController::class, 'index'])->name('dashboard');

    // Riders
    Route::get('/riders',                           [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'index'])->name('riders.index');
    Route::get('/riders/{rider}',                   [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'show'])->name('riders.show');
    Route::post('/riders/{rider}/approve',          [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'approve'])->name('riders.approve');
    Route::post('/riders/{rider}/disapprove',       [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'disapprove'])->name('riders.disapprove');
    Route::post('/riders/{rider}/toggle-active',    [\App\Http\Controllers\Admin\Logistics\RiderController::class, 'toggleActive'])->name('riders.toggle-active');

    // Pickup requests
    Route::get('/pickup-requests',                   [\App\Http\Controllers\Admin\Logistics\PickupRequestController::class, 'index'])->name('pickup-requests.index');
    Route::post('/pickup-requests/{parcel}/approve', [\App\Http\Controllers\Admin\Logistics\PickupRequestController::class, 'approve'])->name('pickup-requests.approve');
    Route::post('/pickup-requests/{parcel}/reject',  [\App\Http\Controllers\Admin\Logistics\PickupRequestController::class, 'reject'])->name('pickup-requests.reject');

    // Parcels
    Route::get('/parcels',                           [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'index'])->name('parcels.index');
    Route::get('/parcels/{parcel}',                  [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'show'])->name('parcels.show');
    Route::post('/parcels/{parcel}/mark-picked-up',  [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'markPickedUp'])->name('parcels.mark-picked-up');
    Route::post('/parcels/{parcel}/sort',            [\App\Http\Controllers\Admin\Logistics\ParcelController::class, 'sort'])->name('parcels.sort');

    // Deliveries
    Route::get('/deliveries/assign',                 [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'assignmentIndex'])->name('deliveries.assign-index');
    Route::post('/deliveries/{parcel}/assign',       [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'assign'])->name('deliveries.assign');
    Route::get('/deliveries/monitor',                [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'monitor'])->name('deliveries.monitor');
    Route::post('/deliveries/{delivery}/status',     [\App\Http\Controllers\Admin\Logistics\DeliveryController::class, 'updateStatus'])->name('deliveries.update-status');

    // Reports
    Route::get('/reports',                           [\App\Http\Controllers\Admin\Logistics\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export',                    [\App\Http\Controllers\Admin\Logistics\ReportController::class, 'export'])->name('reports.export');

    // Chat
    Route::get('/chat',                              [\App\Http\Controllers\Admin\Logistics\ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/send',                        [\App\Http\Controllers\Admin\Logistics\ChatController::class, 'send'])->name('chat.send');
    Route::get('/chat/poll',                         [\App\Http\Controllers\Admin\Logistics\ChatController::class, 'poll'])->name('chat.poll');

    // Account
    Route::get('/account',                           [\App\Http\Controllers\Admin\Logistics\AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account',                           [\App\Http\Controllers\Admin\Logistics\AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password',                  [\App\Http\Controllers\Admin\Logistics\AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/logout',                           [\App\Http\Controllers\Admin\Logistics\AccountController::class, 'logout'])->name('logout');
});