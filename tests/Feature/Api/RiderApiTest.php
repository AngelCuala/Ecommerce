<?php

namespace Tests\Feature\Api;

use App\Models\Courier;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\ParcelPickup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Phase 4 of the app conversion: riders (Hub Jobs) and direct couriers through /api/v1, with the web's rules. */
class RiderApiTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->seedRiderTestData();
        $this->token = $this->rider1->user->createToken('test')->plainTextToken;
    }

    /** Each app request starts fresh: in a test the guards and default headers would carry over. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $app = str_contains($uri, '/api/v1');
        if ($app) {
            $this->app['auth']->forgetGuards();
        }
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        if ($app) {
            $this->defaultHeaders = [];
        }
        return $response;
    }

    private function api(string $method, string $uri, array $data = [], ?string $token = null)
    {
        return $this->withToken($token ?? $this->token)->json($method, '/api/v1' . $uri, $data);
    }

    public function test_online_offers_accept_decline_and_pickup(): void
    {
        $jobs = $this->api('GET', '/rider/jobs')->assertOk();
        $this->assertSame(['Bilis Hatid Express Inc.', false, 3], [$jobs->json('rider.company'), $jobs->json('rider.online'), $jobs->json('rider.max_active')]);

        $this->api('POST', '/rider/online', ['online' => true])->assertOk()
            ->assertJsonPath('message', 'You are Online. New job offers will appear here.')->assertJsonPath('rider.online', true);

        $offer = $this->dispatcher()->offer($this->parcel());
        $o = $this->api('GET', '/rider/jobs')->assertOk()->json('offers.0');
        $this->assertSame(['pickup', 'Maria Clara Santos', 'Bilis Hatid Express Inc. hub', 490.0, 30.0],
            [$o['kind'], $o['pickup_name'], $o['dropoff'], (float) $o['cod'], (float) $o['pay']]);
        $this->assertGreaterThan(0, $o['seconds_left']);

        // Another rider's offer: not found.
        $this->api('POST', "/rider/offers/{$offer->id}/accept", [], $this->rider2->user->createToken('t')->plainTextToken)->assertNotFound();

        $accepted = $this->api('POST', "/rider/offers/{$offer->id}/accept")->assertOk();
        $this->assertStringStartsWith('Job accepted: pickup of ALVY', $accepted->json('message'));
        $pickup = ParcelPickup::sole();
        $this->assertSame($pickup->id, $accepted->json('pickups.0.id'));

        $detail = $this->api('GET', "/rider/pickups/{$pickup->id}")->assertOk()->json('pickup');
        $this->assertSame(['Maria Clara Handicrafts', true, 1], [$detail['seller'], $detail['can_confirm'], $detail['items']]);

        $this->api('POST', "/rider/pickups/{$pickup->id}/confirm", ['tracking_number' => 'ALVY-WRONG'])->assertStatus(422)
            ->assertJsonValidationErrors(['tracking_number' => 'The tracking number does not match this job. Check the waybill on the parcel.']);
        $this->api('POST', "/rider/pickups/{$pickup->id}/confirm", ['tracking_number' => strtolower($pickup->parcel->tracking_number)])->assertOk()
            ->assertJsonPath('pickup.status', 'picked_up')->assertJsonPath('pickup.can_confirm', false);

        // Decline: the offer goes to the next rider (Rider 1 is the only one Online, so the offer is theirs).
        $next = $this->dispatcher()->offer($this->parcel());
        $this->assertSame($this->rider1->id, $next->rider_id);
        $this->api('POST', "/rider/offers/{$next->id}/decline")->assertOk()
            ->assertJsonPath('message', 'Offer declined. It goes to the next rider.')->assertJsonCount(0, 'offers');

        $this->api('POST', '/rider/online', ['online' => false])->assertOk()
            ->assertJsonPath('message', 'You are Offline. You won\'t get new offers, but your 1 active job stay with you.');
    }

    public function test_delivery_with_photo_otp_and_cod_then_remit(): void
    {
        $d = $this->deliveryFor($this->rider1);
        $detail = $this->api('GET', "/rider/deliveries/{$d->id}")->assertOk()->json('delivery');
        $this->assertSame([true, false, 490.0, 1, 3], [$detail['actions']['start'], $detail['actions']['deliver'], (float) $detail['cod_amount'], $detail['attempt'], $detail['max_attempts']]);

        $this->api('POST', "/rider/deliveries/{$d->id}/start")->assertOk()
            ->assertJsonPath('message', 'Out for delivery. Ask the buyer for the OTP on their order page.')
            ->assertJsonPath('delivery.actions.deliver', true);
        $otp = $d->fresh()->otp_code;

        $form = fn (array $over = []) => $this->withToken($this->token)->post("/api/v1/rider/deliveries/{$d->id}/deliver", $over + [
            'pod_photo' => UploadedFile::fake()->image('pod.jpg'), 'otp' => $otp, 'cod_collected' => '490',
        ], ['Accept' => 'application/json']);

        $form(['pod_photo' => null])->assertStatus(422)->assertJsonValidationErrors(['pod_photo' => 'Take a proof-of-delivery photo.']);
        $form(['otp' => $otp === '000000' ? '111111' : '000000'])->assertStatus(422)->assertJsonValidationErrors(['otp' => 'Wrong OTP. 4 tries left.']);
        $form(['cod_collected' => '450'])->assertStatus(422)
            ->assertJsonValidationErrors(['cod_collected' => 'The COD amount doesn\'t match. Collect exactly ₱490.00.']);
        $form()->assertOk()->assertJsonPath('message', 'Delivered. You earned ₱50.00 and collected ₱490.00 COD to remit.')
            ->assertJsonPath('delivery.status', 'delivered');
        $this->assertNotNull($this->api('GET', "/rider/deliveries/{$d->id}")->json('delivery.pod_url'));

        $e = $this->api('GET', '/rider/earnings')->assertOk();
        $this->assertSame([50.0, 1], [(float) $e->json('today_pay'), $e->json('today_jobs')]);
        $this->assertSame('Delivered', $this->api('GET', '/rider/history')->assertOk()->json('rows.0.result'));

        $cod = $this->api('GET', '/rider/cod')->assertOk();
        $this->assertSame(490.0, (float) $cod->json('balance'));
        $this->api('POST', '/rider/cod/remit', ['amount' => 600, 'request_token' => $cod->json('request_token')])->assertStatus(422)
            ->assertJsonValidationErrors(['amount' => 'You can remit up to your COD balance of ₱490.00.']);
        $this->api('POST', '/rider/cod/remit', ['amount' => 490, 'request_token' => $cod->json('request_token')])->assertOk()
            ->assertJsonPath('message', 'Remittance of ₱490.00 sent. It stays Pending until Bilis Hatid Express Inc. confirms it.')
            ->assertJsonPath('pending', 490);
    }

    public function test_failed_delivery_and_return_to_sender(): void
    {
        $d = $this->deliveryFor($this->rider1);
        $this->api('POST', "/rider/deliveries/{$d->id}/fail", ['reason' => 'Lost'])->assertStatus(422)
            ->assertJsonValidationErrors(['reason' => 'Choose a reason from the list.']);
        $this->api('POST', "/rider/deliveries/{$d->id}/fail", ['reason' => 'Refused'])->assertOk()
            ->assertJsonPath('message', $d->parcel->tracking_number . ' is Return to Sender. No further attempt.')
            ->assertJsonPath('delivery.actions.fail', false);

        $parcel = $d->parcel->fresh();
        $parcel->update(['rts_rider_id' => $this->rider1->id, 'rts_assigned_at' => now()]);
        $this->assertSame($parcel->tracking_number, $this->api('GET', '/rider/jobs')->json('returns.0.tracking'));
        $this->api('POST', "/rider/returns/{$parcel->id}/returned")->assertOk()
            ->assertJsonPath('message', "{$parcel->tracking_number} handed back to the seller.")->assertJsonCount(0, 'returns');
    }

    public function test_profile_documents_and_suspended_rider(): void
    {
        $p = $this->api('GET', '/rider/profile')->assertOk();
        $this->assertSame(['123ABC', 'Motorcycle', true], [$p->json('rider.plate_number'), $p->json('rider.vehicle_type'), $p->json('rider.has_license_file')]);
        $this->assertContains('Van', $p->json('vehicles'));

        $this->withToken($this->token)->post('/api/v1/rider/profile', ['vehicle_type' => 'Motorcycle', 'plate_number' => 'NBC1234',
            'license_number' => 'N01-23-456789', 'license_expiry' => '2030-03-22'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['plate_number' => 'Plate number NBC1234 is already registered to another rider.']);
        $this->withToken($this->token)->post('/api/v1/rider/profile', ['vehicle_type' => 'Motorcycle', 'plate_number' => '777 xyz',
            'license_number' => 'N01-23-456789', 'license_expiry' => '2030-03-22'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('rider.plate_number', '777XYZ')->assertJsonPath('rider.needs_reverification', true);

        $this->withToken($this->token)->get('/api/v1/rider/documents/license', ['Accept' => 'application/json'])->assertOk();

        $suspended = $this->rider3->user->createToken('t')->plainTextToken;
        $this->api('POST', '/rider/online', ['online' => true], $suspended)->assertStatus(422)
            ->assertJsonPath('message', 'Your rider account is suspended by Bilis Hatid Express Inc.. You can\'t go Online or receive jobs. Please contact them.');
    }

    public function test_direct_courier_flow_and_account(): void
    {
        $user = User::create(['name' => 'Mario Santos', 'email' => 'mario@example.com', 'password' => Hash::make('Courier@2026'),
            'role' => 'courier', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        Courier::create(['user_id' => $user->id, 'last_name' => 'Santos', 'first_name' => 'Mario', 'sex' => 'Male', 'contact_no' => '09181234568',
            'birthday' => '1995-05-20', 'age' => 31, 'province' => 'Laguna', 'municipality' => 'Magdalena', 'barangay' => 'Poblacion',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'ABC1234', 'or_cr_path' => 'a', 'id_license_path' => 'b', 'status' => 'approved']);
        $token = $user->createToken('t')->plainTextToken;

        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $order->update(['status' => Order::PROCESSING, 'accepted_at' => now(), 'packed_at' => now()]);
        $delivery = Delivery::create(['order_id' => $order->id, 'status' => 'available', 'courier_name' => 'Courier', 'delivery_fee' => 50]);

        $dash = $this->api('GET', '/courier/dashboard', [], $token)->assertOk();
        $this->assertSame([$delivery->id, true], [$dash->json('available.0.id'), $dash->json('available.0.actions.accept')]);

        $this->api('POST', "/courier/deliveries/{$delivery->id}/accept", [], $token)->assertOk()
            ->assertJsonPath('delivery.status_label', 'Pending Pickup');
        $this->api('POST', "/courier/deliveries/{$delivery->id}/accept", [], $token)->assertStatus(422)
            ->assertJsonPath('message', 'This delivery request is no longer available.');
        $this->api('POST', "/courier/deliveries/{$delivery->id}/pickup", [], $token)->assertOk()->assertJsonPath('delivery.status_label', 'Picked Up');
        $this->api('POST', "/courier/deliveries/{$delivery->id}/in-transit", [], $token)->assertOk()->assertJsonPath('delivery.status_label', 'Out for Delivery');
        $this->api('POST', "/courier/deliveries/{$delivery->id}/complete", ['notes' => 'Left with the guard'], $token)->assertOk()
            ->assertJsonPath('message', 'Delivery completed! ₱50.00 added to your earnings.')->assertJsonPath('delivery.status_label', 'Delivered');

        $profit = $this->api('GET', '/courier/profit', [], $token)->assertOk();
        $this->assertSame([50.0, 1], [(float) $profit->json('total_earnings'), $profit->json('total_deliveries')]);
        $history = $this->api('GET', '/courier/history', [], $token)->assertOk();
        $this->assertSame('delivered', $history->json('data.0.status'));
        // The row opens its delivery page; the website's cards and status filter come along.
        $this->assertSame('courier', $history->json('data.0.kind'));
        $this->assertIsInt($history->json('data.0.id'));
        $this->assertSame(1, $history->json('summary.delivered'));
        $this->assertSame('Failed / returned', $history->json('statuses.failed'));

        $this->assertSame('m****@*******.com', $this->api('GET', '/courier/account', [], $token)->assertOk()->json('courier.masked_email'));
        $this->api('PUT', '/courier/account/password', ['current_password' => 'nope', 'password' => 'Bago@2026x', 'password_confirmation' => 'Bago@2026x'], $token)
            ->assertStatus(422)->assertJsonValidationErrors(['current_password' => 'The current password you entered is incorrect.']);

        // A rider of a logistics company is sent to Hub Jobs.
        $this->api('GET', '/courier/dashboard')->assertStatus(409)->assertJsonPath('home', 'rider');
    }

    /** Where the app opens, like the web's courier dashboard: Hub Jobs only for an approved rider without a direct-courier profile. */
    public function test_home_for_riders_and_couriers(): void
    {
        $this->api('GET', '/me')->assertOk()->assertJsonPath('user.home', 'rider')
            ->assertJsonPath('user.has_rider', true)->assertJsonPath('user.has_courier', false);

        // A rider whose application is still pending is not sent to Hub Jobs (the web answers 403 there).
        $this->rider2->update(['application_status' => 'pending']);
        $this->api('GET', '/me', [], $this->rider2->user->createToken('t')->plainTextToken)->assertOk()
            ->assertJsonPath('user.home', 'courier')->assertJsonPath('user.has_rider', false);

        // Both an approved direct courier and a rider: the courier dashboard, with Hub Jobs available too.
        Courier::create(['user_id' => $this->rider1->user_id, 'last_name' => 'Garcia', 'first_name' => 'Pedro', 'sex' => 'Male', 'contact_no' => '09181234570',
            'birthday' => '1990-05-20', 'age' => 36, 'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Central',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'DEF4567', 'or_cr_path' => 'a', 'id_license_path' => 'b', 'status' => 'approved']);
        $this->api('GET', '/me')->assertOk()->assertJsonPath('user.home', 'courier')
            ->assertJsonPath('user.has_rider', true)->assertJsonPath('user.has_courier', true);
        $this->api('GET', '/courier/dashboard')->assertOk();
        $this->api('GET', '/rider/jobs')->assertOk();
    }

    public function test_pending_courier_and_other_roles(): void
    {
        $user = User::create(['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'password' => Hash::make('Courier@2026'),
            'role' => 'courier_pending', 'approval_status' => 'pending', 'email_verified_at' => now()]);
        Courier::create(['user_id' => $user->id, 'last_name' => 'Reyes', 'first_name' => 'Ana', 'sex' => 'Female', 'contact_no' => '09181234569',
            'birthday' => '1996-05-20', 'age' => 30, 'province' => 'Laguna', 'municipality' => 'Magdalena', 'barangay' => 'Poblacion',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'XYZ9876', 'or_cr_path' => 'a', 'id_license_path' => 'b', 'status' => 'pending']);
        $token = $user->createToken('t')->plainTextToken;

        $this->api('GET', '/courier/status', [], $token)->assertOk()->assertJsonPath('application.status', 'pending')
            ->assertJsonPath('application.name', 'Ana Reyes');
        $this->api('GET', '/rider/jobs', [], $token)->assertForbidden()->assertJsonPath('home', 'courier_status');
        $this->api('GET', '/rider/jobs', [], $this->buyer1->createToken('t')->plainTextToken)->assertForbidden()
            ->assertJsonPath('message', 'This part of the app is for riders and couriers.');
    }

    /** A courier waiting for approval: only its status, notifications and account; the hub's decision arrives as a notification and an email. */
    public function test_pending_courier_sees_status_and_gets_the_decision(): void
    {
        $make = function (string $email, string $plate) {
            $user = User::create(['name' => 'Ana Reyes', 'email' => $email, 'password' => Hash::make('Courier@2026'),
                'role' => 'courier_pending', 'approval_status' => 'pending', 'email_verified_at' => now()]);
            $courier = Courier::create(['user_id' => $user->id, 'sorting_center_id' => $this->logistics1->id, 'last_name' => 'Reyes', 'first_name' => 'Ana',
                'sex' => 'Female', 'contact_no' => '09181234569', 'birthday' => '1996-05-20', 'age' => 30, 'province' => 'Laguna',
                'municipality' => 'Magdalena', 'barangay' => 'Poblacion', 'vehicle_type' => 'Motorcycle', 'plate_number' => $plate,
                'or_cr_path' => 'a', 'id_license_path' => 'b', 'status' => 'pending']);
            return [$user, $courier, $user->createToken('t')->plainTextToken];
        };
        $hub = $this->logistics1->createToken('hub')->plainTextToken;

        [$user, $courier, $token] = $make('ana@example.com', 'XYZ9876');
        $this->api('GET', '/me', [], $token)->assertOk()->assertJsonPath('user.home', 'courier_status');
        $this->api('GET', '/courier/status', [], $token)->assertOk()->assertJsonPath('application.status', 'pending');
        $this->api('GET', '/notifications', [], $token)->assertOk();
        $this->api('GET', '/courier/dashboard', [], $token)->assertForbidden();
        $this->api('GET', '/rider/jobs', [], $token)->assertForbidden();

        // Approved by the hub: a notification and an email; the app opens the rider part.
        $this->api('POST', "/logistics/couriers/{$courier->id}/approve", [], $hub)->assertOk();
        Mail::assertSent(\App\Mail\CourierApprovedMail::class, fn ($m) => $m->hasTo('ana@example.com'));
        $n = $this->api('GET', '/notifications', [], $token)->assertOk()->json('data.0');
        $this->assertSame('Courier application approved', $n['title']);
        $this->api('GET', '/me', [], $token)->assertJsonPath('user.home', 'courier');
        $this->api('GET', '/courier/status', [], $token)->assertOk()->assertJsonPath('application.status', 'approved');

        // Rejected (with the reason): a notification and an email; the status shows why.
        [$user2, $courier2, $token2] = $make('ben@example.com', 'ABC1111');
        $this->api('POST', "/logistics/couriers/{$courier2->id}/reject", ['rejection_reason' => 'The license photo is blurry.'], $hub)->assertOk();
        Mail::assertSent(\App\Mail\CourierRejectedMail::class, fn ($m) => $m->hasTo('ben@example.com'));
        $n = $this->api('GET', '/notifications', [], $token2)->assertOk()->json('data.0');
        $this->assertSame('Courier application rejected', $n['title']);
        $this->assertStringContainsString('The license photo is blurry.', $n['body']);
        $this->api('GET', '/courier/status', [], $token2)->assertOk()->assertJsonPath('application.status', 'rejected')
            ->assertJsonPath('application.rejection_reason', 'The license photo is blurry.');
    }
}
