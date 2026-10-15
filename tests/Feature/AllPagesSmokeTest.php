<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\ParcelDelivery;
use App\Models\Payment;
use App\Models\Review;
use App\Models\SellerApplication;
use App\Models\SortingCenterApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * Opens every GET page as every role (guest, buyer, seller, pending seller, rider, sorting center,
 * admin) with real records behind it, and fails on any server error (500). A 403, 404 or redirect
 * is fine: the role simply isn't allowed there.
 */
class AllPagesSmokeTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    public function test_no_page_returns_a_server_error_for_any_role(): void
    {
        $this->seedRiderTestData();
        $admin = $this->account('admin', 'admin@alvy.test', 'Admin');

        // Records for the pages to show.
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p01, 1, 'Natural / M'), $this->cart($this->buyer1, $this->p02)])->first(), Order::COMPLETED);
        $item = $order->items()->first();
        Review::create(['product_id' => $item->product_id, 'user_id' => $this->buyer1->id, 'order_id' => $order->id, 'order_item_id' => $item->id, 'rating' => 5, 'comment' => 'Great']);
        $delivery = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $pickup = $this->pickupFor($this->rider1);
        $courierUser = $this->account('courier', 'courier@example.com', 'Direct Courier');
        $courier = Courier::forceCreate(['user_id' => $courierUser->id, 'first_name' => 'Direct', 'last_name' => 'Courier', 'status' => 'approved',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'DC1234', 'sorting_center_id' => $this->logistics1->id,
            'sex' => 'Male', 'contact_no' => '09170000015', 'birthday' => '1990-01-01', 'age' => 36, 'province' => 'Metro Manila', 'municipality' => 'Quezon City',
            'barangay' => 'Central', 'or_cr_path' => 'x.pdf', 'id_license_path' => 'x.jpg']);
        $payment = Payment::create(['user_id' => $this->buyer1->id, 'checkout_group' => $order->checkout_group ?? 'g', 'provider' => 'sandbox',
            'method' => 'gcash', 'amount' => 100, 'status' => 'pending', 'reference' => 'REF1']);
        $scApp = SortingCenterApplication::where('user_id', $this->logistics1->id)->first();
        $sellerApp = $this->seller1->sellerApplication;
        $voucher = \App\Models\Voucher::where('code', 'MCH50')->first();
        $threadKey = min($this->buyer1->id, $this->seller1->id) . '_' . max($this->buyer1->id, $this->seller1->id);

        $values = [
            'book' => $this->p02->id, 'slug' => $this->p02->slug, 'sellerId' => $this->seller1->id, 'voucher' => $voucher->id,
            'payment' => $payment->id, 'pickup' => $pickup->id, 'delivery' => $delivery->id, 'parcel' => $delivery->parcel_id,
            'rider' => $this->rider1->id, 'courier' => $courier->id, 'document' => 'license', 'threadKey' => $threadKey,
            'sellerApplication' => $sellerApp->id, 'application' => $scApp?->id ?? 1, 'code' => '137404000', 'key' => 'terms', 'type' => 'terms', 'path' => 'x',
        ];
        $idFor = function (string $uri) use ($order, $delivery) {
            return match (true) {
                str_contains($uri, 'customers') || str_contains($uri, 'sellers') || str_contains($uri, 'users') => $this->buyer1->id,
                str_contains($uri, 'deliveries')  => $delivery->id,
                str_contains($uri, 'products')    => $this->p02->id,
                default                           => $order->id,
            };
        };

        $actors = ['guest' => null, 'buyer' => $this->buyer1, 'seller' => $this->seller1, 'pending seller' => $this->seller3,
            'rider' => $this->rider1->user, 'direct courier' => $courierUser, 'sorting center' => $this->logistics1, 'admin' => $admin];

        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => in_array('GET', $r->methods(), true)
            && ! str_starts_with($r->uri(), '_') && ! str_starts_with($r->uri(), 'api/') && ! str_contains($r->uri(), 'storage/')
            && ! str_contains($r->uri(), 'auth/google') && ! str_contains($r->uri(), 'logout') && ! str_contains($r->uri(), 'sanctum'));

        $failures = [];
        foreach ($actors as $role => $user) {
            foreach ($routes as $route) {
                $uri = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($values, $idFor, $route) {
                    return $m[1] === 'id' ? $idFor($route->uri()) : ($values[$m[1]] ?? 1);
                }, $route->uri());

                $this->flushSession();
                $client = $user ? $this->actingAs($user) : $this;
                if (! $user) {
                    auth()->logout();
                }
                try {
                    $status = $client->get('/' . ltrim($uri, '/'))->getStatusCode();
                } catch (\Throwable $e) {
                    $status = 'EXC ' . class_basename($e) . ': ' . \Illuminate\Support\Str::limit($e->getMessage(), 160);
                }
                if (! is_int($status) || $status >= 500) {
                    $failures[] = "[{$role}] GET /{$uri} → {$status}";
                }
            }
        }

        $this->assertSame([], $failures, "Pages with server errors:\n" . implode("\n", $failures));
    }
}
